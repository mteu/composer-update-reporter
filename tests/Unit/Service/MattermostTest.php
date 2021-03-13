<?php
declare(strict_types=1);
namespace EliasHaeussler\ComposerUpdateReporter\Tests\Unit\Service;

/*
 * This file is part of the Composer package "eliashaeussler/composer-update-reporter".
 *
 * Copyright (C) 2020 Elias Häußler <elias@haeussler.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use Composer\IO\BufferIO;
use EliasHaeussler\ComposerUpdateCheck\Package\OutdatedPackage;
use EliasHaeussler\ComposerUpdateCheck\Package\UpdateCheckResult;
use EliasHaeussler\ComposerUpdateReporter\Service\Mattermost;
use EliasHaeussler\ComposerUpdateReporter\Tests\Unit\AbstractTestCase;
use EliasHaeussler\ComposerUpdateReporter\Tests\Unit\ClientMockTrait;
use EliasHaeussler\ComposerUpdateReporter\Tests\Unit\TestEnvironmentTrait;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\Uri;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * MattermostTest
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
class MattermostTest extends AbstractTestCase
{
    use ClientMockTrait;
    use TestEnvironmentTrait;

    /**
     * @var Mattermost
     */
    protected $subject;

    protected function setUp(): void
    {
        $this->subject = new Mattermost(new Uri('https://example.org'), 'foo', 'baz');
    }

    /**
     * @test
     */
    public function constructorThrowsExceptionIfUriIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1600793015);

        new Mattermost(new Uri(''), 'foo');
    }

    /**
     * @test
     */
    public function constructorThrowsExceptionIfUriIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1600792942);

        new Mattermost(new Uri('foo'), 'foo');
    }

    /**
     * @test
     */
    public function constructorThrowsExceptionIfChannelNameIsInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1600793071);

        new Mattermost(new Uri('https://example.org'), '');
    }

    /**
     * @test
     * @dataProvider fromConfigurationThrowsExceptionIfMattermostUrlIsNotSetDataProvider
     * @param array $configuration
     */
    public function fromConfigurationThrowsExceptionIfMattermostUrlIsNotSet(array $configuration): void
    {
        $this->modifyEnvironmentVariable('MATTERMOST_URL');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1600283681);

        Mattermost::fromConfiguration($configuration);
    }

    /**
     * @test
     */
    public function fromConfigurationThrowsExceptionIfChannelNameIsNotSet(): void
    {
        $this->modifyEnvironmentVariable('MATTERMOST_CHANNEL');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1600284246);

        $configuration = [
            'mattermost' => [
                'url' => 'https://example.org',
            ],
        ];
        Mattermost::fromConfiguration($configuration);
    }

    /**
     * @test
     */
    public function fromConfigurationReadsConfigurationFromComposerJson(): void
    {
        $this->modifyEnvironmentVariable('MATTERMOST_URL');
        $this->modifyEnvironmentVariable('MATTERMOST_CHANNEL');
        $this->modifyEnvironmentVariable('MATTERMOST_USERNAME');

        $configuration = [
            'mattermost' => [
                'url' => 'https://example.org',
                'channel' => 'foo',
                'username' => 'baz',
            ],
        ];
        /** @var Mattermost $subject */
        $subject = Mattermost::fromConfiguration($configuration);

        static::assertInstanceOf(Mattermost::class, $subject);
        static::assertSame('https://example.org', (string)$subject->getUri());
        static::assertSame('foo', $subject->getChannelName());
        static::assertSame('baz', $subject->getUsername());
    }

    /**
     * @test
     */
    public function fromConfigurationReadsConfigurationFromEnvironmentVariables(): void
    {
        $this->modifyEnvironmentVariable('MATTERMOST_URL', 'https://example.org');
        $this->modifyEnvironmentVariable('MATTERMOST_CHANNEL', 'foo');
        $this->modifyEnvironmentVariable('MATTERMOST_USERNAME', 'baz');

        /** @var Mattermost $subject */
        $subject = Mattermost::fromConfiguration([]);

        static::assertInstanceOf(Mattermost::class, $subject);
        static::assertSame('https://example.org', (string)$subject->getUri());
        static::assertSame('foo', $subject->getChannelName());
        static::assertSame('baz', $subject->getUsername());
    }

    /**
     * @test
     * @dataProvider isEnabledReturnsStateOfAvailabilityDataProvider
     * @param array $configuration
     * @param $environmentVariable
     * @param bool $expected
     */
    public function isEnabledReturnsStateOfAvailability(array $configuration, $environmentVariable, bool $expected): void
    {
        $this->modifyEnvironmentVariable('MATTERMOST_ENABLE', $environmentVariable);

        static::assertSame($expected, Mattermost::isEnabled($configuration));
    }

    /**
     * @test
     * @throws ClientExceptionInterface
     */
    public function reportSkipsReportIfNoPackagesAreOutdated(): void
    {
        $result = new UpdateCheckResult([]);
        $io = new BufferIO();

        static::assertTrue($this->subject->report($result, $io));
        static::assertStringContainsString('Skipped Mattermost report.', $io->getOutput());
    }

    /**
     * @test
     * @dataProvider reportSendsUpdateReportSuccessfullyDataProvider
     * @param bool $insecure
     * @param string $expectedSecurityNotice
     * @throws ClientExceptionInterface
     */
    public function reportSendsUpdateReportSuccessfully(bool $insecure, string $expectedSecurityNotice): void
    {
        $result = new UpdateCheckResult([
            new OutdatedPackage('foo/foo', '1.0.0', '1.0.5', $insecure),
        ]);
        $io = new BufferIO();

        $this->subject->setClient($this->getClient());
        $this->mockHandler->append(new Response());

        static::assertTrue($this->subject->report($result, $io));
        static::assertStringContainsString('Mattermost report was successful.', $io->getOutput());

        $expectedPayloadSubset = [
            'channel' => 'foo',
            'attachments' => [
                [
                    'color' => '#EE0000',
                ],
            ],
            'username' => 'baz',
        ];
        $this->assertPayloadOfLastRequestContainsSubset($expectedPayloadSubset);

        $payload = $this->getPayloadOfLastRequest();
        $text = $payload['attachments'][0]['text'] ?? null;
        $expected = '[foo/foo](https://packagist.org/packages/foo/foo#1.0.5) | 1.0.0' . $expectedSecurityNotice . ' | **1.0.5**';
        static::assertStringContainsString($expected, $text);
    }

    /**
     * @test
     * @throws ClientExceptionInterface
     */
    public function reportsPrintsErrorOnErroneousReport(): void
    {
        $result = new UpdateCheckResult([
            new OutdatedPackage('foo/foo', '1.0.0', '1.0.5'),
        ]);
        $io = new BufferIO();

        $this->subject->setClient($this->getClient());
        $this->mockHandler->append(new Response(404));

        static::assertFalse($this->subject->report($result, $io));
        static::assertStringContainsString('Error during Mattermost report.', $io->getOutput());
    }

    public function fromConfigurationThrowsExceptionIfMattermostUrlIsNotSetDataProvider(): array
    {
        return [
            'no service configuration' => [
                [],
            ],
            'available service configuration' => [
                [
                    'mattermost' => [],
                ],
            ],
            'missing URL configuration' => [
                [
                    'mattermost' => [
                        'channel' => 'foo',
                    ],
                ],
            ],
        ];
    }

    public function isEnabledReturnsStateOfAvailabilityDataProvider(): array
    {
        return [
            'no configuration and no environment variable' => [
                [],
                null,
                false,
            ],
            'empty configuration and no environment variable' => [
                [
                    'mattermost' => [],
                ],
                null,
                false,
            ],
            'truthy configuration and no environment variable' => [
                [
                    'mattermost' => [
                        'enable' => true,
                    ],
                ],
                null,
                true,
            ],
            'truthy configuration and falsy environment variable' => [
                [
                    'mattermost' => [
                        'enable' => true,
                    ],
                ],
                '0',
                true,
            ],
            'falsy configuration and truthy environment variable' => [
                [
                    'mattermost' => [
                        'enable' => false,
                    ],
                ],
                '1',
                true,
            ],
            'empty configuration and truthy environment variable' => [
                [
                    'mattermost' => [],
                ],
                '1',
                true,
            ],
            'no configuration and truthy environment variable' => [
                [],
                '1',
                true,
            ],
        ];
    }

    public function reportSendsUpdateReportSuccessfullyDataProvider(): array
    {
        return [
            'secure package' => [
                false,
                '',
            ],
            'insecure package' => [
                true,
                ' :warning: **`insecure`**'
            ],
        ];
    }

    protected function tearDown(): void
    {
        $this->restoreEnvironmentVariables();
        parent::tearDown();
    }
}
