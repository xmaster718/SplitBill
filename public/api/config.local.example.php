<?php
// Скопируйте этот файл в config.local.php и впишите данные своей БД.
// Файл config.local.php НЕ попадает в git (он в .gitignore) — пароли в репозиторий не кладём.
return [
    'host' => '127.0.0.1',
    'port' => '3306',
    'name' => 'splitbill',
    'user' => 'root',    // в XAMPP по умолчанию root без пароля
    'pass' => '',
];
