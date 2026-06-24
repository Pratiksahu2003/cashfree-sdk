<?php

namespace CashfreePayment\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class InstallPlugin implements PluginInterface, EventSubscriberInterface
{
    private const PACKAGE_NAME = 'pratiksahu2003/cashfree-sdk';

    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'handleComposerFinish',
            ScriptEvents::POST_UPDATE_CMD => 'handleComposerFinish',
        ];
    }

    public function handleComposerFinish(Event $event): void
    {
        $composer = $event->getComposer();
        $vendorDir = $composer->getConfig()->get('vendor-dir');

        if (! is_dir($vendorDir . '/' . self::PACKAGE_NAME)) {
            return;
        }

        $projectRoot = dirname($vendorDir);

        if (! LaravelInstaller::isLaravelProject($projectRoot)) {
            return;
        }

        $io = $event->getIO();
        $io->write('<info>Cashfree SDK:</info> Running automatic Laravel setup...');

        LaravelInstaller::publishConfig($projectRoot, $io);
        LaravelInstaller::appendEnvVariables($projectRoot, $io);
        LaravelInstaller::runArtisan($projectRoot, ['package:discover', '--ansi'], $io);
        LaravelInstaller::runArtisan($projectRoot, ['config:clear', '--ansi'], $io);

        $io->write('<info>Cashfree SDK:</info> Setup complete. Add your Cashfree credentials to <comment>.env</comment>.');
    }
}
