<?php
declare(strict_types=1);

// ⚠️ Ces identifiants conviennent pour un environnement de développement local (XAMPP/WAMP/MAMP).
// En production, utilisez un utilisateur MySQL dédié avec mot de passe et privilèges restreints,
// et sortez idéalement ces valeurs de ce fichier (variables d'environnement, fichier .env hors du dossier web).
const DB_HOST = 'localhost';
const DB_NAME = 'portfolio';
const DB_USER = 'root';
const DB_PASS = '';

const ADMIN_EMAIL = 'felicienjosephm@gmail.com';
const ADMIN_PASSWORD_HASH = '$2b$10$fYJf3auQEfhLAwEGVDeujOKa8vpzIsnxCi5wEEsYRsHRKEcKrA3.S';
const MAIL_FROM = 'felicienjosephm@gmail.com';
const SITE_NAME = 'Portfolio Felicien Joseph';

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_SECONDS = 300;

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfCheck(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
?>