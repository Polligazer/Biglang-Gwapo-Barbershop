<?php
declare(strict_types=1);
require __DIR__ . '/../lib/core.php';
require __DIR__ . '/../lib/crud.php';
api_boot(true);
$b = body();
$id = filter_var($b['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    throw new ApiError([], 400, 'Invalid id');
}
json_out(['ok' => true, 'row' => save((string)($b['r'] ?? ''), (array)($b['data'] ?? []), $id)]);
