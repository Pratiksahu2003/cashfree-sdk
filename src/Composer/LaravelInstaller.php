<?php

namespace CashfreePayment\Composer;

use Composer\IO\IOInterface;

class LaravelInstaller
{
    private const PACKAGE_NAME = 'pratiksahu2003/cashfree-sdk';

    private const ENV_BLOCK = <<<'ENV'

# Cashfree Payment Gateway (pratiksahu2003/cashfree-sdk)
CASHFREE_APP_ID=
CASHFREE_SECRET_KEY=
CASHFREE_ENV=sandbox
CASHFREE_API_VERSION=2023-08-01
CASHFREE_LOGGING_ENABLED=true
CASHFREE_LOG_CHANNEL=cashfree
CASHFREE_RETRY_ATTEMPTS=3
CASHFREE_RETRY_BACKOFF_MS=500
ENV;

    public static function isLaravelProject(string $projectRoot): bool
    {
        return is_file($projectRoot . '/artisan')
            && is_file($projectRoot . '/bootstrap/app.php');
    }

    public static function publishConfig(string $projectRoot, IOInterface $io): void
    {
        $target = $projectRoot . '/config/cashfree.php';

        if (is_file($target)) {
            $io->write('  • Config already exists at config/cashfree.php');

            return;
        }

        $source = $projectRoot . '/vendor/' . self::PACKAGE_NAME . '/config/cashfree.php';

        if (! is_file($source)) {
            $io->writeError('  • Could not publish Cashfree config automatically');

            return;
        }

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }

        if (copy($source, $target)) {
            $io->write('  • Published config/cashfree.php');
        }
    }

    public static function appendEnvVariables(string $projectRoot, IOInterface $io): void
    {
        foreach (['.env', '.env.example'] as $file) {
            $path = $projectRoot . '/' . $file;

            if (! is_file($path)) {
                continue;
            }

            $contents = file_get_contents($path);

            if ($contents === false || str_contains($contents, 'CASHFREE_APP_ID')) {
                continue;
            }

            $updated = rtrim($contents) . self::ENV_BLOCK . PHP_EOL;

            if (file_put_contents($path, $updated) !== false) {
                $io->write("  • Added Cashfree variables to {$file}");
            }
        }
    }

    public static function runArtisan(string $projectRoot, array $arguments, IOInterface $io): bool
    {
        $command = 'php artisan ' . implode(' ', array_map('escapeshellarg', $arguments));
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $projectRoot);

        if (! is_resource($process)) {
            return false;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if ($stdout !== false && $stdout !== '') {
            $io->write($stdout, false);
        }

        if ($stderr !== false && $stderr !== '') {
            $io->writeError($stderr, false);
        }

        return proc_close($process) === 0;
    }
}
