<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

require_method('POST');
$in = json_body();

$name = str_field($in, 'name', 2, 60, 'Имя');
$email = normalize_email($in['email'] ?? null);
if ($email === null) {
    throw new ApiError(400, 'Введите корректный email');
}
$password = $in['password'] ?? null;
if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) {
    throw new ApiError(400, 'Пароль: от 8 до 72 символов');
}

$pdo = db();
$check = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
$check->execute([$email]);
if ($check->fetchColumn()) {
    throw new ApiError(409, 'Пользователь с таким email уже зарегистрирован');
}

try {
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);   // пароль хранится только в виде хэша
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {      // гонка: email заняли между проверкой и вставкой
        throw new ApiError(409, 'Пользователь с таким email уже зарегистрирован');
    }
    throw $e;
}

$id = (int)$pdo->lastInsertId();
log_event('info', 'Новый пользователь', ['user_id' => $id]);

respond([
    'token' => issue_token($id),
    'user'  => ['id' => $id, 'name' => $name, 'email' => $email, 'role' => 'user'],
], 201);
