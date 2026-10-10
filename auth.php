<?php

require_once __DIR__ . "/config/db.php";

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function current_user(): ?array
{
    global $conn;

    if (!isset($_SESSION["user_id"]) || !is_int($_SESSION["user_id"]) || $_SESSION["user_id"] < 1) {
        return null;
    }

    $statement = $conn->prepare(
        "SELECT id, username, email FROM users WHERE id = ? LIMIT 1"
    );
    $statement->bind_param("i", $_SESSION["user_id"]);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$user) {
        unset(
            $_SESSION["user_id"],
            $_SESSION["username"],
            $_SESSION["email"],
            $_SESSION["logout_token"]
        );

        return null;
    }

    $_SESSION["username"] = $user["username"];
    $_SESSION["email"] = $user["email"];

    if (!isset($_SESSION["logout_token"])) {
        $_SESSION["logout_token"] = bin2hex(random_bytes(32));
    }

    return $user;
}

function require_auth(): array
{
    $user = current_user();

    if ($user === null) {
        header("Location: login.php");
        exit;
    }

    return $user;
}

function redirect_authenticated_user(): void
{
    if (current_user() !== null) {
        header("Location: profile.php");
        exit;
    }
}
