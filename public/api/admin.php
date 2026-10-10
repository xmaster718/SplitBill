<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$admin = require_admin();
$pdo = db();
$method = require_method('GET', 'DELETE');

if ($method === 'GET') {
    $count = fn(string $table): int => (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();   // $table — только константы ниже

    $users = $pdo->query(
        'SELECT u.id, u.name, u.email, u.role, u.created_at,
                (SELECT COUNT(*) FROM group_users gu WHERE gu.user_id = u.id) AS groups_count
           FROM users u ORDER BY u.id DESC LIMIT 200'
    )->fetchAll();
    $groups = $pdo->query(
        'SELECT g.id, g.name, g.created_at, u.name AS owner_name,
                (SELECT COUNT(*) FROM group_members m WHERE m.group_id = g.id) AS members_count,
                (SELECT COUNT(*) FROM expenses e WHERE e.group_id = g.id) AS expenses_count
           FROM split_groups g JOIN users u ON u.id = g.owner_id ORDER BY g.id DESC LIMIT 200'
    )->fetchAll();

    respond([
        'stats'  => ['users' => $count('users'), 'groups' => $count('split_groups'), 'expenses' => $count('expenses')],
        'users'  => $users,
        'groups' => $groups,
    ]);
}

// DELETE ?type=user|group&id=...
$id = int_value($_GET['id'] ?? null, 'id');
$type = $_GET['type'] ?? '';
if ($type === 'user') {
    if ($id === $admin['id']) {
        throw new ApiError(400, 'Нельзя удалить самого себя');
    }
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
} elseif ($type === 'group') {
    $pdo->prepare('DELETE FROM split_groups WHERE id = ?')->execute([$id]);
} else {
    throw new ApiError(400, 'type должен быть user или group');
}
log_event('warning', 'Удаление администратором', ['admin_id' => $admin['id'], 'type' => $type, 'id' => $id]);
respond(['ok' => true]);
