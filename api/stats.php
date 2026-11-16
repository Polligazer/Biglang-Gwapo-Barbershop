<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
require __DIR__ . '/../lib/crud.php';
api_boot();
$pdo = db();
$today = date('Y-m-d');
$branch = admin_branch_id();
$branchWhere = $branch === null ? '' : ' AND b.branch_id = ?';

$s = $pdo->prepare("SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN a.status IN ('confirmed','completed') THEN s.price END), 0) AS revenue
    FROM appointments a JOIN services s ON s.id = a.service_id JOIN barbers b ON b.id = a.barber_id
    WHERE a.appt_date = ? AND a.status <> 'cancelled'$branchWhere");
$params = [$today];
if ($branch !== null) {
    $params[] = $branch;
}
$s->execute($params);
$day = $s->fetch();

$pend = $pdo->prepare("SELECT COUNT(*) FROM appointments a JOIN barbers b ON b.id = a.barber_id WHERE a.status = 'pending' AND a.appt_date >= ?$branchWhere");
$pend->execute($params = $branch === null ? [$today] : [$today, $branch]);

json_out([
    'ok'       => true,
    'today'    => $today,
    'admin'    => $_SESSION['admin']['name'],
    'booked'   => (int)$day['total'],
    'revenue'  => (float)$day['revenue'],
    'pending'  => (int)$pend->fetchColumn(),
    'barbers'  => (int)($branch === null
        ? $pdo->query('SELECT COUNT(*) FROM barbers WHERE active = 1')->fetchColumn()
        : (function () use ($pdo, $branch): int {
            $q = $pdo->prepare('SELECT COUNT(*) FROM barbers WHERE active = 1 AND branch_id = ?');
            $q->execute([$branch]);
            return (int)$q->fetchColumn();
        })()),
    'schedule' => fetch_rows(resource('appointments'), ['date' => $today, 'dir' => 'asc', 'limit' => 8])['rows'],
    'low'      => (function () use ($pdo, $branch): array {
        $q = $pdo->prepare('SELECT i.id, i.sku, i.name, i.stock, i.min_threshold FROM inventory i WHERE i.stock <= i.min_threshold' . ($branch === null ? '' : ' AND i.branch_id = ?') . ' ORDER BY CAST(i.stock AS SIGNED) - CAST(i.min_threshold AS SIGNED) LIMIT 5');
        $q->execute($branch === null ? [] : [$branch]);
        return $q->fetchAll();
    })(),
    'reorders' => (function () use ($pdo, $branch): array {
        $q = $pdo->prepare("SELECT r.id, i.name, r.requested_qty, r.status, r.created_at FROM reorder_requests r JOIN inventory i ON i.id = r.inventory_id WHERE r.status IN ('open','ordered')" . ($branch === null ? '' : ' AND i.branch_id = ?') . ' ORDER BY r.created_at DESC LIMIT 5');
        $q->execute($branch === null ? [] : [$branch]);
        return $q->fetchAll();
    })(),
]);
