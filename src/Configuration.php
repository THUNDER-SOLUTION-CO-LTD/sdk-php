<?php

declare(strict_types=1);

namespace Thunder;

/** Environment variable names and the production API base URLs. */
final class Configuration
{
    public const API_KEY = 'THUNDER_API_KEY';
    public const API_KEY_FILE = 'THUNDER_API_KEY_FILE';
    public const V1_ENV = 'THUNDER_V1_BASE_URL';
    public const V2_ENV = 'THUNDER_V2_BASE_URL';
    public const V1_BASE = 'https://api.thunder.in.th/v1';
    public const V2_BASE = 'https://api.thunder.in.th/v2';
}
