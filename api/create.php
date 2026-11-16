<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
require __DIR__ . '/../lib/crud.php';
api_boot(true);
$b = body();
json_out(['ok' => true, 'row' => save((string)($b['r'] ?? ''), (array)($b['data'] ?? []), null)], 201);
