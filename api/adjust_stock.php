<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
require __DIR__ . '/../lib/crud.php';
api_boot(true);

$c = validate([
    'id'        => ['t' => 'fk', 'req' => 1],
    'type'      => ['t' => 'enum', 'req' => 1, 'in' => ['receive', 'waste', 'correction', 'transfer']],
    'quantity'  => ['t' => 'int', 'req' => 1, 'min' => 0, 'max' => 100000],
    'batch_ref' => ['t' => 'str', 'max' => 120],
    'note'      => ['t' => 'str', 'max' => 255],
], body(), true);
if ($c['type'] !== 'correction' && $c['quantity'] < 1) {
    throw new ApiError(['quantity' => 'Must be at least 1']);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $s = $pdo->prepare('SELECT stock FROM inventory WHERE id = ? FOR UPDATE');
    $s->execute([$c['id']]);
    $cur = $s->fetchColumn();
    if ($cur === false) {
        throw new ApiError([], 404, 'Item not found');
    }
    $cur = (int)$cur;
    $new = match ($c['type']) {
        'receive'    => $cur + $c['quantity'],
        'correction' => $c['quantity'],
        default      => $cur - $c['quantity'],
    };
    if ($new < 0) {
        throw new ApiError(['quantity' => "Only $cur units on hand"]);
    }
    if ($new > 100000) {
        throw new ApiError(['quantity' => 'Resulting stock is too large']);
    }
    $pdo->prepare('UPDATE inventory SET stock = ? WHERE id = ?')->execute([$new, $c['id']]);
    $pdo->prepare('INSERT INTO stock_ledger (inventory_id, type, qty_change, batch_ref, note) VALUES (?,?,?,?,?)')
        ->execute([$c['id'], $c['type'], $new - $cur, $c['batch_ref'], $c['note']]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
json_out(['ok' => true, 'row' => fetch_rows(resource('inventory'), [], (int)$c['id'])['rows'][0]]);
