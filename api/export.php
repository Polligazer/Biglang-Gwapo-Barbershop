<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
require __DIR__ . '/../lib/crud.php';
api_boot();

$name = (string)($_GET['r'] ?? '');
if (!in_array($name, ['appointments', 'services', 'inventory'], true)) {
    throw new ApiError([], 404, 'Unknown export');
}
if ($name === 'services') {
    require_global_admin();
}

$query = $_GET;
$query['limit'] = 'all';
$data = fetch_rows(resource($name), $query);
$rows = $data['rows'];
$columns = match ($name) {
    'appointments' => ['id', 'customer_name', 'customer_email', 'customer_phone', 'service_name', 'barber_name', 'branch_name', 'appt_date', 'start_time', 'end_time', 'status', 'notes', 'booking_ref', 'created_at'],
    'services' => ['id', 'name', 'description', 'duration_min', 'price', 'category', 'active'],
    'inventory' => ['id', 'sku', 'name', 'category', 'price', 'stock', 'min_threshold'],
};

$filename = $name . '-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
$out = fopen('php://output', 'wb');
fputcsv($out, $columns);
foreach ($rows as $row) {
    $values = [];
    foreach ($columns as $column) {
        $value = (string)($row[$column] ?? '');
        if ($value !== '' && preg_match('/^[=+\-@]/', $value)) {
            $value = "'" . $value;
        }
        $values[] = $value;
    }
    fputcsv($out, $values);
}
fclose($out);
exit;
