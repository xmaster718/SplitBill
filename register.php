<?php

require_once __DIR__ . "/auth.php";
redirect_authenticated_user();

if (!isset($_SESSION["registration_token"])) {
    $_SESSION["registration_token"] = bin2hex(random_bytes(32));
}

$username = "";
$email = "";
$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $usernameInput = $_POST["username"] ?? "";
    $emailInput = $_POST["email"] ?? "";
    $username = is_string($usernameInput) ? trim($usernameInput) : "";
    $email = is_string($emailInput) ? trim($emailInput) : "";
    $password = $_POST["password"] ?? "";
    $passwordConfirmation = $_POST["password_confirmation"] ?? "";
    $token = $_POST["registration_token"] ?? "";

    if (!is_string($username) || !preg_match('/\A.{1,50}\z/u', $username)) {
        $errors[] = "Имя пользователя должно содержать от 1 до 50 символов.";
    }

    if (!is_string($email) || !preg_match('/\A.{1,100}\z/u', $email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Введите корректный email длиной не более 100 символов.";
    }

    if (!is_string($password) || preg_match('/\A.{8,}\z/us', $password) !== 1) {
        $errors[] = "Пароль должен содержать не менее 8 символов.";
    }

    if (!is_string($passwordConfirmation) || $password !== $passwordConfirmation) {
        $errors[] = "Пароли не совпадают.";
    }

    if (!is_string($token) || !hash_equals($_SESSION["registration_token"], $token)) {
        $errors[] = "Форма устарела. Обновите страницу и попробуйте снова.";
    }

    if (!$errors) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $statement = $conn->prepare(
            "INSERT INTO users (username, email, password) VALUES (?, ?, ?)"
        );
        $statement->bind_param("sss", $username, $email, $passwordHash);

        try {
            $statement->execute();
            $success = true;
            $username = "";
            $email = "";
            $_SESSION["registration_token"] = bin2hex(random_bytes(32));
        } catch (mysqli_sql_exception $exception) {
            if ($exception->getCode() === 1062) {
                $errors[] = "Пользователь с таким именем или email уже зарегистрирован.";
            } else {
                throw $exception;
            }
        } finally {
            $statement->close();
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
    <title>Регистрация — SplitBill</title>
</head>
<body>
    <main>
        <h1>Регистрация</h1>

        <?php if ($success): ?>
            <p role="status">Регистрация прошла успешно. Теперь войдите в аккаунт.</p>
            <p><a href="login.php">Войти</a></p>
        <?php else: ?>

        <?php if ($errors): ?>
            <div role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escape($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="register.php">
            <input
                type="hidden"
                name="registration_token"
                value="<?= escape($_SESSION["registration_token"]) ?>"
            >

            <p>
                <label for="username">Имя пользователя</label><br>
                <input
                    type="text"
                    id="username"
                    name="username"
                    maxlength="50"
                    autocomplete="username"
                    value="<?= escape($username) ?>"
                    required
                >
            </p>

            <p>
                <label for="email">Email</label><br>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="100"
                    autocomplete="email"
                    value="<?= escape($email) ?>"
                    required
                >
            </p>

            <p>
                <label for="password">Пароль</label><br>
                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </p>

            <p>
                <label for="password_confirmation">Повторите пароль</label><br>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >
            </p>

            <button type="submit">Зарегистрироваться</button>
        </form>
        <?php endif; ?>

        <p><a href="login.php">Уже зарегистрированы? Войти</a></p>
        <p><a href="index.php">На главную</a></p>
    </main>
</body>
</html>
