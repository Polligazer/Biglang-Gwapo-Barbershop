<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
api_boot();

$year = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT);
$month = filter_var($_GET['month'] ?? date('n'), FILTER_VALIDATE_INT);
if ($year === false || $month === false || $year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
    throw new ApiError([], 400, 'Invalid calendar month');
}

$first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
$start = $first->modify('monday this week');
$end = $start->modify('+41 days');
$pdo = db();
$branch = admin_branch_id();
$branchWhere = $branch === null ? '' : ' AND b.branch_id = ?';
$st = $pdo->prepare(
    'SELECT a.id, a.customer_name, a.appt_date, a.start_time, a.end_time, a.status,
            s.name AS service_name, b.name AS barber_name, br.name AS branch_name
     FROM appointments a
     JOIN services s ON s.id = a.service_id
     JOIN barbers b ON b.id = a.barber_id
     JOIN branches br ON br.id = b.branch_id
     WHERE a.appt_date BETWEEN ? AND ?' . $branchWhere . '
     ORDER BY a.appt_date, a.start_time, a.id'
);
$params = [$start->format('Y-m-d'), $end->format('Y-m-d')];
if ($branch !== null) {
    $params[] = $branch;
}
$st->execute($params);

json_out([
    'ok' => true,
    'year' => (int)$year,
    'month' => (int)$month,
    'start' => $start->format('Y-m-d'),
    'end' => $end->format('Y-m-d'),
    'rows' => $st->fetchAll(),
]);
