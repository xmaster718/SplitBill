<?php

require_once __DIR__ . "/auth.php";
$user = require_auth();

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль — SplitBill</title>
</head>
<body>
    <main>
        <h1>Профиль</h1>
        <p>Имя пользователя: <?= escape($user["username"]) ?></p>
        <p>Email: <?= escape($user["email"]) ?></p>

        <form method="post" action="logout.php">
            <input
                type="hidden"
                name="logout_token"
                value="<?= escape($_SESSION["logout_token"]) ?>"
            >
            <button type="submit">Выйти</button>
        </form>

        <p><a href="index.php">На главную</a></p>
    </main>
</body>
</html>
