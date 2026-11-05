<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');
if (str_contains($path, 'jobs')) {
    if (str_contains($path, 'retry')) {
        http_response_code(429);
        header('Retry-After: 1');
        echo '{"message":"rate limited"}';
        return;
    }
    usleep(str_contains($path, 'stall') ? 1000000 : 200000);
    echo '{"data":{"jobId":"slow","status":"done","attempts":1,"createdAt":"now","updatedAt":"now"}}';
    return;
}
$key = substr($_SERVER['HTTP_AUTHORIZATION'] ?? '', 7);
header('X-Echo: ' . rawurlencode($key));
header('Authorization: Bearer ' . $key);
header('Proxy-Authorization: Basic secret-proxy');
http_response_code(400);
$escaped = implode('', array_map(static fn(string $c): string => sprintf('\\u%04x', ord($c)), str_split($key)));
echo str_contains($path, 'v1')
    ? '{"message":"' . $escaped . '","details":{"value":"' . $escaped . '"}}'
    : '{"error":{"message":"' . $escaped . '","code":"' . $escaped . '","details":{"value":"' . $escaped . '"}}}';
