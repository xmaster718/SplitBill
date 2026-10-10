<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Проверка «жив ли сервис» — пригодится для мониторинга и Docker/CI.
require_method('GET');
try {
    db()->query('SELECT 1');
    respond(['status' => 'ok', 'db' => 'up', 'time' => date('c')]);
} catch (ApiError $e) {
    respond(['status' => 'degraded', 'db' => 'down', 'time' => date('c')], 503);
}
