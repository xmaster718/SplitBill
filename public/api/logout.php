<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

require_method('POST');
$token = bearer_token();
if ($token !== null) {
    db()->prepare('DELETE FROM auth_tokens WHERE token_hash = ?')->execute([hash('sha256', $token)]);
}
respond(['ok' => true]);
