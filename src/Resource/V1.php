<?php

declare(strict_types=1);

namespace Thunder\Resource;

use Thunder\Input\{FileInput, QrRequest, V1VerifyOptions, Validation};
use Thunder\Model\{BankSlip, QrCode, TrueWalletSlip, V1Me};
use Thunder\Transport\Api;

/** V1 API operations. */
final readonly class V1
{
    /** Create the legacy v1 resource. */
    public function __construct(private Api $api) {}
    /** Read the legacy account and quota. */
    public function getMe(): V1Me
    {
        return $this->api->call(V1Me::class, 'GET', '/me', safe: true);
    }
    /** Generate a QR image without spending verification quota. */
    public function generateQr(QrRequest $request): QrCode
    {
        return $this->api->call(QrCode::class, 'POST', '/qr/generate', $request->jsonSerialize(), safe: true);
    }
    /** Verify a bank slip; may spend quota. */
    public function verifyBankByPayload(string $payload, ?V1VerifyOptions $options = null): BankSlip
    {
        Validation::text($payload, 'payload', 255);
        $body = $options?->jsonSerialize() ?? [];
        $body = ['payload' => $payload] + $body;
        return $this->api->call(BankSlip::class, 'GET', '/verify', query: $body);
    }
    /** Verify a bank slip; may spend quota. */
    public function verifyBankByImage(FileInput $file, ?V1VerifyOptions $options = null): BankSlip
    {
        $body = $options?->jsonSerialize() ?? [];
        return $this->api->call(BankSlip::class, 'POST', '/verify', $body, file: $file, fileField: 'file');
    }
    /** Verify a bank slip; may spend quota. */
    public function verifyBankByBase64(string $base64, ?V1VerifyOptions $options = null): BankSlip
    {
        Validation::text($base64, 'base64', 6000000);
        $body = $options?->jsonSerialize() ?? [];
        $body = ['image' => $base64] + $body;
        return $this->api->call(BankSlip::class, 'POST', '/verify', $body);
    }
    /** Verify a bank slip; may spend quota. */
    public function verifyBankByUrl(string $url, ?V1VerifyOptions $options = null): BankSlip
    {
        Validation::url($url, 2048);
        $body = $options?->jsonSerialize() ?? [];
        $body = ['url' => $url] + $body;
        return $this->api->call(BankSlip::class, 'POST', '/verify', $body);
    }
    /** Verify a wallet slip; may spend quota. */
    public function verifyTruemoneyByImage(FileInput $file, ?V1VerifyOptions $options = null): TrueWalletSlip
    {
        $body = $options?->jsonSerialize() ?? [];
        return $this->api->call(TrueWalletSlip::class, 'POST', '/verify/truewallet', $body, file: $file, fileField: 'file');
    }
}
