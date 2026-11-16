<?php
// PUBLIC read-only catalog: branches, barbers, services, products. No login, no session.
declare(strict_types=1);
require __DIR__ . '/../../lib/core.php';
public_boot();

$pdo = db();
$branch = (int)($_GET['branch_id'] ?? 0);
$cat = trim((string)($_GET['category'] ?? ''));

switch ((string)($_GET['r'] ?? '')) {
    case 'branches':
        $sql = 'SELECT id, name, address, phone, hours FROM branches WHERE active = 1 ORDER BY id';
        $p = [];
        break;
    case 'barbers':
        $sql = 'SELECT b.id, b.name, b.title, b.chair, b.branch_id FROM barbers b JOIN branches br ON br.id = b.branch_id
                WHERE b.active = 1 AND br.active = 1' . ($branch ? ' AND b.branch_id = ?' : '') . ' ORDER BY b.branch_id, b.chair';
        $p = $branch ? [$branch] : [];
        break;
    case 'services':
        $sql = 'SELECT id, name, description, duration_min, price, category FROM services WHERE active = 1'
            . ($cat !== '' ? ' AND category = ?' : '') . ' ORDER BY category, price';
        $p = $cat !== '' ? [$cat] : [];
        break;
    case 'products': // exact stock counts are never exposed publicly
        $sql = 'SELECT id, name, category, price, (stock > 0) AS in_stock FROM inventory ORDER BY category, name';
        $p = [];
        break;
    default:
        throw new ApiError([], 404, 'Unknown catalog');
}
$st = $pdo->prepare($sql);
$st->execute($p);
$rows = $st->fetchAll();
if ($_GET['r'] === 'products') {
    foreach ($rows as &$r) {
        $r['in_stock'] = (bool)$r['in_stock'];
    }
}
header('Cache-Control: public, max-age=30');
json_out(['ok' => true, 'today' => date('Y-m-d'), 'rows' => $rows]);
