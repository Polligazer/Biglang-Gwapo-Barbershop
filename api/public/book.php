<?php
// PUBLIC booking request: one barber, 1-6 services scheduled back-to-back, created as "pending".
// No login required; protected by CSRF, a honeypot field and a per-session throttle.
declare(strict_types=1);
require __DIR__ . '/../../lib/core.php';
require __DIR__ . '/../../lib/crud.php';
public_boot(true);

$b = body();
if (!empty($b['website'])) { // honeypot: bots fill hidden fields
    json_out(['ok' => true, 'ref' => '--------', 'items' => []]);
}
$_SESSION['bk'] = array_values(array_filter($_SESSION['bk'] ?? [], fn($t) => $t > time() - 3600));
if (count($_SESSION['bk']) >= 5) {
    throw new ApiError([], 429, 'Too many booking requests. Please try again later or call the atelier.');
}

$c = validate([
    'customer_name'  => ['t' => 'str', 'req' => 1, 'max' => 120],
    'customer_phone' => ['t' => 'str', 'req' => 1, 'max' => 30],
    'customer_email' => ['t' => 'email'],
    'barber_id'      => ['t' => 'fk', 'req' => 1],
    'appt_date'      => ['t' => 'date', 'req' => 1],
    'start_time'     => ['t' => 'time', 'req' => 1],
    'notes'          => ['t' => 'text', 'max' => 500],
], $b, true);
if (!preg_match('/^[0-9+()\-\s.]{7,30}$/', $c['customer_phone'])) {
    throw new ApiError(['customer_phone' => 'Enter a valid phone number']);
}
$ids = array_values(array_unique(array_map('intval', (array)($b['services'] ?? []))));
if (!$ids || count($ids) > 6 || in_array(0, $ids, true)) {
    throw new ApiError(['services' => 'Select 1 to 6 services']);
}
$today = date('Y-m-d');
if ($c['appt_date'] > date('Y-m-d', strtotime('+60 days'))) {
    throw new ApiError(['appt_date' => 'Bookings open up to 60 days ahead']);
}
if ($c['appt_date'] === $today && (int)substr($c['start_time'], 0, 2) * 60 + (int)substr($c['start_time'], 3, 2) < (int)date('G') * 60 + (int)date('i') + 30) {
    throw new ApiError(['start_time' => 'That time has passed. Please choose a later slot.']);
}

$r = resource('appointments');
$pdo = db();
$ref = strtoupper(bin2hex(random_bytes(4)));
$pdo->beginTransaction();
try {
    $start = $c['start_time'];
    foreach ($ids as $sid) {
        $row = [
            'customer_name' => $c['customer_name'], 'customer_email' => $c['customer_email'], 'customer_phone' => $c['customer_phone'],
            'service_id' => $sid, 'barber_id' => $c['barber_id'], 'appt_date' => $c['appt_date'], 'start_time' => $start,
            'status' => 'pending', 'notes' => $c['notes'], 'booking_ref' => $ref,
        ];
        $r['before']($pdo, $row, null);   // same hours + conflict rules the admin uses
        write_row($pdo, $r, $row, null);
        $start = substr($row['end_time'], 0, 5); // next service starts when this one ends
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
$_SESSION['bk'][] = time();

$st = $pdo->prepare('SELECT s.name AS service, a.start_time, a.end_time, a.appt_date, b.name AS barber, br.name AS branch
    FROM appointments a JOIN services s ON s.id = a.service_id JOIN barbers b ON b.id = a.barber_id JOIN branches br ON br.id = b.branch_id
    WHERE a.booking_ref = ? ORDER BY a.start_time');
$st->execute([$ref]);
json_out(['ok' => true, 'ref' => $ref, 'items' => $st->fetchAll()], 201);
