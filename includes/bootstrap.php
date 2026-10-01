<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kathmandu');

$configPath = dirname(__DIR__) . '/config.php';
if (!is_file($configPath)) {
    http_response_code(500);
    exit('Configuration is missing. Copy config.example.php to config.php and set the database credentials.');
}

$config = require $configPath;

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

function db(): PDO
{
    static $pdo;
    if (!$pdo) {
        global $config;
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
        $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+05:45'");
    }
    return $pdo;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $query = db()->prepare('SELECT u.id, u.name, u.email, u.role, t.id AS team_id, t.name AS team_name, t.verification_status FROM users u LEFT JOIN teams t ON t.owner_user_id = u.id WHERE u.id = ?');
    $query->execute([$_SESSION['user_id']]);
    $user = $query->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please sign in to continue.');
        redirect('login.php');
    }
    return $user;
}

function require_team(): array
{
    $user = require_auth();
    if (!$user['team_id']) {
        http_response_code(403);
        exit('A team account is required for this page.');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_auth();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Administrator access required.');
    }
    return $user;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        exit('Your session token expired. Refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}

function redirect(string $page): never
{
    header('Location: ' . $page);
    exit;
}

function status_label(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
}

function save_verification_upload(array $upload): ?string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($upload['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Choose a document smaller than 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Upload a JPG, PNG, or PDF document.');
    }
    $directory = dirname(__DIR__, 2) . '/khelmandu-private/verification';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The private upload directory could not be created.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($upload['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('The document could not be saved.');
    }
    return $filename;
}

function require_player(): array
{
    $user = require_auth();
    if ($user['role'] !== 'player') {
        http_response_code(403);
        exit('A player account is required for this page.');
    }
    return $user;
}