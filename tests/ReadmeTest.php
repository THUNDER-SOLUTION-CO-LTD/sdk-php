<?php

declare(strict_types=1);

namespace Thunder\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Checks every ```php code block of README.md and README.th.md as written: each one is a complete
 * file that starts with <?php, has valid syntax on this PHP version and passes PHPStan at the max
 * level against the SDK source, so the samples customers copy keep working.
 */
final class ReadmeTest extends TestCase
{
    public function testEveryReadmeCodeBlockPassesPhpLintAndPhpStan(): void
    {
        $root = dirname(__DIR__);
        $dir = $root . '/build/readme-check';
        if (!is_dir($dir . '/vendor')) {
            mkdir($dir . '/vendor', 0o777, true);
        }
        array_map(unlink(...), [...glob($dir . '/*.php') ?: [], ...glob($dir . '/*.neon') ?: []]);
        // The blocks start with require __DIR__ . '/vendor/autoload.php', as in a customer's project.
        file_put_contents($dir . '/vendor/autoload.php', "<?php\n\nreturn require " . var_export($root . '/vendor/autoload.php', true) . ";\n");

        $count = 0;
        foreach (['README.md', 'README.th.md'] as $doc) {
            $text = (string) file_get_contents($root . '/' . $doc);
            preg_match_all('/^```php\n(.*?)^```$/ms', $text, $blocks, PREG_OFFSET_CAPTURE);
            self::assertNotEmpty($blocks[1], "$doc has php code blocks");
            foreach ($blocks[1] as $i => [$code, $offset]) {
                $line = substr_count(substr($text, 0, $offset), "\n") + 1;
                $name = str_replace('.', '-', strtolower($doc)) . '-' . ($i + 1) . '-line-' . $line . '.php';
                self::assertStringStartsWith('<?php', $code, "$doc:$line: the code block must start with <?php");
                file_put_contents($dir . '/' . $name, $code);
                exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($dir . '/' . $name) . ' 2>&1', $output, $status);
                self::assertSame(0, $status, "$doc:$line: " . implode("\n", $output));
                $output = [];
                ++$count;
            }
        }

        file_put_contents($dir . '/phpstan.neon', <<<NEON
            parameters:
                level: max
                phpVersion:
                    min: 80200
                    max: 80599
                paths:
                    - {$dir}
                excludePaths:
                    - {$dir}/phpstan.neon
                    - {$dir}/vendor
                tmpDir: {$root}/build/phpstan
            NEON);
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/vendor/bin/phpstan')
            . ' analyse --no-progress --error-format=raw --memory-limit=1G -c ' . escapeshellarg($dir . '/phpstan.neon') . ' 2>&1';
        exec($command, $output, $status);
        self::assertSame(0, $status, "PHPStan found errors in the $count README code blocks (each file name gives the README and the line):\n" . implode("\n", $output));
    }
}
