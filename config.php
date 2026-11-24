<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

/* ========== Banco de dados ========== */
const DB_HOST    = '127.0.0.1';
const DB_NAME    = 'sdd_login';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

/* ========== Regras de negócio (critérios de aceitação) ========== */
const MAX_FAILED_ATTEMPTS = 3;   // critério 4: 3 tentativas consecutivas
const BLOCK_MINUTES       = 15;  // critério 4: bloqueio de 15 minutos

/* ========== Sessão ========== */
// Timeout de inatividade. O guia não define valor; ajuste conforme a política.
const SESSION_IDLE_MINUTES = 30;

/* ========== HTTPS ========== */
// Em produção: true. Em ambiente local sem TLS: false (apenas desenvolvimento).
const ENFORCE_HTTPS = true;

/* ========== Mensagens ========== */
const MSG_INVALID          = 'pelo amor de Deus, ponha algo válido';
const MSG_SESSION_EXPIRED  = 'Sua sessão expirou. Faça login novamente.';
const MSG_BLOCKED_PREFIX   = 'Muitas tentativas sem êxito. Por segurança, a funcionalidade de login está bloqueada por 15 minutos para este usuário.';

/* ========== Infra ========== */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if ($proto === 'https') {
            return true;
        }
    }
    return false;
}

/** Critério 5: senhas só trafegam por HTTPS. */
function enforce_https(): void
{
    if (!ENFORCE_HTTPS || is_https()) {
        return;
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('SDDLOGIN');
    session_start();
}

/**
 * Tenta autenticar.
 * @return array{ok:bool,blocked:bool,invalid:bool,user_id?:int,remaining_seconds?:int}
 */
function attempt_login(string $email, string $password): array
{
    $pdo    = db();
    $email  = strtolower(trim($email));
    $now    = new DateTimeImmutable('now');

    // 1) Estado de tentativas/bloqueio para este usuário
    $stmt = $pdo->prepare('SELECT failed_attempts, blocked_until FROM login_attempts WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch() ?: null;

    $failed = $row ? (int)$row['failed_attempts'] : 0;

    if ($row && !empty($row['blocked_until'])) {
        $blockedUntil = new DateTimeImmutable($row['blocked_until']);
        if ($blockedUntil > $now) {
            return [
                'ok' => false, 'blocked' => true, 'invalid' => false,
                'remaining_seconds' => $blockedUntil->getTimestamp() - $now->getTimestamp(),
            ];
        }
        // Bloqueio expirou → zera
        $pdo->prepare('UPDATE login_attempts SET failed_attempts = 0, blocked_until = NULL WHERE email = ?')
            ->execute([$email]);
        $failed = 0;
    }

    // 2) Verifica credenciais (hash seguro — critério 5)
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch() ?: null;

    $valid = $user !== null && password_verify($password, $user['password_hash']);

    if ($valid) {
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);
        }
        // Login bem-sucedido → reset de tentativas consecutivas
        $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
        return ['ok' => true, 'blocked' => false, 'invalid' => false, 'user_id' => (int)$user['id']];
    }

    // 3) Falha → incrementa e, se atingir o limite, bloqueia
    $failed++;
    if ($failed >= MAX_FAILED_ATTEMPTS) {
        $blockedUntil = $now->modify('+' . BLOCK_MINUTES . ' minutes');
        $pdo->prepare(
            'INSERT INTO login_attempts (email, failed_attempts, blocked_until) VALUES (?, 0, ?)
             ON DUPLICATE KEY UPDATE failed_attempts = 0, blocked_until = ?'
        )->execute([
            $email,
            $blockedUntil->format('Y-m-d H:i:s'),
            $blockedUntil->format('Y-m-d H:i:s'),
        ]);
        return [
            'ok' => false, 'blocked' => true, 'invalid' => false,
            'remaining_seconds' => BLOCK_MINUTES * 60,
        ];
    }

    $pdo->prepare(
        'INSERT INTO login_attempts (email, failed_attempts, blocked_until) VALUES (?, ?, NULL)
         ON DUPLICATE KEY UPDATE failed_attempts = ?, blocked_until = NULL'
    )->execute([$email, $failed, $failed]);

    return ['ok' => false, 'blocked' => false, 'invalid' => true];
}

function blocked_message(int $remainingSeconds): string
{
    $minutes = max(1, (int)ceil($remainingSeconds / 60));
    return MSG_BLOCKED_PREFIX . ' Tente novamente em aproximadamente ' . $minutes . ' minuto(s).';
}
