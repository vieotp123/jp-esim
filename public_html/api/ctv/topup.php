<?php
declare(strict_types=1);
require_once __DIR__ . '/_dispatch.php';
ctv_api_dispatch(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'topup.lookup' : 'topup.create');
