<?php

declare(strict_types=1);

// Shared helpers of the example scripts. Every example builds its client with `new Client()`,
// which reads the API key from THUNDER_API_KEY (or the path of a key file from
// THUNDER_API_KEY_FILE). In your own project, require Composer's vendor/autoload.php instead.

use Thunder\Exception\ApiError;
use Thunder\Exception\ThunderError;

require __DIR__ . '/../vendor/autoload.php';

/** Returns command line argument $index (0 = first), or prints the usage and exits. */
function arg(int $index, string $usage): string
{
    $value = optionalArg($index);
    if ($value === null) {
        $script = commandLine()[0] ?? 'example.php';
        fwrite(STDERR, 'usage: php examples/' . basename($script) . ' ' . $usage . PHP_EOL);
        exit(2);
    }
    return $value;
}

/** Returns command line argument $index (0 = first), or null when it is missing. */
function optionalArg(int $index): ?string
{
    $value = commandLine()[$index + 1] ?? '';
    return $value === '' ? null : $value;
}

/** @return list<string> The script name followed by its arguments. */
function commandLine(): array
{
    $argv = $_SERVER['argv'] ?? [];
    return is_array($argv) ? array_values(array_filter($argv, is_string(...))) : [];
}

/** Reads a local file, or prints why it cannot and exits. */
function readLocalFile(string $path): string
{
    $bytes = is_file($path) ? file_get_contents($path) : false;
    if ($bytes === false) {
        fwrite(STDERR, 'cannot read ' . $path . PHP_EOL);
        exit(2);
    }
    return $bytes;
}

/**
 * Runs an example and prints its result as JSON, or the error and its request id.
 *
 * @param \Closure(): mixed $main
 */
function run(\Closure $main): void
{
    try {
        $result = $main();
        if ($result !== null) {
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
        }
    } catch (ApiError $error) {
        fwrite(STDERR, $error . ' (request id ' . ($error->requestId ?? 'none') . ')' . PHP_EOL);
        exit(1);
    } catch (ThunderError $error) {
        fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
        exit(1);
    }
}
