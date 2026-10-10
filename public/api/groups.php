<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_user();
$pdo = db();
$method = require_method('GET', 'POST', 'DELETE');

if ($method === 'GET') {
    if (isset($_GET['id'])) {                       // одна группа
        $g = load_group_for_user(int_value($_GET['id'], 'id'), $user);
        respond(['group' => [
            'id' => $g['id'], 'name' => $g['name'], 'invite_code' => $g['invite_code'],
            'is_owner' => $g['is_owner'], 'created_at' => $g['created_at'],
        ]]);
    }

    $stmt = $pdo->prepare(
        "SELECT g.id, g.name, g.invite_code, g.owner_id, g.created_at,
                (SELECT COUNT(*) FROM group_members m WHERE m.group_id = g.id) AS members_count,
                (SELECT COALESCE(SUM(e.amount_cents), 0) FROM expenses e WHERE e.group_id = g.id AND e.kind = 'expense') AS total_cents
           FROM split_groups g JOIN group_users gu ON gu.group_id = g.id
          WHERE gu.user_id = ?
          ORDER BY g.id DESC"
    );
    $stmt->execute([$user['id']]);
    $groups = array_map(fn(array $g): array => [
        'id'            => (int)$g['id'],
        'name'          => $g['name'],
        'invite_code'   => $g['invite_code'],
        'is_owner'      => (int)$g['owner_id'] === $user['id'],
        'members_count' => (int)$g['members_count'],
        'total_cents'   => (int)$g['total_cents'],
        'created_at'    => $g['created_at'],
    ], $stmt->fetchAll());
    respond(['groups' => $groups]);
}

if ($method === 'POST') {                           // создать группу
    $name = str_field(json_body(), 'name', 2, 80, 'Название группы');

    $owned = $pdo->prepare('SELECT COUNT(*) FROM split_groups WHERE owner_id = ?');
    $owned->execute([$user['id']]);
    if ((int)$owned->fetchColumn() >= 50) {
        throw new ApiError(400, 'Можно создать не более 50 групп');
    }

    $codeCheck = $pdo->prepare('SELECT 1 FROM split_groups WHERE invite_code = ?');
    do {
        $code = generate_invite_code();
        $codeCheck->execute([$code]);
    } while ($codeCheck->fetchColumn());

    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO split_groups (name, owner_id, invite_code) VALUES (?, ?, ?)')
            ->execute([$name, $user['id'], $code]);
        $groupId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO group_users (group_id, user_id) VALUES (?, ?)')->execute([$groupId, $user['id']]);
        $pdo->prepare('INSERT INTO group_members (group_id, user_id, name) VALUES (?, ?, ?)')
            ->execute([$groupId, $user['id'], $user['name']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    log_event('info', 'Создана группа', ['group_id' => $groupId, 'user_id' => $user['id']]);
    respond(['id' => $groupId, 'name' => $name, 'invite_code' => $code], 201);
}

// DELETE: удалить группу может только её создатель
$g = load_group_for_user(int_value($_GET['id'] ?? null, 'id'), $user);
if (!$g['is_owner']) {
    throw new ApiError(403, 'Удалить группу может только её создатель');
}
$pdo->prepare('DELETE FROM split_groups WHERE id = ?')->execute([$g['id']]);
log_event('info', 'Группа удалена', ['group_id' => $g['id'], 'user_id' => $user['id']]);
respond(['ok' => true]);
