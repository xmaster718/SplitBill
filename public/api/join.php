<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$user = require_user();
require_method('POST');
$pdo = db();

$code = strtoupper(trim((string)(json_body()['code'] ?? '')));
if (!preg_match('/^[A-HJ-NP-Z2-9]{8}$/', $code)) {
    throw new ApiError(400, 'Код приглашения — 8 символов, например K7M2QX9A');
}

$stmt = $pdo->prepare('SELECT id FROM split_groups WHERE invite_code = ?');
$stmt->execute([$code]);
$groupId = (int)$stmt->fetchColumn();
if ($groupId === 0) {
    throw new ApiError(404, 'Группа с таким кодом не найдена');
}

$already = $pdo->prepare('SELECT 1 FROM group_users WHERE group_id = ? AND user_id = ?');
$already->execute([$groupId, $user['id']]);
if ($already->fetchColumn()) {
    respond(['group_id' => $groupId, 'already_member' => true]);
}

$count = $pdo->prepare('SELECT COUNT(*) FROM group_members WHERE group_id = ?');
$count->execute([$groupId]);
if ((int)$count->fetchColumn() >= 50) {
    throw new ApiError(400, 'В группе уже максимальное число участников (50)');
}

$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO group_users (group_id, user_id) VALUES (?, ?)')->execute([$groupId, $user['id']]);
    $pdo->prepare('INSERT INTO group_members (group_id, user_id, name) VALUES (?, ?, ?)')
        ->execute([$groupId, $user['id'], unique_member_name($groupId, $user['name'])]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
log_event('info', 'Вход в группу по коду', ['group_id' => $groupId, 'user_id' => $user['id']]);
respond(['group_id' => $groupId, 'already_member' => false], 201);
