<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

require_method('POST');
$in = json_body();

$email = normalize_email($in['email'] ?? null) ?? '';
$password = is_string($in['password'] ?? null) ? $in['password'] : '';
$ip = substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
$pdo = db();

// Защита от подбора пароля: не больше 8 неудачных попыток за 15 минут на один email.
$cnt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
$cnt->execute([$email]);
if ((int)$cnt->fetchColumn() >= 8) {
    throw new ApiError(429, 'Слишком много попыток входа. Подождите 15 минут.');
}

$stmt = $pdo->prepare('SELECT id, name, email, role, password_hash FROM users WHERE email = ?');
$stmt->execute([$email]);
$u = $stmt->fetch();

if (!$u || !password_verify($password, $u['password_hash'])) {
    $pdo->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)')->execute([$email, $ip]);
    log_event('warning', 'Неудачный вход', ['ip' => $ip]);
    throw new ApiError(401, 'Неверный email или пароль');
}

$pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
$pdo->exec('DELETE FROM auth_tokens WHERE expires_at < NOW()');      // уборка старых токенов

respond([
    'token' => issue_token((int)$u['id']),
    'user'  => ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'role' => $u['role']],
]);
