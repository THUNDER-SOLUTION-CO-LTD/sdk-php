<?php

declare(strict_types=1);

$path = $_SERVER['REQUEST_URI'];
$body = str_contains($path, 'error') ? '{"error":{"code":"FAIL","message":"synthetic-KeY+/test"}}' : '{"data":{"jobId":"job-1","status":"done","attempts":1,"createdAt":"now","updatedAt":"now"}}';
http_response_code(str_contains($path, 'error') ? 400 : 200);
header('Content-Type: application/json');
header('Content-Length: ' . strlen($body));
foreach (str_split($body) as $byte) {
    echo $byte;
    flush();
    usleep(5000);
}
