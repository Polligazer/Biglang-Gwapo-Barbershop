<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
api_boot(true, false);

$b = body();
$action = $b['action'] ?? '';

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    json_out(['ok' => true]);
}
if ($action !== 'login') {
    throw new ApiError([], 400, 'Unknown action');
}
if (($_SESSION['lock'] ?? 0) > time()) {
    throw new ApiError([], 429, 'Too many attempts. Please wait a minute.');
}

$u = trim((string)($b['username'] ?? ''));
$p = (string)($b['password'] ?? '');
if ($u === '' || $p === '') {
    throw new ApiError(['username' => $u === '' ? 'Required' : '', 'password' => $p === '' ? 'Required' : '']);
}

$st = db()->prepare('SELECT id, name, password_hash FROM admins WHERE username = ?');
$st->execute([$u]);
$row = $st->fetch();
// Always run one hash verification so response time doesn't reveal valid usernames.
$ok = password_verify($p, $row['password_hash'] ?? '$2y$10$FJS.vV4EdkUQfVQpxAMEvexmodggQHN3prTc9Wy6MCkIWHMxCP9Xa') && $row;

if (!$ok) {
    $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
    if ($_SESSION['fails'] >= 5) {
        $_SESSION['lock'] = time() + 60;
        $_SESSION['fails'] = 0;
    }
    throw new ApiError([], 401, 'Invalid username or password');
}
session_regenerate_id(true);
unset($_SESSION['fails'], $_SESSION['lock']);
$_SESSION['admin'] = ['id' => (int)$row['id'], 'name' => $row['name']];
json_out(['ok' => true]);
