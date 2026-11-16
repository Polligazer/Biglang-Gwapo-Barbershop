<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
api_boot(true);

$b = body();
$ids = array_values(array_unique(array_filter(array_map('intval', (array)($b['ids'] ?? [])), static fn(int $id): bool => $id > 0)));
$status = (string)($b['status'] ?? '');
if (!$ids || count($ids) > 100) {
    throw new ApiError(['ids' => 'Select between 1 and 100 appointments']);
}
if (!in_array($status, ['confirmed', 'completed', 'cancelled'], true)) {
    throw new ApiError(['status' => 'Invalid bulk status']);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $select = $pdo->prepare('SELECT a.id, a.service_id, a.barber_id, a.appt_date, a.start_time, a.status, b.branch_id
        FROM appointments a JOIN barbers b ON b.id = a.barber_id WHERE a.id = ? FOR UPDATE');
    $update = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
    $changed = 0;
    $rules = [
        'confirmed' => ['pending', 'confirmed'],
        'completed' => ['confirmed', 'completed'],
        'cancelled' => ['pending', 'confirmed'],
    ];
    $resource = require __DIR__ . '/../lib/resources.php';
    foreach ($ids as $id) {
        $select->execute([$id]);
        $row = $select->fetch();
        if (!$row) {
            throw new ApiError([], 404, 'Appointment not found');
        }
        if (($branch = admin_branch_id()) !== null && (int)$row['branch_id'] !== $branch) {
            throw new ApiError([], 403, 'You can only manage appointments in your assigned branch');
        }
        if ($row['status'] === $status) {
            continue;
        }
        if (!in_array($row['status'], $rules[$status], true)) {
            throw new ApiError([], 409, 'One or more appointments cannot use that status');
        }
        if ($status === 'confirmed') {
            $candidate = [
                'service_id' => (int)$row['service_id'],
                'barber_id' => (int)$row['barber_id'],
                'appt_date' => $row['appt_date'],
                'start_time' => substr($row['start_time'], 0, 5),
                'status' => 'confirmed',
            ];
            $resource['appointments']['before']($pdo, $candidate, (int)$row['id']);
        }
        $update->execute([$status, $id]);
        $changed++;
    }
    audit_log($pdo, 'bulk_status', 'appointments', null, ['ids' => $ids, 'status' => $status, 'changed' => $changed]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

json_out(['ok' => true, 'changed' => $changed, 'status' => $status]);
