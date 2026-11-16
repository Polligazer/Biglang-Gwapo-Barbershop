<?php
// PUBLIC booking lookup, rescheduling, and cancellation. Access requires the booking reference,
// customer phone number, and the private token returned when the booking was made.
declare(strict_types=1);
require __DIR__ . '/../../lib/core.php';
public_boot($_SERVER['REQUEST_METHOD'] === 'POST');

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? body() : $_GET;
$action = (string)($input['action'] ?? 'lookup');
$ref = strtoupper(trim((string)($input['ref'] ?? '')));
$phone = trim((string)($input['phone'] ?? ''));
$token = trim((string)($input['token'] ?? ''));
if (!preg_match('/^[A-F0-9]{8}$/', $ref) || $phone === '') {
    throw new ApiError([], 400, 'Enter a valid booking reference and phone number');
}

$pdo = db();
$st = $pdo->prepare(
    'SELECT a.id, a.status, a.customer_name, a.customer_email, a.customer_phone, a.notes,
            a.service_id, a.barber_id, b.branch_id, a.appt_date, a.start_time, a.end_time, a.booking_token,
            s.name AS service, b.name AS barber, br.name AS branch
     FROM appointments a
     JOIN services s ON s.id = a.service_id
     JOIN barbers b ON b.id = a.barber_id
     JOIN branches br ON br.id = b.branch_id
     WHERE a.booking_ref = ? AND a.customer_phone = ?
     ORDER BY a.appt_date, a.start_time'
);
$st->execute([$ref, $phone]);
$rows = $st->fetchAll();
$storedToken = (string)($rows[0]['booking_token'] ?? '');
if (!$rows || $storedToken === '' || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($token === '' || !hash_equals($storedToken, $token)))) {
    throw new ApiError([], 404, 'Booking not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $active = array_filter($rows, static fn(array $row): bool => in_array($row['status'], ['pending', 'confirmed'], true));
    if (!$active) {
        throw new ApiError([], 409, $action === 'reschedule' ? 'This booking can no longer be rescheduled' : 'This booking can no longer be cancelled');
    }
    if ($action === 'reschedule') {
        $now = new DateTimeImmutable('now');
        $currentStart = new DateTimeImmutable($rows[0]['appt_date'] . ' ' . $rows[0]['start_time']);
        if ($currentStart <= $now->modify('+20 minutes')) {
            throw new ApiError([], 409, 'Rescheduling is only available at least 20 minutes before the appointment');
        }

        $new = validate([
            'barber_id' => ['t' => 'fk', 'req' => 1],
            'appt_date' => ['t' => 'date', 'req' => 1],
            'start_time' => ['t' => 'time', 'req' => 1],
        ], $input, true);
        $serviceIds = array_values(array_unique(array_map('intval', (array)($input['services'] ?? []))));
        if (!$serviceIds || count($serviceIds) > 6 || in_array(0, $serviceIds, true)) {
            throw new ApiError(['services' => 'Select 1 to 6 services']);
        }
        if ($new['appt_date'] < date('Y-m-d') || $new['appt_date'] > date('Y-m-d', strtotime('+60 days'))) {
            throw new ApiError(['appt_date' => 'Choose a date within the next 60 days']);
        }
        $newStart = new DateTimeImmutable($new['appt_date'] . ' ' . $new['start_time']);
        if ($newStart < $now->modify('+20 minutes')) {
            throw new ApiError(['start_time' => 'Choose a time at least 20 minutes from now']);
        }

        $pdo->beginTransaction();
        try {
            $barber = $pdo->prepare('SELECT id FROM barbers WHERE id = ? AND active = 1 FOR UPDATE');
            $barber->execute([$new['barber_id']]);
            if (!$barber->fetchColumn()) {
                throw new ApiError(['barber_id' => 'Barber not available']);
            }

            $marks = implode(',', array_fill(0, count($serviceIds), '?'));
            $services = $pdo->prepare("SELECT id, duration_min FROM services WHERE active = 1 AND id IN ($marks)");
            $services->execute($serviceIds);
            $serviceRows = $services->fetchAll();
            if (count($serviceRows) !== count($serviceIds)) {
                throw new ApiError(['services' => 'A selected service is unavailable']);
            }
            $durations = [];
            $totalDuration = 0;
            foreach ($serviceRows as $service) {
                $durations[(int)$service['id']] = (int)$service['duration_min'];
                $totalDuration += (int)$service['duration_min'];
            }
            [$hour, $minute] = array_map('intval', explode(':', $new['start_time']));
            $startMinutes = $hour * 60 + $minute;
            $endMinutes = $startMinutes + $totalDuration;
            if ($startMinutes < 540 || $endMinutes > 1080) {
                throw new ApiError(['start_time' => 'Service must fit within studio hours (09:00 - 18:00)']);
            }

            $conflict = $pdo->prepare(
                "SELECT 1 FROM appointments
                 WHERE barber_id = ? AND appt_date = ? AND status IN ('pending','confirmed')
                   AND booking_ref <> ? AND start_time < ? AND end_time > ?
                 LIMIT 1"
            );
            $endTime = sprintf('%02d:%02d:00', intdiv($endMinutes, 60), $endMinutes % 60);
            $startTime = $new['start_time'] . ':00';
            $conflict->execute([$new['barber_id'], $new['appt_date'], $ref, $endTime, $startTime]);
            if ($conflict->fetchColumn()) {
                throw new ApiError(['start_time' => 'That time is no longer available'], 409, 'Time slot conflict');
            }

            $existing = array_values($active);
            $update = $pdo->prepare('UPDATE appointments SET service_id = ?, barber_id = ?, appt_date = ?, start_time = ?, end_time = ? WHERE id = ?');
            $insert = $pdo->prepare(
                'INSERT INTO appointments (customer_name, customer_email, customer_phone, service_id, barber_id, appt_date, start_time, end_time, status, notes, booking_ref, booking_token)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $cursor = $startMinutes;
            foreach ($serviceIds as $index => $serviceId) {
                $serviceEnd = $cursor + $durations[$serviceId];
                $itemStart = sprintf('%02d:%02d:00', intdiv($cursor, 60), $cursor % 60);
                $itemEnd = sprintf('%02d:%02d:00', intdiv($serviceEnd, 60), $serviceEnd % 60);
                if (isset($existing[$index])) {
                    $update->execute([$serviceId, $new['barber_id'], $new['appt_date'], $itemStart, $itemEnd, $existing[$index]['id']]);
                } else {
                    $insert->execute([
                        $rows[0]['customer_name'], $rows[0]['customer_email'], $phone, $serviceId, $new['barber_id'],
                        $new['appt_date'], $itemStart, $itemEnd, $rows[0]['status'], $rows[0]['notes'] ?? null, $ref, $storedToken,
                    ]);
                }
                $cursor = $serviceEnd;
            }
            if (count($existing) > count($serviceIds)) {
                $remove = $pdo->prepare('DELETE FROM appointments WHERE id = ?');
                foreach (array_slice($existing, count($serviceIds)) as $extra) {
                    $remove->execute([$extra['id']]);
                }
            }
            audit_log($pdo, 'reschedule', 'appointments', (int)$rows[0]['id'], [
                'booking_ref' => $ref, 'barber_id' => $new['barber_id'], 'appt_date' => $new['appt_date'], 'start_time' => $startTime,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        json_out(['ok' => true, 'status' => $rows[0]['status'], 'ref' => $ref]);
    }
    if ($action !== 'cancel') {
        throw new ApiError([], 400, 'Unknown booking action');
    }
    $cancel = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE booking_ref = ? AND customer_phone = ? AND status IN ('pending','confirmed')");
    $cancel->execute([$ref, $phone]);
    json_out(['ok' => true, 'status' => 'cancelled']);
}

foreach ($rows as &$row) {
    unset($row['id'], $row['booking_token'], $row['customer_name'], $row['customer_email'], $row['customer_phone'], $row['notes']);
}
unset($row);
json_out(['ok' => true, 'ref' => $ref, 'status' => $rows[0]['status'], 'items' => $rows]);
