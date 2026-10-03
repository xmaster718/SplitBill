<?php

require_once __DIR__ . "/auth.php";
redirect_authenticated_user();

if (!isset($_SESSION["login_token"])) {
    $_SESSION["login_token"] = bin2hex(random_bytes(32));
}

$login = "";
$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $loginInput = $_POST["login"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $tokenInput = $_POST["login_token"] ?? "";
    $login = is_string($loginInput) ? trim($loginInput) : "";

    if (
        !is_string($loginInput)
        || $login === ""
        || preg_match('/\A.{1,100}\z/us', $login) !== 1
    ) {
        $error = "Введите имя пользователя или email.";
    } elseif (!is_string($passwordInput) || $passwordInput === "") {
        $error = "Введите пароль.";
    } elseif (
        !is_string($tokenInput)
        || !hash_equals($_SESSION["login_token"], $tokenInput)
    ) {
        $error = "Форма устарела. Обновите страницу и попробуйте снова.";
    } else {
        $statement = $conn->prepare(
            "SELECT id, username, email, password FROM users WHERE username = ? OR email = ? LIMIT 1"
        );
        $statement->bind_param("ss", $login, $login);
        $statement->execute();
        $result = $statement->get_result();
        $user = $result->fetch_assoc();
        $statement->close();

        if ($user && password_verify($passwordInput, $user["password"])) {
            session_regenerate_id(true);
            $_SESSION["user_id"] = (int) $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["login_token"] = bin2hex(random_bytes(32));
            $_SESSION["logout_token"] = bin2hex(random_bytes(32));
            header("Location: profile.php");
            exit;
        } else {
            $error = "Неверное имя пользователя/email или пароль.";
        }
    }
}

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
    <title>Вход — SplitBill</title>
</head>
<body>
    <main>
        <h1>Вход</h1>

        <?php if ($error !== ""): ?>
            <p role="alert"><?= escape($error) ?></p>
        <?php endif; ?>

        <form method="post" action="login.php">
            <input
                type="hidden"
                name="login_token"
                value="<?= escape($_SESSION["login_token"]) ?>"
            >

            <p>
                <label for="login">Имя пользователя или email</label><br>
                <input
                    type="text"
                    id="login"
                    name="login"
                    maxlength="100"
                    autocomplete="username"
                    value="<?= escape($login) ?>"
                    required
                >
            </p>

            <p>
                <label for="password">Пароль</label><br>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </p>

            <button type="submit">Войти</button>
        </form>

        <p><a href="register.php">Зарегистрироваться</a></p>

        <p><a href="index.php">На главную</a></p>
    </main>
</body>
</html>
