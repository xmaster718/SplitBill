<?php
declare(strict_types=1);

/*
 * Общий файл для всех эндпоинтов: настройки, подключение к БД, ответы в JSON,
 * авторизация по токену и проверка доступа к группам.
 */

require_once __DIR__ . '/lib.php';

date_default_timezone_set(getenv('APP_TZ') ?: 'Asia/Almaty');
ini_set('display_errors', '0');      // ошибки не показываем пользователю, а пишем в лог
error_reporting(E_ALL);

class ApiError extends Exception
{
    public int $status;

    public function __construct(int $status, string $message)
    {
        parent::__construct($message);
        $this->status = $status;
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// CORS нужен только если фронтенд лежит на другом домене (задайте переменную CORS_ORIGIN).
$corsOrigin = getenv('CORS_ORIGIN');
if ($corsOrigin) {
    header('Access-Control-Allow-Origin: ' . $corsOrigin);
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Vary: Origin');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function log_event(string $level, string $message, array $context = []): void
{
    error_log('[splitbill] ' . json_encode(
        ['level' => $level, 'msg' => $message, 'ctx' => $context],
        JSON_UNESCAPED_UNICODE
    ));
}

function respond($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

set_exception_handler(function (Throwable $e): void {
    if ($e instanceof ApiError) {
        respond(['error' => $e->getMessage()], $e->status);
    }
    log_event('error', $e->getMessage(), ['file' => basename($e->getFile()), 'line' => $e->getLine()]);
    respond(['error' => 'Внутренняя ошибка сервера'], 500);
});

/** Подключение к БД. Настройки берутся из переменных окружения или из config.local.php. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $local = is_file(__DIR__ . '/config.local.php') ? (require __DIR__ . '/config.local.php') : [];
    $get = function (string $env, string $key, string $default) use ($local): string {
        $v = getenv($env);
        if ($v !== false && $v !== '') {
            return $v;
        }
        return (string)($local[$key] ?? $default);
    };
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $get('DB_HOST', 'host', '127.0.0.1'),
        $get('DB_PORT', 'port', '3306'),
        $get('DB_NAME', 'name', 'splitbill')
    );
    try {
        $pdo = new PDO($dsn, $get('DB_USER', 'user', 'root'), $get('DB_PASS', 'pass', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // настоящие prepared statements → защита от SQL-инъекций
        ]);
    } catch (PDOException $e) {
        log_event('error', 'Не удалось подключиться к БД', ['error' => $e->getMessage()]);
        throw new ApiError(503, 'База данных недоступна. Проверьте настройки подключения.');
    }
    return $pdo;
}

// ---------- Разбор запроса и валидация ----------

function require_method(string ...$allowed): string
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        throw new ApiError(405, 'Метод не поддерживается');
    }
    return $method;
}

function json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new ApiError(400, 'Некорректный JSON в теле запроса');
    }
    return $data;
}

/** Строка: убираем управляющие символы и лишние пробелы, проверяем длину. */
function str_field(array $data, string $key, int $min, int $max, string $label): string
{
    $v = $data[$key] ?? null;
    if (!is_string($v)) {
        throw new ApiError(400, "Поле «{$label}» обязательно");
    }
    $v = preg_replace('/[\x00-\x1F\x7F]/u', '', $v) ?? '';
    $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
    $len = mb_strlen($v);
    if ($len < $min || $len > $max) {
        throw new ApiError(400, "Поле «{$label}»: от {$min} до {$max} символов");
    }
    return $v;
}

function int_value($v, string $label): int
{
    if (is_int($v) && $v > 0) {
        return $v;
    }
    if (is_string($v) && preg_match('/^\d{1,10}$/', $v) && (int)$v > 0) {
        return (int)$v;
    }
    throw new ApiError(400, "Некорректное значение: {$label}");
}

// ---------- Авторизация ----------

function bearer_token(): ?string
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp((string)$name, 'Authorization') === 0) {
                $h = (string)$value;
                break;
            }
        }
    }
    if (preg_match('/^Bearer\s+([A-Za-z0-9]{64})$/', $h, $m)) {
        return $m[1];
    }
    // запасной заголовок: некоторые хостинги вырезают Authorization
    $x = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    return preg_match('/^[A-Za-z0-9]{64}$/', $x) ? $x : null;
}

function issue_token(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $stmt = db()->prepare(
        'INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))'
    );
    $stmt->execute([$userId, hash('sha256', $token)]);
    return $token;
}

function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $token = bearer_token();
    if ($token === null) {
        return $cache = null;
    }
    $stmt = db()->prepare(
        'SELECT u.id, u.name, u.email, u.role
           FROM auth_tokens t JOIN users u ON u.id = t.user_id
          WHERE t.token_hash = ? AND t.expires_at > NOW()'
    );
    $stmt->execute([hash('sha256', $token)]);
    $u = $stmt->fetch();
    if (!$u) {
        return $cache = null;
    }
    $u['id'] = (int)$u['id'];
    return $cache = $u;
}

function require_user(): array
{
    $u = current_user();
    if ($u === null) {
        throw new ApiError(401, 'Требуется вход в аккаунт');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_user();
    if ($u['role'] !== 'admin') {
        throw new ApiError(403, 'Только для администратора');
    }
    return $u;
}

/** Загружает группу, если у пользователя есть к ней доступ. Иначе — 404. */
function load_group_for_user(int $groupId, array $user): array
{
    $stmt = db()->prepare(
        'SELECT g.id, g.name, g.owner_id, g.invite_code, g.created_at
           FROM split_groups g JOIN group_users gu ON gu.group_id = g.id AND gu.user_id = ?
          WHERE g.id = ?'
    );
    $stmt->execute([$user['id'], $groupId]);
    $g = $stmt->fetch();
    if (!$g) {
        throw new ApiError(404, 'Группа не найдена или у вас нет доступа');
    }
    $g['id'] = (int)$g['id'];
    $g['owner_id'] = (int)$g['owner_id'];
    $g['is_owner'] = $g['owner_id'] === $user['id'];
    return $g;
}

/** Подбирает свободное имя участника в группе: «Анна», «Анна (2)», ... */
function unique_member_name(int $groupId, string $name): string
{
    $stmt = db()->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND name = ?');
    $candidate = $name;
    for ($i = 2; $i < 100; $i++) {
        $stmt->execute([$groupId, $candidate]);
        if (!$stmt->fetchColumn()) {
            return $candidate;
        }
        $candidate = mb_substr($name, 0, 54) . " ({$i})";
    }
    throw new ApiError(409, 'Не удалось подобрать имя участника');
}
