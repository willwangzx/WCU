<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'ok' => true,
    'service' => 'wcu-api',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
