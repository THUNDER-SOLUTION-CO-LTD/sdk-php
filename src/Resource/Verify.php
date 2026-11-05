<?php

declare(strict_types=1);

namespace Thunder\Resource;

use Thunder\Input\{FileInput, Validation, VerifyOptions};
use Thunder\Model\{BankSlipResult, TrueWalletResult};
use Thunder\Transport\Api;

/** Verify API operations. */
final readonly class Verify
{
    /** Create the v2 verification resource. */
    public function __construct(private Api $api) {}
    /** Verify a bank slip; may spend quota. */
    public function bankByPayload(string $payload, ?VerifyOptions $options = null): BankSlipResult
    {
        Validation::text($payload, 'payload', 128);
        $body = $options?->jsonSerialize() ?? [];
        $body = ['payload' => $payload] + $body;
        return $this->api->call(BankSlipResult::class, 'POST', '/verify/bank', $body);
    }
    /** Verify a bank slip; may spend quota. */
    public function bankByImage(FileInput $file, ?VerifyOptions $options = null): BankSlipResult
    {
        $body = $options?->jsonSerialize() ?? [];
        return $this->api->call(BankSlipResult::class, 'POST', '/verify/bank', $body, file: $file, fileField: 'image');
    }
    /** Verify a bank slip; may spend quota. */
    public function bankByBase64(string $base64, ?VerifyOptions $options = null): BankSlipResult
    {
        Validation::text($base64, 'base64', 6000000);
        $body = $options?->jsonSerialize() ?? [];
        $body = ['base64' => $base64] + $body;
        return $this->api->call(BankSlipResult::class, 'POST', '/verify/bank', $body);
    }
    /** Verify a bank slip; may spend quota. */
    public function bankByUrl(string $url, ?VerifyOptions $options = null): BankSlipResult
    {
        Validation::url($url, 2048);
        $body = $options?->jsonSerialize() ?? [];
        $body = ['url' => $url] + $body;
        return $this->api->call(BankSlipResult::class, 'POST', '/verify/bank', $body);
    }
    /** Verify a wallet slip; may spend quota. */
    public function truewalletByImage(FileInput $file, ?VerifyOptions $options = null): TrueWalletResult
    {
        $body = $options?->jsonSerialize() ?? [];
        return $this->api->call(TrueWalletResult::class, 'POST', '/verify/truewallet', $body, file: $file, fileField: 'image');
    }
    /** Verify a wallet slip; may spend quota. */
    public function truewalletByBase64(string $base64, ?VerifyOptions $options = null): TrueWalletResult
    {
        Validation::text($base64, 'base64', 6000000);
        $body = $options?->jsonSerialize() ?? [];
        $body = ['base64' => $base64] + $body;
        return $this->api->call(TrueWalletResult::class, 'POST', '/verify/truewallet', $body);
    }
    /** Verify a wallet slip; may spend quota. */
    public function truewalletByUrl(string $url, ?VerifyOptions $options = null): TrueWalletResult
    {
        Validation::url($url, 255);
        $body = $options?->jsonSerialize() ?? [];
        $body = ['url' => $url] + $body;
        return $this->api->call(TrueWalletResult::class, 'POST', '/verify/truewallet', $body);
    }
}
