<?php
/**
 * General helper functions used across the application.
 */
declare(strict_types=1);

/** Read a value from the config array using dot notation, e.g. config('db.host'). */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['app_config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Escape text for safe output in HTML (prevents XSS). Use on EVERY user-supplied value. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string) config('app.base_url', ''), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Render a friendly error page and stop. */
function abort(int $code, string $message): never
{
    http_response_code($code);
    $pageTitle    = 'Error ' . $code;
    $activeNav    = '';
    $errorCode    = $code;
    $errorMessage = $message;
    include ROOT_PATH . '/includes/error_view.php';
    exit;
}

// ---- CSRF protection --------------------------------------------------------

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

/** Call at the top of every POST handler. Stops the request if the token is missing or wrong. */
function csrf_check(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    $known = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sent) || $known === '' || !hash_equals($known, $sent)) {
        abort(419, 'Your form expired or was invalid. Go back, refresh the page and try again.');
    }
}

// ---- Flash messages (one-time notices shown after a redirect) ----------------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ---- Formatting ---------------------------------------------------------------

function format_price(float|string $amount): string
{
    return (string) config('app.currency', '৳') . number_format((float) $amount, 2);
}

function format_date(string $timestamp): string
{
    $time = strtotime($timestamp);
    return $time ? date('j M Y', $time) : '';
}

/** Read a trimmed string from $_GET / $_POST without warnings. Arrays are rejected. */
function input_string(array $source, string $key, int $maxLength = 255): string
{
    $value = $source[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    return mb_substr($value, 0, $maxLength);
}

// ---- Pagination ----------------------------------------------------------------

/** @return array{page:int,pages:int,offset:int} */
function paginate(int $total, int $perPage, int $requestedPage): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, $requestedPage), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

/** Build a link to another page of results while keeping the current filters. */
function page_url(int $page): string
{
    $query = $_GET;
    unset($query['page']);
    if ($page > 1) {
        $query['page'] = $page;
    }
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    return url($script) . ($query ? '?' . http_build_query($query) : '');
}

// ---- Form helpers ---------------------------------------------------------------

/** CSS classes for a form field wrapper; adds the error style when this field failed validation. */
function field_class(array $errors, string $key, string $extra = ''): string
{
    return trim('field ' . $extra . (isset($errors[$key]) ? ' field--error' : ''));
}

/** The message slot under a field. Always printed so JavaScript can fill it for live validation. */
function field_error(array $errors, string $key): string
{
    return '<p class="field__error" id="error-' . e($key) . '" aria-live="polite">' . e($errors[$key] ?? '') . '</p>';
}
