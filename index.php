<?php

require_once __DIR__ . "/auth.php";

$user = current_user();

$result = $conn->query("SELECT COUNT(*) AS total FROM users");
$row = $result->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SplitBill</title>
</head>
<body>

    <h1>SplitBill</h1>

    <?php if ($user !== null): ?>
        <p>
            Здравствуйте, <?= htmlspecialchars($user["username"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>.
            <a href="profile.php">Мой профиль</a>
        </p>
        <form method="post" action="logout.php">
            <input
                type="hidden"
                name="logout_token"
                value="<?= htmlspecialchars($_SESSION["logout_token"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") ?>"
            >
            <button type="submit">Выйти</button>
        </form>
    <?php else: ?>
        <p>
            <a href="register.php">Зарегистрироваться</a>
            |
            <a href="login.php">Войти</a>
        </p>
    <?php endif; ?>

    <p>
        Пользователей в системе: <?php echo $row["total"]; ?>
    </p>

</body>
</html>