<?php
/**
 * Authentication and access control.
 *  - passwords: password_hash() / password_verify() (bcrypt via PASSWORD_DEFAULT)
 *  - sessions: id regenerated on login, destroyed on logout, idle timeout
 *  - login throttling: limits repeated failed attempts
 *  - roles: 'user' manages own products, 'admin' manages all products
 */
declare(strict_types=1);

const LOGIN_MAX_FAILS_PER_ACCOUNT = 5;   // per email + IP, per 15 minutes
const LOGIN_MAX_FAILS_PER_IP      = 20;  // per IP, per 15 minutes

/** The logged-in user (id, name, email, role) or null. Result is cached for the request. */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;

    if (!empty($GLOBALS['db_failed']) || empty($_SESSION['user_id'])) {
        return null;
    }

    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - (int) $_SESSION['last_activity']) > SESSION_IDLE_SECONDS) {
        end_session();
        flash('info', 'You were logged out after 30 minutes of inactivity. Please log in again.');
        return null;
    }
    $_SESSION['last_activity'] = $now;

    // Always re-read the user from the database so deleted accounts / changed roles take effect immediately.
    $stmt = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $row = $stmt->fetch();
    if (!$row) {
        end_session();
        return null;
    }

    $user = $row;
    return $user;
}

function is_admin(?array $user): bool
{
    return $user !== null && ($user['role'] ?? '') === 'admin';
}

/** Fully destroy the session (used by logout and by the idle timeout). */
function end_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
    if (!headers_sent()) {
        session_start();
        session_regenerate_id(true);
    }
}

/** Start an authenticated session. The session id is regenerated to prevent session fixation. */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']); // fresh CSRF token for the new session
}

/** Pages that need an account. Sends guests to the login page and remembers where they wanted to go. */
function require_login(): array
{
    $user = current_user();
    if ($user !== null) {
        return $user;
    }
    if (empty($_SESSION['flash'])) {
        flash('info', 'Please log in to continue.');
    }
    $next = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) ;
    if (!empty($_SERVER['QUERY_STRING'])) {
        $next .= '?' . $_SERVER['QUERY_STRING'];
    }
    redirect('login.php?next=' . rawurlencode($next));
}

/** Pages only for guests (login/register). Logged-in users are sent to their dashboard. */
function require_guest(): void
{
    if (current_user() !== null) {
        redirect('dashboard.php');
    }
}

/**
 * Only allow redirecting to a known internal page after login (prevents open-redirect attacks).
 * Accepts things like "dashboard.php" or "product_form.php?id=4", nothing else.
 */
function safe_next(string $next): string
{
    if (preg_match('/^(dashboard|product_form|product|catalogue)\.php(\?[A-Za-z0-9=&_%.\-]*)?$/', $next) === 1) {
        return $next;
    }
    return 'dashboard.php';
}

/** Owners can manage their own products; admins can manage everything. */
function can_manage_product(?array $user, array $product): bool
{
    if ($user === null) {
        return false;
    }
    return is_admin($user) || (int) $product['user_id'] === (int) $user['id'];
}

// ---- Login throttling -----------------------------------------------------------

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

function login_is_blocked(string $email): bool
{
    $ip = client_ip();

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE email = ? AND ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    );
    $stmt->execute([$email, $ip]);
    if ((int) $stmt->fetchColumn() >= LOGIN_MAX_FAILS_PER_ACCOUNT) {
        return true;
    }

    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
    );
    $stmt->execute([$ip]);
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_FAILS_PER_IP;
}

function record_failed_login(string $email): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)');
    $stmt->execute([$email, client_ip()]);
    // housekeeping: forget attempts older than a day
    db()->exec('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
}

function clear_failed_logins(string $email): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE email = ? AND ip_address = ?');
    $stmt->execute([$email, client_ip()]);
}

/**
 * Check credentials. Returns the user row on success, null on failure.
 * The same work is done whether or not the email exists, so response time does not reveal which emails are registered.
 */
function verify_credentials(string $email, string $password): ?array
{
    $stmt = db()->prepare('SELECT id, name, email, role, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row) {
        password_verify($password, password_hash('not-a-real-password', PASSWORD_DEFAULT)); // equalise timing
        return null;
    }
    if (!password_verify($password, $row['password_hash'])) {
        return null;
    }

    // Upgrade the stored hash if PHP's default algorithm/cost has improved since it was created.
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $upd = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }

    unset($row['password_hash']);
    return $row;
}
