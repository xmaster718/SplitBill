<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_user();
$pdo = db();
$method = require_method('GET', 'POST', 'DELETE');

if ($method === 'GET') {
    $g = load_group_for_user(int_value($_GET['group_id'] ?? null, 'group_id'), $user);
    $stmt = $pdo->prepare('SELECT id, name, user_id FROM group_members WHERE group_id = ? ORDER BY id');
    $stmt->execute([$g['id']]);
    $members = array_map(fn(array $m): array => [
        'id'      => (int)$m['id'],
        'name'    => $m['name'],
        'user_id' => $m['user_id'] === null ? null : (int)$m['user_id'],
    ], $stmt->fetchAll());
    respond(['members' => $members]);
}

if ($method === 'POST') {                           // добавить «гостя» по имени
    $in = json_body();
    $g = load_group_for_user(int_value($in['group_id'] ?? null, 'group_id'), $user);
    $name = str_field($in, 'name', 1, 60, 'Имя участника');

    $count = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ?');
    $count->execute([$g['id']]);
    if ((int)$count->fetchColumn() >= 50) {
        throw new ApiError(400, 'В группе не может быть больше 50 участников');
    }
    try {
        $pdo->prepare('INSERT INTO group_members (group_id, name) VALUES (?, ?)')->execute([$g['id'], $name]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            throw new ApiError(409, 'Участник с таким именем уже есть в группе');
        }
        throw $e;
    }
    respond(['id' => (int)$pdo->lastInsertId(), 'name' => $name, 'user_id' => null], 201);
}

// DELETE: убрать «гостя» можно, только если у него нет расходов; делает это создатель группы
$stmt = $pdo->prepare('SELECT id, group_id, user_id FROM group_members WHERE id = ?');
$stmt->execute([int_value($_GET['id'] ?? null, 'id')]);
$m = $stmt->fetch();
if (!$m) {
    throw new ApiError(404, 'Участник не найден');
}
$g = load_group_for_user((int)$m['group_id'], $user);
if (!$g['is_owner']) {
    throw new ApiError(403, 'Удалять участников может только создатель группы');
}
if ($m['user_id'] !== null) {
    throw new ApiError(409, 'Нельзя удалить участника с аккаунтом');
}
$used = $pdo->prepare('SELECT (SELECT COUNT(*) FROM expenses WHERE paid_by = ?) + (SELECT COUNT(*) FROM expense_shares WHERE member_id = ?)');
$used->execute([$m['id'], $m['id']]);
if ((int)$used->fetchColumn() > 0) {
    throw new ApiError(409, 'Нельзя удалить участника, у которого есть расходы');
}
$pdo->prepare('DELETE FROM group_members WHERE id = ?')->execute([$m['id']]);
respond(['ok' => true]);
