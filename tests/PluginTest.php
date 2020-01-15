<?php

declare(strict_types=1);

namespace Infection\ExtensionInstaller\Tests;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Repository\RepositoryManager;
use Composer\Repository\WritableRepositoryInterface;
use Composer\Script\Event;
use Infection\ExtensionInstaller\GeneratedExtensionsConfig;
use Infection\ExtensionInstaller\Plugin;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class PluginTest extends TestCase
{
    /**
     * @var IOInterface&MockObject
     */
    private $ioMock;

    /**
     * @var Event&MockObject
     */
    private $eventMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ioMock = $this->createMock(IOInterface::class);
    }

    public function test_it_implements_plugin_interface(): void
    {
        $plugin = new Plugin();

        self::assertInstanceOf(PluginInterface::class, $plugin);
    }

    public function test_it_implements_event_subscriber_interface(): void
    {
        $plugin = new Plugin();

        self::assertInstanceOf(EventSubscriberInterface::class, $plugin);
    }

    public function test_it_displays_message_when_no_extensions_installed(): void
    {
        $plugin = new Plugin();

        $eventMock = $this->createEventMock([]);

        $this->ioMock->expects(self::once())->method('write')->with('<info>infection/extension-installer:</info> No extensions found');

        $plugin->process($eventMock);
    }

    public function test_it_displays_message_when_extensions_installed(): void
    {
        $plugin = new Plugin();

        $packages = [
            $this->cretePackage(
                'infection/codeception-adapter',
                'infection-extension',
                [
                    'infection' => ['class' => 'Infection\Codeception\CodeceptionAdapterFactory']
                ]
            )
        ];

        $eventMock = $this->createEventMock($packages);

        $this->expectsOutput([
            '<info>infection/extension-installer:</info> Extensions installed',
            '<comment>></comment> <info>infection/codeception-adapter:</info> installed',
        ]);

        $plugin->process($eventMock);
    }

    public function test_it_can_install_more_than_one_extension(): void
    {
        $plugin = new Plugin();

        $packages = [
            $this->cretePackage(
                'infection/codeception-adapter',
                'infection-extension',
                [
                    'infection' => ['class' => 'Infection\Codeception\CodeceptionAdapterFactory']
                ]
            ),
            $this->cretePackage(
                'infection/phpspec-adapter',
                'infection-extension',
                [
                    'infection' => ['class' => 'Infection\Codeception\PhpSpecAdapterFactory']
                ]
            ),
        ];

        $eventMock = $this->createEventMock($packages);

        $this->expectsOutput([
            '<info>infection/extension-installer:</info> Extensions installed',
            '<comment>></comment> <info>infection/codeception-adapter:</info> installed',
            '<comment>></comment> <info>infection/phpspec-adapter:</info> installed',
        ]);

        $plugin->process($eventMock);
    }

    public function test_it_correctly_skips_non_infection_extensions(): void
    {
        $plugin = new Plugin();

        $packages = [
            $this->cretePackage(
                'symfony/process',
                'library'
            ),
            $this->cretePackage(
                'infection/phpspec-adapter',
                'infection-extension',
                [
                    'infection' => ['class' => 'Infection\Codeception\PhpSpecAdapterFactory']
                ]
            ),
        ];

        $eventMock = $this->createEventMock($packages);

        $this->expectsOutput([
            '<info>infection/extension-installer:</info> Extensions installed',
            '<comment>></comment> <info>infection/phpspec-adapter:</info> installed',
        ]);

        $plugin->process($eventMock);
    }

    public function test_sorts_output_of_installed_extensions(): void
    {
        $plugin = new Plugin();

        $packages = [
            $this->cretePackage(
                'c',
                'infection-extension',
                [
                    'infection' => ['class' => 'Infection\Codeception\C']
                ]
            ),
            $this->cretePackage(
                'a',
                'infection-extension',
                [
                    'infection' => ['class' => 'Infection\Codeception\A']
                ]
            ),
            $this->cretePackage(
                'b',
                'infection-extension',
                [
                    'infection' => ['class' => 'Infection\Codeception\B']
                ]
            ),
        ];

        $eventMock = $this->createEventMock($packages);

        $this->expectsOutput([
            '<info>infection/extension-installer:</info> Extensions installed',
            '<comment>></comment> <info>a:</info> installed',
            '<comment>></comment> <info>b:</info> installed',
            '<comment>></comment> <info>c:</info> installed',
        ]);

        $plugin->process($eventMock);
    }

    public function test_it_writes_the_file_to_file_system(): void
    {
        $plugin = new Plugin();

        $packages = [
            $this->cretePackage(
                'symfony/process',
                'library'
            ),
            $this->cretePackage(
                'infection/phpspec-adapter',
                'infection-extension',
                [
                    'infection' => ['class' => 'Infection\Codeception\PhpSpecAdapterFactory']
                ]
            ),
        ];

        $eventMock = $this->createEventMock($packages);

        $plugin->process($eventMock);

        $reflectionClass = new \ReflectionClass($plugin);
        $dirWithFile = dirname($reflectionClass->getFileName());

        self::assertFileExists(sprintf('%s/GeneratedExtensionsConfig.php', $dirWithFile));

        // todo commit it to git
        // //todo check constants
        var_dump(GeneratedExtensionsConfig::EXTENSIONS);
    }

    /**
     * @param PackageInterface[] $packages
     * @return Event&MockObject
     */
    private function createEventMock(array $packages): MockObject
    {
        $eventMock = $this->createMock(Event::class);
        $eventMock->method('getIO')->willReturn($this->ioMock);

        $localRepositoryMock = $this->createMock(WritableRepositoryInterface::class);
        $localRepositoryMock->method('getPackages')->willReturn($packages);

        $repositoryManagerMock = $this->createMock(RepositoryManager::class);
        $repositoryManagerMock->method('getLocalRepository')->willReturn($localRepositoryMock);

        $installationManagerMock = $this->createMock(InstallationManager::class);
        $installationManagerMock->method('getInstallPath')->willReturn('/path/to/installed/package');

        $composerMock = $this->createMock(Composer::class);
        $composerMock->method('getRepositoryManager')->willReturn($repositoryManagerMock);
        $composerMock->method('getInstallationManager')->willReturn($installationManagerMock);

        $eventMock->method('getComposer')->willReturn($composerMock);

        return $eventMock;
    }

    private function cretePackage(string $name, string $type, array $extra = []): PackageInterface
    {
        $package = new Package($name, '1.2.3', 'v1.2.3');

        $package->setType($type);
        $package->setExtra($extra);

        return $package;
    }

    private function expectsOutput(array $outputMessages): void
    {
        foreach ($outputMessages as $index => $outputMessage) {
            $this->ioMock->expects(self::at($index))
                ->method('write')
                ->with($outputMessage);
        }
    }
}
