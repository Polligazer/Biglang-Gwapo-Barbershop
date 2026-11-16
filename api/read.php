<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
require __DIR__ . '/../lib/crud.php';
api_boot();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
json_out(['ok' => true] + fetch_rows(resource((string)($_GET['r'] ?? '')), $_GET, $id));
