<?php

declare(strict_types=1);

namespace Thunder;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Thunder\Input\Validation;
use Thunder\Model\Info;
use Thunder\Resource\{BankAccounts, Banks, Jobs, V1, Verify};
use Thunder\Transport\{Api, Transport, TransportFactory};

/** Typed resources; credentials stay in the transport and are excluded from debug output. */
class Client
{
    public readonly Verify $verify;
    public readonly Banks $banks;
    public readonly BankAccounts $bankAccounts;
    public readonly Jobs $jobs;
    public readonly V1 $v1;
    private readonly Api $api;

    /** Explicit key wins over environment, which wins over the configured key file.
     * @param null|\Closure(float): void $sleep Injection for tests or a custom scheduler.
     */
    public function __construct(
        #[\SensitiveParameter]
        ?string $apiKey = null,
        ?string $v1BaseUrl = null,
        ?string $v2BaseUrl = null,
        float $timeout = 30.0,
        int $maxRetries = 2,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?\Closure $sleep = null,
    ) {
        if ($apiKey === null) {
            $env = getenv(Configuration::API_KEY);
            $apiKey = $env === false || $env === '' ? null : $env;
            if ($apiKey === null) {
                $file = getenv(Configuration::API_KEY_FILE);
                if ($file !== false && $file !== '') {
                    if (!is_file($file) || !is_readable($file) || str_contains($file, '://')) {
                        Validation::fail('Cannot read API key file');
                    }
                    $value = file_get_contents($file);
                    if ($value === false) {
                        Validation::fail('Cannot read API key file');
                    }
                    $apiKey = trim($value);
                }
            }
        }
        if (!is_finite($timeout) || $timeout <= 0) {
            Validation::fail('timeout must be positive');
        }
        $v1BaseUrl = rtrim($v1BaseUrl ?? (getenv(Configuration::V1_ENV) ?: Configuration::V1_BASE), '/');
        $v2BaseUrl = rtrim($v2BaseUrl ?? (getenv(Configuration::V2_ENV) ?: Configuration::V2_BASE), '/');
        Validation::url($v1BaseUrl);
        Validation::url($v2BaseUrl);
        $transport = new Transport($httpClient ?? TransportFactory::httpClient($timeout), $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(), $apiKey ?? '', $maxRetries, $sleep, timeout: $timeout);
        $this->api = new Api($transport, $v2BaseUrl);
        $this->verify = new Verify($this->api);
        $this->banks = new Banks($this->api);
        $this->bankAccounts = new BankAccounts($this->api);
        $this->jobs = new Jobs($this->api, $sleep);
        $this->v1 = new V1(new Api($transport, $v1BaseUrl));
    }

    /** Read application quota and account information. */
    public function getInfo(): Info
    {
        return $this->api->call(Info::class, 'GET', '/info', safe: true);
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['client' => 'configured'];
    }
}
