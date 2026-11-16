<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';

/** Sessions start lazily: public read endpoints never touch one. */
function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);
        session_start();
    }
}

final class ApiError extends Exception
{
    public function __construct(public array $errors = [], public int $status = 422, string $message = 'Validation failed')
    {
        parent::__construct($message);
    }
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

function audit_log(PDO $pdo, string $action, string $resource, ?int $recordId, array $details = []): void
{
    $json = json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare('INSERT INTO audit_logs (admin_id, action, resource, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([
            isset($_SESSION['admin']['id']) ? (int)$_SESSION['admin']['id'] : null,
            $action,
            $resource,
            $recordId,
            $json,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
}

function csrf_token(): string
{
    start_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function install_error_handler(): void
{
    set_exception_handler(function (Throwable $e): void {
        if ($e instanceof ApiError) {
            json_out(['ok' => false, 'error' => $e->getMessage(), 'errors' => (object)$e->errors], $e->status);
        }
        if ($e instanceof PDOException) {
            $code = (int)($e->errorInfo[1] ?? 0);
            if ($code === 1062) {
                json_out(['ok' => false, 'error' => 'That value already exists', 'errors' => ['sku' => 'Already exists']], 409);
            }
            if ($code === 1451) {
                json_out(['ok' => false, 'error' => 'Record is in use and cannot be deleted. Hide it instead.', 'errors' => new stdClass()], 409);
            }
        }
        error_log((string)$e);
        json_out(['ok' => false, 'error' => 'Server error', 'errors' => new stdClass()], 500);
    });
}

/** Writes must be POST with a valid CSRF token (requires the session). */
function require_write_request(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new ApiError([], 405, 'POST required');
    }
    if (!hash_equals(csrf_token(), $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        throw new ApiError([], 403, 'Invalid CSRF token');
    }
}

/** PROTECTED endpoints (api/*.php): admin session required, plus POST + CSRF for writes. */
function api_boot(bool $write = false, bool $auth = true): void
{
    header('X-Content-Type-Options: nosniff');
    install_error_handler();
    start_session();
    if ($auth && empty($_SESSION['admin'])) {
        throw new ApiError([], 401, 'Authentication required');
    }

    function admin_branch_id(): ?int
    {
        if (empty($_SESSION['admin']) || ($_SESSION['admin']['role'] ?? 'global_admin') === 'global_admin') {
            return null;
        }
        return isset($_SESSION['admin']['branch_id']) ? (int)$_SESSION['admin']['branch_id'] : null;
    }

    function require_global_admin(): void
    {
        if (empty($_SESSION['admin']) || ($_SESSION['admin']['role'] ?? '') !== 'global_admin') {
            throw new ApiError([], 403, 'Global admin access required');
        }
    }
    if ($write) {
        require_write_request();
    }
}

/** PUBLIC endpoints (api/public/*.php): no login check. Reads need no session at all; public writes still need CSRF. */
function public_boot(bool $write = false): void
{
    header('X-Content-Type-Options: nosniff');
    install_error_handler();
    if ($write) {
        require_write_request();
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new ApiError([], 405, 'GET required');
    }
}

/** PROTECTED pages (admin/*.php): redirect visitors without an admin session to the login screen. */
function require_admin_page(): void
{
    start_session();
    if (empty($_SESSION['admin'])) {
        header('Location: login.php');
        exit;
    }
}

/** UTF-8 aware length that works with or without the mbstring extension. */
function str_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : (int)preg_match_all('/./us', $s);
}

function body(): array
{
    $d = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($d)) {
        throw new ApiError([], 400, 'Invalid JSON body');
    }
    return $d;
}

/**
 * Server-side validation driven by a field spec.
 * Types: str, text, email, int, fk, dec, date, time, enum, bool.
 */
function validate(array $spec, array $in, bool $creating): array
{
    $c = [];
    $e = [];
    foreach ($spec as $k => $r) {
        if (!empty($r['create_only']) && !$creating) {
            continue;
        }
        $v = $in[$k] ?? null;
        if (is_string($v)) {
            $v = trim($v);
        }
        if ($v === null || $v === '') {
            if (!empty($r['req'])) {
                $e[$k] = 'Required';
            } else {
                $c[$k] = $r['def'] ?? null;
            }
            continue;
        }
        if (!is_scalar($v)) {
            $e[$k] = 'Invalid value';
            continue;
        }
        $t = $r['t'];
        switch ($t) {
            case 'str':
            case 'text':
            case 'email':
                $max = $r['max'] ?? ($t === 'text' ? 1000 : ($t === 'email' ? 150 : 255));
                $v = (string)$v;
                if (str_len($v) > $max) {
                    $e[$k] = "Max $max characters";
                } elseif ($t === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    $e[$k] = 'Invalid email address';
                } else {
                    $c[$k] = $v;
                }
                break;
            case 'int':
            case 'fk':
                $n = filter_var($v, FILTER_VALIDATE_INT);
                $min = $r['min'] ?? ($t === 'fk' ? 1 : 0);
                $max = $r['max'] ?? PHP_INT_MAX;
                if ($n === false) {
                    $e[$k] = 'Must be a whole number';
                } elseif ($n < $min || $n > $max) {
                    $e[$k] = "Must be between $min and $max";
                } else {
                    $c[$k] = $n;
                }
                break;
            case 'dec':
                $min = $r['min'] ?? 0;
                $max = $r['max'] ?? 9999999;
                if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', (string)$v)) {
                    $e[$k] = 'Use a valid amount (max 2 decimals)';
                } elseif ((float)$v < $min || (float)$v > $max) {
                    $e[$k] = "Must be between $min and $max";
                } else {
                    $c[$k] = (string)$v;
                }
                break;
            case 'date':
                $d = DateTime::createFromFormat('!Y-m-d', (string)$v);
                if (!$d || $d->format('Y-m-d') !== $v) {
                    $e[$k] = 'Invalid date';
                } else {
                    $c[$k] = $v;
                }
                break;
            case 'time':
                if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string)$v, $m)) {
                    $e[$k] = 'Invalid time (HH:MM)';
                } elseif ((int)$m[2] % 15 !== 0) {
                    $e[$k] = 'Use 15-minute steps';
                } else {
                    $c[$k] = $v;
                }
                break;
            case 'enum':
                if (!in_array($v, $r['in'], true)) {
                    $e[$k] = 'Invalid option';
                } else {
                    $c[$k] = $v;
                }
                break;
            case 'bool':
                $c[$k] = filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                break;
        }
    }
    if ($e) {
        throw new ApiError($e);
    }
    return $c;
}
