<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

require_method('GET');
respond(['user' => require_user()]);
