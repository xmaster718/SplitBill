<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_user();
$pdo = db();
$method = require_method('GET', 'POST', 'DELETE');

// ---------- GET: список с поиском, фильтром, сортировкой и пагинацией ----------
if ($method === 'GET') {
    $g = load_group_for_user(int_value($_GET['group_id'] ?? null, 'group_id'), $user);

    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 10)));
    $q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 60);
    $sorts = [                                      // белый список: в SQL попадает только то, что здесь перечислено
        'date_desc'   => 'e.spent_on DESC, e.id DESC',
        'date_asc'    => 'e.spent_on ASC, e.id ASC',
        'amount_desc' => 'e.amount_cents DESC, e.id DESC',
        'amount_asc'  => 'e.amount_cents ASC, e.id ASC',
    ];
    $order = $sorts[$_GET['sort'] ?? 'date_desc'] ?? $sorts['date_desc'];

    $where = ['e.group_id = ?'];
    $params = [$g['id']];
    if ($q !== '') {
        $where[] = 'e.description LIKE ?';
        $params[] = '%' . addcslashes($q, '%_\\') . '%';
    }
    if (isset($_GET['paid_by']) && $_GET['paid_by'] !== '') {
        $where[] = 'e.paid_by = ?';
        $params[] = int_value($_GET['paid_by'], 'paid_by');
    }
    if (in_array($_GET['kind'] ?? '', ['expense', 'payment'], true)) {
        $where[] = 'e.kind = ?';
        $params[] = $_GET['kind'];
    }
    $whereSql = implode(' AND ', $where);

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM expenses e WHERE {$whereSql}");
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, (int)($_GET['page'] ?? 1)), $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT e.id, e.kind, e.description, e.amount_cents, e.spent_on, e.paid_by, e.created_by, m.name AS paid_by_name
           FROM expenses e JOIN group_members m ON m.id = e.paid_by
          WHERE {$whereSql}
          ORDER BY {$order}
          LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Доли для расходов на этой странице одним запросом
    $sharesByExpense = [];
    if ($rows) {
        $ids = array_map(fn(array $r): int => (int)$r['id'], $rows);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sh = $pdo->prepare(
            "SELECT s.expense_id, s.member_id, m.name, s.share_cents
               FROM expense_shares s JOIN group_members m ON m.id = s.member_id
              WHERE s.expense_id IN ({$in}) ORDER BY m.id"
        );
        $sh->execute($ids);
        foreach ($sh->fetchAll() as $s) {
            $sharesByExpense[(int)$s['expense_id']][] = [
                'member_id' => (int)$s['member_id'], 'name' => $s['name'], 'share_cents' => (int)$s['share_cents'],
            ];
        }
    }

    $items = array_map(fn(array $r): array => [
        'id'            => (int)$r['id'],
        'kind'          => $r['kind'],
        'description'   => $r['description'],
        'amount_cents'  => (int)$r['amount_cents'],
        'spent_on'      => $r['spent_on'],
        'paid_by'       => (int)$r['paid_by'],
        'paid_by_name'  => $r['paid_by_name'],
        'shares'        => $sharesByExpense[(int)$r['id']] ?? [],
        'can_delete'    => $g['is_owner'] || ($r['created_by'] !== null && (int)$r['created_by'] === $user['id']),
    ], $rows);

    $sum = $pdo->prepare("SELECT COALESCE(SUM(amount_cents), 0) FROM expenses WHERE group_id = ? AND kind = 'expense'");
    $sum->execute([$g['id']]);

    respond([
        'items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage,
        'total_pages' => $totalPages, 'group_total_cents' => (int)$sum->fetchColumn(),
    ]);
}

// ---------- POST: добавить расход или перевод долга ----------
if ($method === 'POST') {
    $in = json_body();
    $g = load_group_for_user(int_value($in['group_id'] ?? null, 'group_id'), $user);

    $kind = ($in['kind'] ?? 'expense') === 'payment' ? 'payment' : 'expense';
    $amount = parse_amount_cents($in['amount'] ?? null);
    if ($amount === null) {
        throw new ApiError(400, 'Введите сумму больше нуля (не больше двух знаков после запятой)');
    }
    $spentOn = $in['spent_on'] ?? date('Y-m-d');
    if (!valid_date($spentOn)) {
        throw new ApiError(400, 'Некорректная дата');
    }

    $mem = $pdo->prepare('SELECT id FROM group_members WHERE group_id = ?');
    $mem->execute([$g['id']]);
    $memberSet = array_flip(array_map('intval', $mem->fetchAll(PDO::FETCH_COLUMN)));

    $paidBy = int_value($in['paid_by'] ?? null, 'Кто заплатил');
    if (!isset($memberSet[$paidBy])) {
        throw new ApiError(400, 'Плательщик не состоит в этой группе');
    }

    if ($kind === 'payment') {
        // Перевод долга: paid_by отдал деньги одному получателю.
        $list = $in['participant_ids'] ?? [];
        if (!is_array($list) || count($list) !== 1) {
            throw new ApiError(400, 'Укажите одного получателя перевода');
        }
        $to = int_value(array_values($list)[0], 'Получатель');
        if (!isset($memberSet[$to]) || $to === $paidBy) {
            throw new ApiError(400, 'Некорректный получатель перевода');
        }
        $description = 'Перевод';
        $shares = [$to => $amount];
    } else {
        $description = str_field($in, 'description', 1, 120, 'Описание');
        if (isset($in['shares'])) {
            // Деление «по суммам»: сумма долей должна точно совпадать с суммой расхода.
            if (!is_array($in['shares']) || count($in['shares']) === 0) {
                throw new ApiError(400, 'Укажите доли участников');
            }
            $shares = [];
            foreach ($in['shares'] as $row) {
                $mid = int_value(is_array($row) ? ($row['member_id'] ?? null) : null, 'Участник');
                $cents = parse_amount_cents(is_array($row) ? ($row['amount'] ?? null) : null);
                if (!isset($memberSet[$mid]) || isset($shares[$mid])) {
                    throw new ApiError(400, 'Некорректный список участников');
                }
                if ($cents === null) {
                    throw new ApiError(400, 'Доля каждого участника должна быть больше нуля');
                }
                $shares[$mid] = $cents;
            }
            if (array_sum($shares) !== $amount) {
                throw new ApiError(400, 'Сумма долей должна равняться сумме расхода');
            }
        } else {
            // Деление поровну между выбранными участниками.
            $list = $in['participant_ids'] ?? [];
            if (!is_array($list) || count($list) === 0 || count($list) > 50) {
                throw new ApiError(400, 'Отметьте хотя бы одного участника');
            }
            $ids = array_values(array_unique(array_map(fn($v): int => int_value($v, 'Участник'), $list)));
            foreach ($ids as $id) {
                if (!isset($memberSet[$id])) {
                    throw new ApiError(400, 'Участник не состоит в этой группе');
                }
            }
            $shares = split_equal($amount, $ids);
        }
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO expenses (group_id, kind, description, amount_cents, paid_by, spent_on, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$g['id'], $kind, $description, $amount, $paidBy, $spentOn, $user['id']]);
        $expenseId = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare('INSERT INTO expense_shares (expense_id, member_id, share_cents) VALUES (?, ?, ?)');
        foreach ($shares as $memberId => $cents) {
            $ins->execute([$expenseId, $memberId, $cents]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    log_event('info', 'Добавлен расход', ['expense_id' => $expenseId, 'group_id' => $g['id'], 'kind' => $kind]);
    respond(['id' => $expenseId], 201);
}

// ---------- DELETE: удалить может автор записи или создатель группы ----------
$stmt = $pdo->prepare('SELECT id, group_id, created_by FROM expenses WHERE id = ?');
$stmt->execute([int_value($_GET['id'] ?? null, 'id')]);
$e = $stmt->fetch();
if (!$e) {
    throw new ApiError(404, 'Запись не найдена');
}
$g = load_group_for_user((int)$e['group_id'], $user);
if (!$g['is_owner'] && ($e['created_by'] === null || (int)$e['created_by'] !== $user['id'])) {
    throw new ApiError(403, 'Удалить запись может её автор или создатель группы');
}
$pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([$e['id']]);
log_event('info', 'Запись удалена', ['expense_id' => (int)$e['id'], 'user_id' => $user['id']]);
respond(['ok' => true]);
