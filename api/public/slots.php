<?php
// PUBLIC: open start times for one barber on one date, sized to the chosen services. No login.
declare(strict_types=1);
require __DIR__ . '/../../lib/core.php';
public_boot();

$v = validate(['barber_id' => ['t' => 'fk', 'req' => 1], 'date' => ['t' => 'date', 'req' => 1]], $_GET, true);
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_GET['services'] ?? ''))))));
if (!$ids || count($ids) > 6) {
    throw new ApiError(['services' => 'Select 1 to 6 services']);
}
$today = date('Y-m-d');
if ($v['date'] < $today || $v['date'] > date('Y-m-d', strtotime('+60 days'))) {
    throw new ApiError(['date' => 'Choose a date within the next 60 days']);
}

$pdo = db();
$st = $pdo->prepare('SELECT COUNT(*) n, COALESCE(SUM(duration_min), 0) d FROM services WHERE active = 1 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
$st->execute($ids);
$svc = $st->fetch();
if ((int)$svc['n'] !== count($ids)) {
    throw new ApiError(['services' => 'A selected service is unavailable']);
}
$dur = (int)$svc['d'];

$barberCheck = $pdo->prepare('SELECT b.id FROM barbers b JOIN branches br ON br.id = b.branch_id WHERE b.id = ? AND b.active = 1 AND br.active = 1');
$barberCheck->execute([$v['barber_id']]);
if (!$barberCheck->fetchColumn()) {
    throw new ApiError(['barber_id' => 'Barber not available']);
}
$st = $pdo->prepare("SELECT start_time, end_time FROM appointments WHERE barber_id = ? AND appt_date = ? AND status IN ('pending','confirmed')");
$st->execute([$v['barber_id'], $v['date']]);
$busy = array_map(fn($r) => [intdiv((int)substr($r['start_time'], 0, 2) * 60 + (int)substr($r['start_time'], 3, 2), 1),
                              (int)substr($r['end_time'], 0, 2) * 60 + (int)substr($r['end_time'], 3, 2)], $st->fetchAll());
$st = $pdo->prepare(
    'SELECT start_time, end_time FROM barber_unavailability
     WHERE barber_id = ? AND start_date <= ? AND end_date >= ?'
);
$st->execute([$v['barber_id'], $v['date'], $v['date']]);
$unavailable = $st->fetchAll();

$earliest = $v['date'] === $today ? ((int)date('G') * 60 + (int)date('i') + 30) : 0; // 30-minute lead time
$slots = [];
for ($s = 540; $s + $dur <= 1080; $s += 15) {
    if ($s < $earliest) {
        continue;
    }
    foreach ($unavailable as $block) {
        if ($block['start_time'] === null || ($block['start_time'] < sprintf('%02d:%02d:00', intdiv($s + $dur, 60), ($s + $dur) % 60)
            && $block['end_time'] > sprintf('%02d:%02d:00', intdiv($s, 60), $s % 60))) {
            continue 2;
        }
    }
    foreach ($busy as [$bs, $be]) {
        if ($s < $be && $s + $dur > $bs) {
            continue 2;
        }
    }
    $slots[] = ['start' => sprintf('%02d:%02d', intdiv($s, 60), $s % 60), 'end' => sprintf('%02d:%02d', intdiv($s + $dur, 60), ($s + $dur) % 60)];
}
json_out(['ok' => true, 'duration' => $dur, 'slots' => $slots]);
