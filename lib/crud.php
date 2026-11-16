<?php
declare(strict_types=1);

function resource(string $name, bool $writable = false): array
{
    static $all = null;
    $all ??= require __DIR__ . '/resources.php';
    $r = $all[$name] ?? null;
    if (!$r || ($writable && !empty($r['ro']))) {
        throw new ApiError([], 404, 'Unknown resource');
    }
    if ($writable && !empty($r['write_global_only'])) {
        require_global_admin();
    }
    return $r;
}

function scope_clause(array $r): array
{
    $branch = admin_branch_id();
    if ($branch === null || empty($r['scope'])) {
        return [null, []];
    }
    return match ($r['scope']) {
        'barber_branch', 'availability_branch' => ['b.branch_id = ?', [$branch]],
        'appointment_branch' => ['b.branch_id = ?', [$branch]],
        'inventory_branch' => ['i.branch_id = ?', [$branch]],
        'branch_list' => ['br.id = ?', [$branch]],
        default => [null, []],
    };
}

/** Paginated/filtered read, or a single row when $id is given. */
function fetch_rows(array $r, array $q = [], ?int $id = null): array
{
    $pdo = db();
    $where = $r['where'] ?? [];
    $p = [];
    if (!empty($r['global_only'])) {
        require_global_admin();
    }
    [$scopeWhere, $scopeParams] = scope_clause($r);
    if ($scopeWhere !== null) {
        $where[] = $scopeWhere;
        $p = array_merge($p, $scopeParams);
    }
    if ($id !== null) {
        $where[] = "{$r['pk']} = ?";
        $p[] = $id;
    }
    $term = trim((string)($q['q'] ?? ''));
    if ($term !== '' && !empty($r['search'])) {
        $like = '%' . addcslashes($term, '%_\\') . '%';
        $where[] = '(' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $r['search'])) . ')';
        foreach ($r['search'] as $_) {
            $p[] = $like;
        }
    }
    foreach (($r['filters'] ?? []) as $key => $col) {
        $v = (string)($q[$key] ?? '');
        if ($v !== '') {
            $where[] = "$col = ?";
            $p[] = $v;
        }
    }
    $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $order = (($q['dir'] ?? '') === 'asc' && isset($r['order_asc'])) ? $r['order_asc'] : $r['order'];
    $all = ($q['limit'] ?? '') === 'all';
    $limit = $all ? 0 : max(1, min(100, (int)($q['limit'] ?? 10)));
    $page = max(1, (int)($q['page'] ?? 1));
    $sql = "SELECT {$r['cols']} FROM {$r['from']}$w ORDER BY $order"
        . ($id === null && !$all ? " LIMIT $limit OFFSET " . (($page - 1) * $limit) : '');
    $st = $pdo->prepare($sql);
    $st->execute($p);
    $rows = $st->fetchAll();
    $total = count($rows);
    if ($id === null) {
        $ct = $pdo->prepare("SELECT COUNT(*) FROM {$r['from']}$w");
        $ct->execute($p);
        $total = (int)$ct->fetchColumn();
    }
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'limit' => $all ? 'all' : $limit];
}

/** INSERT (id null) or UPDATE one row from already-validated columns; returns the row id. */
function write_row(PDO $pdo, array $r, array $c, ?int $id): int
{
    $cols = array_keys($c);
    $params = [];
    foreach ($c as $k => $v) {
        $params[":$k"] = $v;
    }
    if ($id === null) {
        $sql = "INSERT INTO {$r['table']} (" . implode(',', array_map(fn($k) => "`$k`", $cols)) . ') VALUES ('
            . implode(',', array_map(fn($k) => ":$k", $cols)) . ')';
        $pdo->prepare($sql)->execute($params);
        return (int)$pdo->lastInsertId();
    }
    $sql = "UPDATE {$r['table']} SET " . implode(',', array_map(fn($k) => "`$k` = :$k", $cols)) . ' WHERE id = :__id';
    $params[':__id'] = $id;
    $pdo->prepare($sql)->execute($params);
    return $id;
}

/** Create ($id null) or update a row inside one transaction; returns the fresh row. */
function save(string $name, array $in, ?int $id): array
{
    $r = resource($name, true);
    if (!empty($r['global_only'])) {
        require_global_admin();
    }
    $pdo = db();
    $c = validate($r['fields'], $in, $id === null);

    $pdo->beginTransaction();
    try {
        foreach ($r['fields'] as $k => $f) {
            if ($f['t'] === 'fk' && isset($c[$k])) {
                $s = $pdo->prepare("SELECT 1 FROM {$f['ref']} WHERE id = ?");
                $s->execute([$c[$k]]);
                if (!$s->fetchColumn()) {
                    throw new ApiError([$k => 'Not found']);
                }
            }
        }
        if ($id !== null) {
            $s = $pdo->prepare("SELECT id FROM {$r['table']} WHERE id = ? FOR UPDATE");
            $s->execute([$id]);
            if (!$s->fetchColumn()) {
                throw new ApiError([], 404, 'Record not found');
            }
            if (admin_branch_id() !== null && !empty($r['scope'])) {
                if (!fetch_rows($r, [], $id)['rows']) {
                    throw new ApiError([], 403, 'You cannot modify records outside your assigned branch');
                }
            }
        }
        if (isset($r['before'])) {
            $r['before']($pdo, $c, $id);
        }
        $creating = $id === null;
        $id = write_row($pdo, $r, $c, $id);
        if (isset($r['after'])) {
            $r['after']($pdo, $id, $c, $creating);
        }
        audit_log($pdo, $creating ? 'create' : 'update', $name, $id, ['fields' => array_keys($c)]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return fetch_rows($r, [], $id)['rows'][0];
}

function remove(string $name, int $id): void
{
    $r = resource($name, true);
    if (!empty($r['global_only'])) {
        require_global_admin();
    }
    if ($name === 'admins') {
        if ((int)($_SESSION['admin']['id'] ?? 0) === $id) {
            throw new ApiError([], 409, 'You cannot remove your own admin account');
        }
        $count = (int)db()->query("SELECT COUNT(*) FROM admins WHERE role = 'global_admin'")->fetchColumn();
        $role = db()->prepare('SELECT role FROM admins WHERE id = ?');
        $role->execute([$id]);
        if ($role->fetchColumn() === 'global_admin' && $count <= 1) {
            throw new ApiError([], 409, 'At least one global admin account must remain');
        }
    }
    if (admin_branch_id() !== null && !empty($r['scope']) && !fetch_rows($r, [], $id)['rows']) {
        throw new ApiError([], 403, 'You cannot modify records outside your assigned branch');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if (!empty($r['soft_delete'])) {
            $st = $pdo->prepare("UPDATE {$r['table']} SET active = 0 WHERE id = ?");
        } else {
            $st = $pdo->prepare("DELETE FROM {$r['table']} WHERE id = ?");
        }
        $st->execute([$id]);
        if ($st->rowCount() === 0) {
            throw new ApiError([], 404, 'Record not found');
        }
        audit_log($pdo, !empty($r['soft_delete']) ? 'deactivate' : 'delete', $name, $id);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
