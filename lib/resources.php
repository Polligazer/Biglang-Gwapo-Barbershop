<?php
declare(strict_types=1);

/**
 * Resource registry: one definition drives read/create/update/delete.
 * 'before' runs inside the write transaction (business rules, conflict checks).
 */
return [
    'barbers' => [
        'ro' => true, 'pk' => 'b.id', 'cols' => 'b.id, b.name, b.chair, b.title, b.branch_id, br.name AS branch',
        'from' => 'barbers b JOIN branches br ON br.id = b.branch_id',
        'where' => ['b.active = 1'], 'order' => 'br.name, b.chair',
    ],

    'services' => [
        'table' => 'services', 'pk' => 'id', 'cols' => '*', 'from' => 'services',
        'search' => ['name', 'description'], 'filters' => ['category' => 'category'],
        'order' => 'category, name',
        'fields' => [
            'name'         => ['t' => 'str', 'req' => 1, 'max' => 120],
            'description'  => ['t' => 'text'],
            'duration_min' => ['t' => 'int', 'req' => 1, 'min' => 15, 'max' => 240],
            'price'        => ['t' => 'dec', 'req' => 1, 'min' => 0, 'max' => 9999],
            'category'     => ['t' => 'enum', 'req' => 1, 'in' => ['Haircut', 'Beard & Shave', 'Facial & Care', 'Packages']],
            'active'       => ['t' => 'bool', 'def' => 1],
        ],
    ],

    'inventory' => [
        'table' => 'inventory', 'pk' => 'id', 'cols' => '*', 'from' => 'inventory',
        'search' => ['name', 'sku'], 'filters' => ['category' => 'category'],
        'order' => '(stock <= min_threshold) DESC, name',
        'fields' => [
            'sku'           => ['t' => 'str', 'req' => 1, 'max' => 32],
            'name'          => ['t' => 'str', 'req' => 1, 'max' => 150],
            'category'      => ['t' => 'enum', 'req' => 1, 'in' => ['Styling', 'Shave', 'Beard', 'Skincare', 'Tools', 'Consumables']],
            'price'         => ['t' => 'dec', 'req' => 1, 'min' => 0, 'max' => 9999],
            'min_threshold' => ['t' => 'int', 'req' => 1, 'min' => 0, 'max' => 100000],
            // Stock is only set on creation; afterwards it changes via api/adjust_stock.php (ledgered).
            'stock'         => ['t' => 'int', 'req' => 1, 'min' => 0, 'max' => 100000, 'create_only' => 1],
        ],
        'after' => function (PDO $pdo, int $id, array $c, bool $creating): void {
            if ($creating && ($c['stock'] ?? 0) > 0) {
                $pdo->prepare('INSERT INTO stock_ledger (inventory_id, type, qty_change, note) VALUES (?, ?, ?, ?)')
                    ->execute([$id, 'receive', $c['stock'], 'Initial stock']);
            }
        },
    ],

    'appointments' => [
        'table' => 'appointments', 'pk' => 'a.id',
        'cols'  => 'a.*, s.name AS service_name, s.duration_min, s.price, b.name AS barber_name, b.chair, br.name AS branch_name',
        'from'  => 'appointments a JOIN services s ON s.id = a.service_id JOIN barbers b ON b.id = a.barber_id JOIN branches br ON br.id = b.branch_id',
        'search'  => ['a.customer_name', 'a.customer_email', 'a.customer_phone', 's.name'],
        'filters' => ['status' => 'a.status', 'date' => 'a.appt_date', 'barber_id' => 'a.barber_id'],
        'order' => 'a.appt_date DESC, a.start_time DESC',
        'order_asc' => 'a.appt_date ASC, a.start_time ASC',
        'fields' => [
            'customer_name'  => ['t' => 'str', 'req' => 1, 'max' => 120],
            'customer_email' => ['t' => 'email'],
            'customer_phone' => ['t' => 'str', 'max' => 30],
            'service_id'     => ['t' => 'fk', 'req' => 1, 'ref' => 'services'],
            'barber_id'      => ['t' => 'fk', 'req' => 1, 'ref' => 'barbers'],
            'appt_date'      => ['t' => 'date', 'req' => 1],
            'start_time'     => ['t' => 'time', 'req' => 1],
            'status'         => ['t' => 'enum', 'req' => 1, 'in' => ['pending', 'confirmed', 'completed', 'cancelled'], 'def' => 'pending'],
            'notes'          => ['t' => 'text'],
        ],
        'before' => function (PDO $pdo, array &$c, ?int $id): void {
            // 1. Service must exist/be active (unchanged services on old bookings are allowed).
            $s = $pdo->prepare('SELECT duration_min, active FROM services WHERE id = ?');
            $s->execute([$c['service_id']]);
            $svc = $s->fetch();
            if (!$svc) {
                throw new ApiError(['service_id' => 'Service not found']);
            }
            $oldService = null;
            if ($id !== null) {
                $o = $pdo->prepare('SELECT service_id FROM appointments WHERE id = ?');
                $o->execute([$id]);
                $oldService = (int)$o->fetchColumn();
            }
            if (!$svc['active'] && $oldService !== (int)$c['service_id']) {
                throw new ApiError(['service_id' => 'This service is no longer offered']);
            }

            // 2. Time must fit inside studio hours (09:00-18:00); end time derives from service duration.
            [$h, $m] = array_map('intval', explode(':', $c['start_time']));
            $start = $h * 60 + $m;
            $end = $start + (int)$svc['duration_min'];
            if ($start < 540 || $end > 1080) {
                throw new ApiError(['start_time' => 'Service must fit within studio hours (09:00 - 18:00)']);
            }
            $c['end_time'] = sprintf('%02d:%02d:00', intdiv($end, 60), $end % 60);
            $c['start_time'] .= ':00';

            if ($id === null && $c['status'] !== 'cancelled' && $c['appt_date'] < date('Y-m-d')) {
                throw new ApiError(['appt_date' => 'Cannot book in the past']);
            }

            // 3. Conflict check. Lock the barber row so concurrent bookings serialize.
            if (in_array($c['status'], ['pending', 'confirmed'], true)) {
                $lock = $pdo->prepare('SELECT id FROM barbers WHERE id = ? AND active = 1 FOR UPDATE');
                $lock->execute([$c['barber_id']]);
                if (!$lock->fetchColumn()) {
                    throw new ApiError(['barber_id' => 'Barber not available']);
                }
                $sql = "SELECT start_time, end_time FROM appointments
                        WHERE barber_id = ? AND appt_date = ? AND status IN ('pending','confirmed')
                          AND start_time < ? AND end_time > ?" . ($id !== null ? ' AND id <> ?' : '') . ' LIMIT 1';
                $params = [$c['barber_id'], $c['appt_date'], $c['end_time'], $c['start_time']];
                if ($id !== null) {
                    $params[] = $id;
                }
                $q = $pdo->prepare($sql);
                $q->execute($params);
                if ($hit = $q->fetch()) {
                    throw new ApiError(
                        ['start_time' => 'Barber is already booked ' . substr($hit['start_time'], 0, 5) . ' - ' . substr($hit['end_time'], 0, 5)],
                        409,
                        'Time slot conflict'
                    );
                }
            }
        },
    ],
];
