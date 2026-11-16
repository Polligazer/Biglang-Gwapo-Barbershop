<?php
// Usage:
//   php seed_admin.php <username> <password> "<Display Name>"
//   php seed_admin.php <username> --hash <bcrypt hash> "<Display Name>"
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/db.php';
[, $user, $pass, $name] = $argv + [null, null, null, 'Administrator'];
if (!$user || !$pass) {
    fwrite(STDERR, "Usage: php seed_admin.php <username> <password (10+ chars)> \"<Name>\"\n");
    fwrite(STDERR, "   or: php seed_admin.php <username> --hash <bcrypt hash> \"<Name>\"\n");
    exit(1);
}

if ($pass === '--hash') {
    $hash = $argv[3] ?? '';
    $name = $argv[4] ?? 'Administrator';
    if (password_get_info($hash)['algo'] === 0) {
        fwrite(STDERR, "The supplied value is not a valid password hash.\n");
        exit(1);
    }
    $passwordHash = $hash;
} else {
    if (strlen($pass) < 10) {
        fwrite(STDERR, "Password must be at least 10 characters.\n");
        exit(1);
    }
    $passwordHash = password_hash($pass, PASSWORD_DEFAULT);
}

db()->prepare(
    'INSERT INTO admins (username, name, password_hash, role, branch_id) VALUES (?, ?, ?, \'global_admin\', NULL)
     ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = \'global_admin\', branch_id = NULL'
)->execute([$user, $name, $passwordHash]);
echo "Admin '$user' saved.\n";
