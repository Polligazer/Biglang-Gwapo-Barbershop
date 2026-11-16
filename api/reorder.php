<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
api_boot(true);

$b = body();
$inventoryId = filter_var($b['inventory_id'] ?? null, FILTER_VALIDATE_INT);
$quantity = filter_var($b['quantity'] ?? null, FILTER_VALIDATE_INT);
$note = trim((string)($b['note'] ?? ''));
if ($inventoryId === false || $inventoryId < 1 || $quantity === false || $quantity < 1 || $quantity > 100000) {
    throw new ApiError(['quantity' => 'Enter a quantity between 1 and 100000']);
}
if (strlen($note) > 255) {
    throw new ApiError(['note' => 'Maximum 255 characters']);
}

$pdo = db();
$check = $pdo->prepare('SELECT id, name, branch_id FROM inventory WHERE id = ?');
$check->execute([$inventoryId]);
$item = $check->fetch();
if (!$item) {
    throw new ApiError([], 404, 'Inventory item not found');
}
if (($branch = admin_branch_id()) !== null && (int)$item['branch_id'] !== $branch) {
    throw new ApiError([], 403, 'You can only reorder inventory in your assigned branch');
}
$pdo->beginTransaction();
try {
    $st = $pdo->prepare("INSERT INTO reorder_requests (inventory_id, requested_qty, note, created_by) VALUES (?, ?, ?, ?)");
    $st->execute([$inventoryId, $quantity, $note ?: null, (int)$_SESSION['admin']['id']]);
    $id = (int)$pdo->lastInsertId();
    audit_log($pdo, 'reorder_request', 'inventory', (int)$inventoryId, ['request_id' => $id, 'quantity' => $quantity]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
json_out(['ok' => true, 'id' => $id, 'item' => $item['name'], 'quantity' => $quantity], 201);
