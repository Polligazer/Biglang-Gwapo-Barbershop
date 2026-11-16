<?php
// Usage: php seed_admin.php <username> <password> "<Display Name>"
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/db.php';
[, $user, $pass, $name] = $argv + [null, null, null, 'Administrator'];
if (!$user || !$pass || strlen($pass) < 10) { fwrite(STDERR, "Usage: php seed_admin.php <username> <password (10+ chars)> \"<Name>\"\n"); exit(1); }
db()->prepare('INSERT INTO admins (username, name, password_hash) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash)')
    ->execute([$user, $name, password_hash($pass, PASSWORD_DEFAULT)]);
echo "Admin '$user' saved.\n";
