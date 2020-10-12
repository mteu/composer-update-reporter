<?php
declare(strict_types=1);
namespace EliasHaeussler\ComposerUpdateReporter\Service;

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

use Composer\IO\IOInterface;
use EliasHaeussler\ComposerUpdateCheck\OutdatedPackage;
use EliasHaeussler\ComposerUpdateCheck\UpdateCheckResult;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\UriInterface;
use Spatie\Emoji\Emoji;
use Spatie\Emoji\Exceptions\UnknownCharacter;

/**
 * Slack
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
class Slack implements ServiceInterface
{
    /**
     * @var UriInterface
     */
    private $uri;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var bool
     */
    private $json = false;

    public function __construct(UriInterface $uri)
    {
        $this->uri = $uri;
        $this->client = new Client(['base_uri' => (string) $this->uri]);

        $this->validateUri();
    }

    public static function fromConfiguration(array $configuration): ServiceInterface
    {
        $extra = $configuration['slack'] ?? null;

        // Parse Slack URL
        if (is_array($extra) && array_key_exists('url', $extra)) {
            $uri = new Uri((string)$extra['url']);
        } else if (getenv('SLACK_URL') !== false) {
            $uri = new Uri(getenv('SLACK_URL'));
        } else {
            throw new \RuntimeException(
                'Slack URL is not defined. Define it either in composer.json or as $SLACK_URL.',
                1602496964
            );
        }

        return new self($uri);
    }

    public static function isEnabled(array $configuration): bool
    {
        if (getenv('SLACK_ENABLE') !== false && (bool)getenv('SLACK_ENABLE')) {
            return true;
        }
        $extra = $configuration['slack'] ?? null;
        return is_array($extra) && (bool)($extra['enable'] ?? false);
    }

    public function report(UpdateCheckResult $result, IOInterface $io): bool
    {
        $outdatedPackages = $result->getOutdatedPackages();

        // Do not send report if packages are up to date
        if ($outdatedPackages === []) {
            if (!$this->json) {
                $io->write(Emoji::crossMark() . ' Skipped Slack report.');
            }
            return true;
        }

        // Build JSON payload
        $payload = [
            'blocks' => $this->renderBlocks($outdatedPackages),
        ];

        // Send report
        if (!$this->json) {
            $io->write(Emoji::rocket() . ' Sending report to Slack...');
        }
        $response = $this->client->post('', [RequestOptions::JSON => $payload]);
        $successful = $response->getStatusCode() < 400;

        // Print report state
        if (!$successful) {
            $io->writeError(Emoji::crossMark() . ' Error during Slack report.');
        } else if (!$this->json) {
            try {
                $checkMark = Emoji::checkMark();
            } /** @noinspection PhpRedundantCatchClauseInspection */ catch (UnknownCharacter $e) {
                /** @noinspection PhpUndefinedMethodInspection */
                $checkMark = Emoji::heavyCheckMark();
            }
            $io->write($checkMark . ' Slack report was successful.');
        }

        return $successful;
    }

    /**
     * @param OutdatedPackage[] $outdatedPackages
     * @return array
     */
    private function renderBlocks(array $outdatedPackages): array
    {
        $count = count($outdatedPackages);
        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => sprintf('%d outdated package%s', $count, $count !== 1 ? 's' : ''),
                ],
            ],
        ];
        foreach ($outdatedPackages as $outdatedPackage) {
            $block = [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => '*Package*',
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => sprintf(
                            '<https://packagist.org/packages/%s|%s>',
                            $outdatedPackage->getName(),
                            $outdatedPackage->getName()
                        ),
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => '*Current version*',
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => sprintf('`%s`', $outdatedPackage->getOutdatedVersion()),
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => '*New version*',
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => sprintf('*`%s`*', $outdatedPackage->getNewVersion()),
                    ],
                ],
            ];
            if (method_exists($outdatedPackage, 'isInsecure') && $outdatedPackage->isInsecure()) {
                $block['fields'][] = [
                    'type' => 'mrkdwn',
                    'text' => '*Security state*',
                ];
                $block['fields'][] = [
                    'type' => 'mrkdwn',
                    'text' => '*Package is insecure* :warning:',
                ];
            }
            $blocks[] = [
                'type' => 'divider',
            ];
            $blocks[] = $block;
        }
        return $blocks;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function setJson(bool $json): ServiceInterface
    {
        $this->json = $json;
        return $this;
    }

    private function validateUri(): void
    {
        $uri = (string) $this->uri;
        if (trim($uri) === '') {
            throw new \InvalidArgumentException('Slack URL must not be empty.', 1602496937);
        }
        if (filter_var($uri, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Slack URL is no valid URL.', 1602496941);
        }
    }
}
