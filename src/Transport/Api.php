<?php

declare(strict_types=1);

namespace Thunder\Transport;

use GuzzleHttp\Psr7\MultipartStream;
use Thunder\Exception\ServerError;
use Thunder\Input\FileInput;
use Thunder\Input\Validation;
use Thunder\Model\BankList;
use Thunder\Model\Model;

/** Envelope parsing and wire encoding shared by all resource operations. */
final readonly class Api
{
    public function __construct(private Transport $transport, private string $baseUrl) {}

    /** @template T of Model
     * @param class-string<T> $class
     * @param array<string, mixed> $body
     * @param array<string, scalar|null> $query
     * @return T
     */
    public function call(string $class, string $method, string $path, #[\SensitiveParameter] array $body = [], #[\SensitiveParameter] array $query = [], bool $safe = false, ?FileInput $file = null, string $fileField = 'image', ?float $deadline = null): Model
    {
        [$data, $meta] = $this->request($method, $path, $body, $query, $safe, $file, $fileField, $deadline);
        if (!is_array($data)) {
            throw new ServerError($meta->status, null, 'Expected object data');
        }
        /** @var array<string, mixed> $data */
        return new $class($data, $meta);
    }

    /** Fetch the plain bank array without adding a synthetic JSON envelope. */
    public function banks(): BankList
    {
        [$data, $meta] = $this->request('GET', '/banks', [], [], true);
        if (!is_array($data) || !array_is_list($data)) {
            throw new ServerError($meta->status, null, 'Expected bank list');
        }
        $items = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                throw new ServerError($meta->status, null, 'Invalid bank item');
            }
            /** @var array<string, mixed> $row */
            $items[] = $row;
        }
        return new BankList($items, $meta);
    }

    /** @param array<string, mixed> $body
     * @param array<string, scalar|null> $query
     * @return array{mixed, \Thunder\Model\ResponseMeta}
     */
    private function request(string $method, string $path, #[\SensitiveParameter] array $body, #[\SensitiveParameter] array $query, bool $safe, ?FileInput $file = null, string $fileField = 'image', ?float $deadline = null): array
    {
        $encoded = '';
        $contentType = 'application/json';
        if ($file !== null) {
            $parts = [['name' => $fileField, 'contents' => $file->bytes, 'filename' => $file->filename, 'headers' => ['Content-Type' => $file->mimeType]]];
            foreach ($body as $key => $value) {
                if (!is_scalar($value)) {
                    Validation::fail('Multipart option must be scalar');
                }
                $parts[] = ['name' => $key, 'contents' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value];
            }
            $multipart = new MultipartStream($parts);
            $encoded = (string) $multipart;
            $contentType = 'multipart/form-data; boundary=' . $multipart->getBoundary();
        } elseif ($body !== []) {
            $encoded = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }
        $qs = Query::encode($query);
        $response = $this->transport->send($method, $this->baseUrl . $path . ($qs === '' ? '' : '?' . $qs), $encoded, $safe, $contentType, $deadline);
        try {
            $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ServerError($response->getStatusCode(), null, 'Invalid JSON response');
        }
        if (!is_array($payload) || !array_key_exists('data', $payload)) {
            throw new ServerError($response->getStatusCode(), null, 'Missing response data');
        }
        return [$payload['data'], ResponseDecoder::meta($response)];
    }
}
