<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
api_boot();
require_global_admin();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;
$pdo = db();
$total = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
$st = $pdo->prepare(
    'SELECT l.id, l.action, l.resource, l.record_id, l.details, l.ip_address, l.created_at,
            COALESCE(a.name, \'Deleted admin\') AS admin_name
     FROM audit_logs l LEFT JOIN admins a ON a.id = l.admin_id
     ORDER BY l.created_at DESC, l.id DESC LIMIT ? OFFSET ?'
);
$st->bindValue(1, $limit, PDO::PARAM_INT);
$st->bindValue(2, $offset, PDO::PARAM_INT);
$st->execute();
json_out(['ok' => true, 'rows' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'limit' => $limit]);
