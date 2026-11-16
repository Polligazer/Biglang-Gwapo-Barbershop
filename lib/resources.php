<?php
declare(strict_types=1);

/**
 * Resource registry: one definition drives read/create/update/delete.
 * 'before' runs inside the write transaction (business rules, conflict checks).
 */
return [
    'barbers' => [
        'soft_delete' => true, 'pk' => 'b.id', 'cols' => 'b.id, b.name, b.chair, b.title, b.branch_id, br.name AS branch, b.active',
        'from' => 'barbers b JOIN branches br ON br.id = b.branch_id',
        'where' => [], 'order' => 'br.name, b.chair', 'scope' => 'barber_branch',
        'fields' => [
            'name' => ['t' => 'str', 'req' => 1, 'max' => 100],
            'branch_id' => ['t' => 'fk', 'req' => 1, 'ref' => 'branches'],
            'chair' => ['t' => 'int', 'req' => 1, 'min' => 1, 'max' => 255],
            'title' => ['t' => 'str', 'max' => 60],
            'active' => ['t' => 'bool', 'def' => 1],
        ],
        'before' => function (PDO $pdo, array &$c, ?int $id): void {
            if (($branch = admin_branch_id()) !== null && (int)$c['branch_id'] !== $branch) {
                throw new ApiError([], 403, 'You can only manage barbers in your assigned branch');
            }
            $st = $pdo->prepare('SELECT 1 FROM barbers WHERE branch_id = ? AND chair = ?' . ($id === null ? '' : ' AND id <> ?'));
            $params = [$c['branch_id'], $c['chair']];
            if ($id !== null) {
                $params[] = $id;
            }
            $st->execute($params);
            if ($st->fetchColumn()) {
                throw new ApiError(['chair' => 'That chair is already assigned in this branch']);
            }
        },
    ],

    'branches' => [
        'ro' => true, 'pk' => 'br.id', 'cols' => 'br.id, br.name, br.address, br.phone, br.hours, br.active',
        'from' => 'branches br', 'where' => ['br.active = 1'], 'order' => 'br.name', 'scope' => 'branch_list',
    ],

    'availability' => [
        'table' => 'barber_unavailability', 'pk' => 'u.id',
        'cols' => 'u.*, b.name AS barber_name, br.name AS branch',
        'from' => 'barber_unavailability u JOIN barbers b ON b.id = u.barber_id JOIN branches br ON br.id = b.branch_id',
        'order' => 'u.start_date DESC, u.id DESC', 'scope' => 'barber_branch',
        'fields' => [
            'barber_id' => ['t' => 'fk', 'req' => 1, 'ref' => 'barbers'],
            'start_date' => ['t' => 'date', 'req' => 1],
            'end_date' => ['t' => 'date', 'req' => 1],
            'start_time' => ['t' => 'time'],
            'end_time' => ['t' => 'time'],
            'reason' => ['t' => 'str', 'max' => 255],
        ],
        'before' => function (PDO $pdo, array &$c, ?int $id): void {
            if ($c['end_date'] < $c['start_date']) {
                throw new ApiError(['end_date' => 'End date must be on or after start date']);
            }
            if (($c['start_time'] === null) !== ($c['end_time'] === null)) {
                throw new ApiError(['start_time' => 'Provide both times, or leave both blank for the whole day']);
            }
            if ($c['start_time'] !== null && $c['end_time'] <= $c['start_time']) {
                throw new ApiError(['end_time' => 'End time must be after start time']);
            }
            if (($branch = admin_branch_id()) !== null) {
                $st = $pdo->prepare('SELECT 1 FROM barbers WHERE id = ? AND branch_id = ?');
                $st->execute([$c['barber_id'], $branch]);
                if (!$st->fetchColumn()) {
                    throw new ApiError([], 403, 'You can only manage barbers in your assigned branch');
                }
            }
            if ($c['start_time'] !== null) {
                $c['start_time'] .= ':00';
                $c['end_time'] .= ':00';
            }
        },
    ],

    'admins' => [
        'table' => 'admins', 'pk' => 'a.id',
        'cols' => 'a.id, a.username, a.name, a.role, a.branch_id, br.name AS branch',
        'from' => 'admins a LEFT JOIN branches br ON br.id = a.branch_id',
        'order' => 'a.username', 'global_only' => true,
        'fields' => [
            'username' => ['t' => 'str', 'req' => 1, 'max' => 50],
            'name' => ['t' => 'str', 'req' => 1, 'max' => 100],
            'role' => ['t' => 'enum', 'req' => 1, 'in' => ['global_admin', 'branch_manager']],
            'branch_id' => ['t' => 'fk', 'ref' => 'branches'],
            'password' => ['t' => 'str', 'max' => 200],
        ],
        'before' => function (PDO $pdo, array &$c, ?int $id): void {
            require_global_admin();
            if ($id === null && strlen((string)($c['password'] ?? '')) < 10) {
                throw new ApiError(['password' => 'Password must be at least 10 characters']);
            }
            if ($c['role'] === 'branch_manager' && empty($c['branch_id'])) {
                throw new ApiError(['branch_id' => 'Branch is required for a branch manager']);
            }
            if ($c['role'] === 'global_admin') {
                $c['branch_id'] = null;
            }
            if (!empty($c['password'])) {
                $c['password_hash'] = password_hash($c['password'], PASSWORD_DEFAULT);
            }
            unset($c['password']);
        },
    ],

    'services' => [
        'write_global_only' => true, 'table' => 'services', 'pk' => 'id', 'cols' => '*', 'from' => 'services',
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
        'table' => 'inventory', 'pk' => 'i.id', 'cols' => 'i.*', 'from' => 'inventory i JOIN branches br ON br.id = i.branch_id',
        'search' => ['name', 'sku'], 'filters' => ['category' => 'category'],
        'order' => '(i.stock <= i.min_threshold) DESC, i.name', 'scope' => 'inventory_branch',
        'fields' => [
            'branch_id'     => ['t' => 'fk', 'req' => 1, 'ref' => 'branches'],
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
        'before' => function (PDO $pdo, array &$c, ?int $id): void {
            if (($branch = admin_branch_id()) !== null && (int)$c['branch_id'] !== $branch) {
                throw new ApiError([], 403, 'You can only manage inventory in your assigned branch');
            }
        },
    ],

    'appointments' => [
        'table' => 'appointments', 'pk' => 'a.id',
        'cols'  => 'a.*, s.name AS service_name, s.duration_min, s.price, b.name AS barber_name, b.chair, br.name AS branch_name',
        'from'  => 'appointments a JOIN services s ON s.id = a.service_id JOIN barbers b ON b.id = a.barber_id JOIN branches br ON br.id = b.branch_id',
        'search'  => ['a.customer_name', 'a.customer_email', 'a.customer_phone', 's.name'],
        'filters' => ['status' => 'a.status', 'date' => 'a.appt_date', 'barber_id' => 'a.barber_id'],
        'order' => 'a.appt_date DESC, a.start_time DESC', 'scope' => 'appointment_branch',
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
            if (($branch = admin_branch_id()) !== null) {
                $barberBranch = $pdo->prepare('SELECT branch_id FROM barbers WHERE id = ?');
                $barberBranch->execute([$c['barber_id']]);
                if ((int)$barberBranch->fetchColumn() !== $branch) {
                    throw new ApiError([], 403, 'You can only manage appointments in your assigned branch');
                }
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

            $unavailable = $pdo->prepare(
                "SELECT 1 FROM barber_unavailability
                 WHERE barber_id = ? AND start_date <= ? AND end_date >= ?
                   AND (start_time IS NULL OR (start_time < ? AND end_time > ?))
                 LIMIT 1"
            );
            $unavailable->execute([$c['barber_id'], $c['appt_date'], $c['appt_date'], $c['end_time'], $c['start_time']]);
            if ($unavailable->fetchColumn()) {
                throw new ApiError(['start_time' => 'This barber is unavailable during that time']);
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
