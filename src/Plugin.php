<?php

declare(strict_types=1);


namespace Infection\ExtensionInstaller;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

final class Plugin implements PluginInterface, EventSubscriberInterface
{
    private const FILE_TEMPLATE = <<<'PHP'
<?php
declare(strict_types=1);

namespace Infection\ExtensionInstaller;

/**
 * This class is generated by infection/extension-installer
 */
final class GeneratedExtensionsConfig
{
    public const EXTENSIONS = %s;

    private function __construct()
    {
    }
}
PHP;

    public function activate(Composer $composer, IOInterface $io)
    {
        // no need to activate anything
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'process',
            ScriptEvents::POST_UPDATE_CMD => 'process',
        ];
    }

    public function process(Event $event): void
    {
        $io = $event->getIO();

        if (!file_exists(__DIR__)) {
            $io->write('<info>infection/extension-installer:</info> Package not found (probably scheduled for removal); extensions installation skipped.');

            return;
        }

        $composer = $event->getComposer();
        $installationManager = $composer->getInstallationManager();
        $infectionInstalledExtensions = [];
        $invalidInfectionExtensions = [];

        foreach ($composer->getRepositoryManager()->getLocalRepository()->getPackages() as $package) {
            if ($package->getType() !== 'infection-extension') {
                continue;
            }

            $extensionClass = $package->getExtra()['infection']['class'] ?? null;

            if ($extensionClass === null) {
                $invalidInfectionExtensions[$package->getName()] = '`class` is not specified under `extra.infection` key';

                continue;
            }

            $infectionInstalledExtensions[$package->getName()] = [
                'install_path' => $installationManager->getInstallPath($package),
                'extra' => $package->getExtra()['infection'] ?? null,
                'version' => $package->getFullPrettyVersion(),
            ];
        }

        ksort($infectionInstalledExtensions);

        $generatedConfigFilePath = __DIR__ . '/GeneratedExtensionsConfig.php';

        $generatedConfigFileContents = sprintf(self::FILE_TEMPLATE, var_export($infectionInstalledExtensions, true));
        file_put_contents($generatedConfigFilePath, $generatedConfigFileContents);

        $installedExtensionsCount = \count($infectionInstalledExtensions);
        $invalidExtensionsCount = \count($invalidInfectionExtensions);

        if ($installedExtensionsCount === 0 && $invalidExtensionsCount === 0) {
            $io->write('<info>infection/extension-installer:</info> No extensions found');
        }

        if ($installedExtensionsCount > 0) {
            $io->write('<info>infection/extension-installer:</info> Extensions installed');

            foreach (array_keys($infectionInstalledExtensions) as $name) {
                $io->write(sprintf('<comment>></comment> <info>%s:</info> installed', $name));
            }
        }

        if ($invalidExtensionsCount > 0) {
            $io->write('<info>infection/extension-installer:</info> Invalid extensions:');

            foreach ($invalidInfectionExtensions as $name => $reason) {
                $io->write(sprintf('<comment>></comment> <info>%s.</info> (%s)', $name, $reason));
            }
        }
    }
}
