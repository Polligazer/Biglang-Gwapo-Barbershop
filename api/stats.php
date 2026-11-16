<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
require __DIR__ . '/../lib/crud.php';
api_boot();
$pdo = db();
$today = date('Y-m-d');

$s = $pdo->prepare("SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN a.status IN ('confirmed','completed') THEN s.price END), 0) AS revenue
    FROM appointments a JOIN services s ON s.id = a.service_id
    WHERE a.appt_date = ? AND a.status <> 'cancelled'");
$s->execute([$today]);
$day = $s->fetch();

$pend = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE status = 'pending' AND appt_date >= ?");
$pend->execute([$today]);

json_out([
    'ok'       => true,
    'today'    => $today,
    'admin'    => $_SESSION['admin']['name'],
    'booked'   => (int)$day['total'],
    'revenue'  => (float)$day['revenue'],
    'pending'  => (int)$pend->fetchColumn(),
    'barbers'  => (int)$pdo->query('SELECT COUNT(*) FROM barbers WHERE active = 1')->fetchColumn(),
    'schedule' => fetch_rows(resource('appointments'), ['date' => $today, 'dir' => 'asc', 'limit' => 8])['rows'],
    'low'      => $pdo->query('SELECT id, sku, name, stock, min_threshold FROM inventory WHERE stock <= min_threshold ORDER BY CAST(stock AS SIGNED) - CAST(min_threshold AS SIGNED) LIMIT 5')->fetchAll(),
]);
