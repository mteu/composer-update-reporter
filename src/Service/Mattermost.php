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

use EliasHaeussler\ComposerUpdateCheck\OutdatedPackage;
use EliasHaeussler\ComposerUpdateCheck\UpdateCheckResult;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\UriInterface;

/**
 * Mattermost
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
class Mattermost implements ServiceInterface
{
    /**
     * @var UriInterface
     */
    private $uri;

    /**
     * @var string
     */
    private $channelName;

    /**
     * @var string|null
     */
    private $username;

    public function __construct(UriInterface $uri, string $channelName, string $username = null)
    {
        $this->uri = $uri;
        $this->channelName = $channelName;
        $this->username = $username;
    }

    public static function fromConfiguration(array $configuration): ServiceInterface
    {
        $extra = $configuration['mattermost'] ?? null;

        // Parse Mattermost URL
        if (is_array($extra) && array_key_exists('url', $extra)) {
            $uri = new Uri((string)$extra['url']);
        } else if (getenv('MATTERMOST_URL') !== false) {
            $uri = new Uri(getenv('MATTERMOST_URL'));
        } else {
            throw new \RuntimeException(
                'Mattermost URL is not defined. Define it either in composer.json or as $MATTERMOST_URL.',
                1600283681
            );
        }

        // Parse Mattermost channel name
        if (is_array($extra) && array_key_exists('channel', $extra)) {
            $channelName = (string)$extra['channel'];
        } else if (getenv('MATTERMOST_CHANNEL') !== false) {
            $channelName = getenv('MATTERMOST_CHANNEL');
        } else {
            throw new \RuntimeException(
                'Mattermost channel name is not defined. Define it either in composer.json or as $MATTERMOST_CHANNEL.',
                1600284246
            );
        }

        // Parse Mattermost username
        $username = null;
        if (is_array($extra) && array_key_exists('username', $extra)) {
            $username = (string)$extra['username'];
        } else if (getenv('MATTERMOST_USERNAME') !== false) {
            $username = getenv('MATTERMOST_USERNAME');
        }

        return new self($uri, $channelName, $username);
    }

    public function report(UpdateCheckResult $result): bool
    {
        $outdatedPackages = $result->getOutdatedPackages();
        $client = new Client(['base_uri' => $this->uri]);

        // Do not send report if packages are up to date
        if ($outdatedPackages === []) {
            return true;
        }

        // Build JSON
        $json = [
            'channel' => $this->channelName,
            'attachments' => [
                [
                    'color' => '#EE0000',
                    'text' => $this->renderText($outdatedPackages),
                ],
            ],
        ];
        if ($this->username !== null) {
            $json['username'] = $this->username;
        }

        // Send report
        $response = $client->post('', [RequestOptions::JSON => $json]);
        return $response->getStatusCode() < 400;
    }

    /**
     * @param OutdatedPackage[] $outdatedPackages
     * @return string
     */
    private function renderText(array $outdatedPackages): string
    {
        $count = count($outdatedPackages);
        $textParts = [
            sprintf('#### :rotating_light: %d outdated package%s', $count, $count !== 1 ? 's' : ''),
            '| Package | Current version | New version |',
            '|:------- |:--------------- |:----------- |',
        ];
        foreach ($outdatedPackages as $outdatedPackage) {
            $textParts[] = sprintf(
                '| [%s](https://packagist.org/packages/%s) | %s | **%s** |',
                $outdatedPackage->getName(),
                $outdatedPackage->getName(),
                $outdatedPackage->getOutdatedVersion(),
                $outdatedPackage->getNewVersion()
            );
        }
        return implode(PHP_EOL, $textParts);
    }
}
