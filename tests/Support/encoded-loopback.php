<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
[$encoding, $shape] = array_slice(explode('/', $_SERVER['REQUEST_URI']), 1, 2);
$encoded = iterator_to_array(\Thunder\Tests\Unit\EncodedRedactionTest::encodings())[$encoding][0];
header('X-Echo: ' . $encoded);
header('Authorization: other-secret');
header('Proxy-Authorization: other-secret');
http_response_code($shape === 'success' ? 200 : 400);
if ($shape === 'plain') {
    echo 'plain ' . $encoded;
    return;
}
header('Content-Type: application/json');
$body = ['message' => $encoded, 'code' => $encoded, 'details' => [$encoded => $encoded]];
if ($shape === 'v2') {
    $body = ['error' => $body];
}
if ($shape === 'success') {
    $body = json_decode(file_get_contents(__DIR__ . '/../fixtures/v2/info/success.json'), true, 512, JSON_THROW_ON_ERROR)['response']['body'];
    $body['data']['application']['name'] = $encoded;
}
echo json_encode($body, JSON_THROW_ON_ERROR);
