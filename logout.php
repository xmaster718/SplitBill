<?php

session_start();

$token = $_POST["logout_token"] ?? "";

if (
    $_SERVER["REQUEST_METHOD"] !== "POST"
    || !is_string($token)
    || !isset($_SESSION["logout_token"])
    || !hash_equals($_SESSION["logout_token"], $token)
) {
    http_response_code(403);
    exit("Недействительный запрос на выход.");
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $cookieParameters = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        [
            "expires" => time() - 42000,
            "path" => $cookieParameters["path"],
            "domain" => $cookieParameters["domain"],
            "secure" => $cookieParameters["secure"],
            "httponly" => $cookieParameters["httponly"],
            "samesite" => $cookieParameters["samesite"],
        ]
    );
}

session_destroy();

header("Location: index.php");
exit;
