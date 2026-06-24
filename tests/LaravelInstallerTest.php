<?php

namespace CashfreePayment\Tests;

use CashfreePayment\Composer\LaravelInstaller;
use PHPUnit\Framework\TestCase;

class LaravelInstallerTest extends TestCase
{
    public function test_append_env_variables_adds_cashfree_block(): void
    {
        $root = sys_get_temp_dir() . '/cashfree-installer-' . uniqid();
        mkdir($root, 0755, true);

        file_put_contents($root . '/.env', "APP_NAME=Laravel\n");

        $io = new TestIO();
        LaravelInstaller::appendEnvVariables($root, $io);

        $env = file_get_contents($root . '/.env');

        $this->assertStringContainsString('CASHFREE_APP_ID=', $env);
        $this->assertStringContainsString('CASHFREE_SECRET_KEY=', $env);
        $this->assertStringContainsString('CASHFREE_ENV=sandbox', $env);

        $this->unlinkRecursive($root);
    }

    public function test_append_env_variables_skips_when_already_present(): void
    {
        $root = sys_get_temp_dir() . '/cashfree-installer-' . uniqid();
        mkdir($root, 0755, true);

        file_put_contents($root . '/.env', "CASHFREE_APP_ID=existing\n");

        $io = new TestIO();
        LaravelInstaller::appendEnvVariables($root, $io);

        $this->assertSame("CASHFREE_APP_ID=existing\n", file_get_contents($root . '/.env'));
        $this->assertSame([], $io->messages);

        $this->unlinkRecursive($root);
    }

    public function test_is_laravel_project_requires_artisan_and_bootstrap_app(): void
    {
        $root = sys_get_temp_dir() . '/cashfree-installer-' . uniqid();
        mkdir($root, 0755, true);
        touch($root . '/artisan');

        $this->assertFalse(LaravelInstaller::isLaravelProject($root));

        mkdir($root . '/bootstrap', 0755, true);
        touch($root . '/bootstrap/app.php');

        $this->assertTrue(LaravelInstaller::isLaravelProject($root));

        $this->unlinkRecursive($root);
    }

    private function unlinkRecursive(string $path): void
    {
        if (! is_dir($path)) {
            @unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->unlinkRecursive($path . '/' . $item);
        }

        @rmdir($path);
    }
}

class TestIO extends \Composer\IO\NullIO
{
    public array $messages = [];

    public function write($messages, $newline = true, $verbosity = self::NORMAL): void
    {
        $this->messages[] = (string) $messages;
    }
}
