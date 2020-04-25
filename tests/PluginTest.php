<?php
/**
 * This code is licensed under the BSD 3-Clause License.
 *
 * Copyright (c) 2017, Maks Rafalko
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * * Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 * * Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 * * Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

declare(strict_types=1);

namespace  Infection\ExtensionInstaller {
    function file_put_contents(): void
    {
        // do not write to FS to not override the file inside this package

        \Infection\ExtensionInstaller\Tests\PluginTest::$fileHasBeenWritten = true;
    }

    /**
     * @param array<mixed> $infectionInstalledExtensions
     */
    function var_export(array $infectionInstalledExtensions, bool $toReturn): void
    {
        // catch to test the result
        \Infection\ExtensionInstaller\Tests\PluginTest::$infectionInstalledExtensions = $infectionInstalledExtensions;
    }
}

namespace Infection\ExtensionInstaller\Tests {
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
    use Composer\Script\ScriptEvents;
    use Infection\ExtensionInstaller\Plugin;
    use PHPUnit\Framework\MockObject\MockObject;
    use PHPUnit\Framework\TestCase;

    final class PluginTest extends TestCase
    {
        /**
         * @var array<string, mixed>
         */
        public static $infectionInstalledExtensions = [];

        /**
         * @var bool
         */
        public static $fileHasBeenWritten = false;

        /**
         * @var IOInterface&MockObject
         */
        private $ioMock;

        protected function setUp(): void
        {
            parent::setUp();

            $this->ioMock = $this->createMock(IOInterface::class);
        }

        public function test_it_implements_plugin_interface(): void
        {
            $plugin = new Plugin();

            self::assertTrue(is_a($plugin, PluginInterface::class));
        }

        public function test_it_implements_event_subscriber_interface(): void
        {
            $plugin = new Plugin();

            self::assertTrue(is_a($plugin, EventSubscriberInterface::class));
        }

        public function test_it_subscribed_to_events(): void
        {
            $events = Plugin::getSubscribedEvents();

            self::assertSame(
                [
                    ScriptEvents::POST_INSTALL_CMD => 'process',
                    ScriptEvents::POST_UPDATE_CMD => 'process',
                ],
                $events
            );
        }

        public function test_it_displays_message_when_no_extensions_installed(): void
        {
            $plugin = new Plugin();

            $eventMock = $this->createEventMock([]);

            $this->ioMock->expects(self::once())
                ->method('write')
                ->with('<info>infection/extension-installer:</info> No extensions found');

            $plugin->process($eventMock);
        }

        public function test_it_displays_invalid_infection_extensions(): void
        {
            $plugin = new Plugin();

            $packages = [
                $this->cretePackage(
                    'infection/codeception-adapter',
                    'infection-extension',
                    [
                        'infection' => [/* class is not specified */],
                    ]
                ),
            ];

            $eventMock = $this->createEventMock($packages);

            $this->expectsOutput([
                '<info>infection/extension-installer:</info> Invalid extensions:',
                '<comment>></comment> <info>infection/codeception-adapter.</info> (`class` is not specified under `extra.infection` key)',
            ]);

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
                        'infection' => ['class' => 'Infection\Codeception\CodeceptionAdapterFactory'],
                    ]
                ),
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
                        'infection' => ['class' => 'Infection\Codeception\CodeceptionAdapterFactory'],
                    ]
                ),
                $this->cretePackage(
                    'infection/phpspec-adapter',
                    'infection-extension',
                    [
                        'infection' => ['class' => 'Infection\Codeception\PhpSpecAdapterFactory'],
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
                        'infection' => ['class' => 'Infection\Codeception\PhpSpecAdapterFactory'],
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
                        'infection' => ['class' => 'Infection\Codeception\C'],
                    ]
                ),
                $this->cretePackage(
                    'a',
                    'infection-extension',
                    [
                        'infection' => ['class' => 'Infection\Codeception\A'],
                    ]
                ),
                $this->cretePackage(
                    'b',
                    'infection-extension',
                    [
                        'infection' => ['class' => 'Infection\Codeception\B'],
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
                        'infection' => ['class' => 'Infection\Codeception\PhpSpecAdapterFactory'],
                    ]
                ),
            ];

            $eventMock = $this->createEventMock($packages);

            $plugin->process($eventMock);

            $reflectionClass = new \ReflectionClass($plugin);
            $dirWithFile = dirname((string) $reflectionClass->getFileName());

            self::assertFileExists(sprintf('%s/GeneratedExtensionsConfig.php', $dirWithFile));
            self::assertTrue(self::$fileHasBeenWritten);
            self::assertSame(
                [
                    'infection/phpspec-adapter' => [
                        'install_path' => '/path/to/installed/package',
                        'extra' => [
                            'class' => 'Infection\Codeception\PhpSpecAdapterFactory',
                        ],
                        'version' => 'v1.2.3',
                    ],
                ],
                self::$infectionInstalledExtensions
            );
        }

        public function test_it_skips_invalid_extensions_without_class_specified(): void
        {
            $plugin = new Plugin();

            $packages = [
                $this->cretePackage(
                    'infection/c',
                    'infection-extension',
                    [
                        'infection' => ['class' => 'Infection\Codeception\C'],
                    ]
                ),
                $this->cretePackage(
                    'a',
                    'infection-extension'
                ),
                $this->cretePackage(
                    'infection/b',
                    'infection-extension',
                    [
                        'infection' => ['class' => 'Infection\Codeception\B'],
                    ]
                ),
            ];

            $eventMock = $this->createEventMock($packages);

            $this->expectsOutput([
                '<info>infection/extension-installer:</info> Extensions installed',
                '<comment>></comment> <info>infection/b:</info> installed',
                '<comment>></comment> <info>infection/c:</info> installed',
            ]);

            $plugin->process($eventMock);

            self::assertSame(
                [
                    'infection/b' => [
                        'install_path' => '/path/to/installed/package',
                        'extra' => [
                            'class' => 'Infection\Codeception\B',
                        ],
                        'version' => 'v1.2.3',
                    ],
                    'infection/c' => [
                        'install_path' => '/path/to/installed/package',
                        'extra' => [
                            'class' => 'Infection\Codeception\C',
                        ],
                        'version' => 'v1.2.3',
                    ],
                ],
                self::$infectionInstalledExtensions
            );
        }

        /**
         * @param PackageInterface[] $packages
         *
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

        /**
         * @param array<string, mixed> $extra
         */
        private function cretePackage(string $name, string $type, array $extra = []): PackageInterface
        {
            $package = new Package($name, '1.2.3', 'v1.2.3');

            $package->setType($type);
            $package->setExtra($extra);

            return $package;
        }

        /**
         * @param string[] $outputMessages
         */
        private function expectsOutput(array $outputMessages): void
        {
            foreach ($outputMessages as $index => $outputMessage) {
                $this->ioMock->expects(self::at($index))
                    ->method('write')
                    ->with($outputMessage);
            }
        }
    }
}
