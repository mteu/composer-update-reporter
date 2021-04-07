<?php

declare(strict_types=1);

namespace EliasHaeussler\ComposerUpdateReporter;

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

use Composer\Composer;
use Composer\IO\NullIO;
use EliasHaeussler\ComposerUpdateCheck\IO\OutputBehavior;
use EliasHaeussler\ComposerUpdateCheck\IO\Style;
use EliasHaeussler\ComposerUpdateCheck\IO\Verbosity;
use EliasHaeussler\ComposerUpdateCheck\Options;
use EliasHaeussler\ComposerUpdateCheck\Package\UpdateCheckResult;
use EliasHaeussler\ComposerUpdateReporter\Exception\InvalidServiceException;
use EliasHaeussler\ComposerUpdateReporter\Service\Email;
use EliasHaeussler\ComposerUpdateReporter\Service\GitLab;
use EliasHaeussler\ComposerUpdateReporter\Service\Mattermost;
use EliasHaeussler\ComposerUpdateReporter\Service\ServiceInterface;
use EliasHaeussler\ComposerUpdateReporter\Service\Slack;
use EliasHaeussler\ComposerUpdateReporter\Service\Teams;

/**
 * Reporter.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
class Reporter
{
    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var OutputBehavior
     */
    private $behavior;

    /**
     * @var Options
     */
    private $options;

    /**
     * @var string[]
     */
    private $registeredServices;

    /**
     * @var array<string, mixed>
     */
    private $configuration;

    public function __construct(Composer $composer)
    {
        $this->composer = $composer;
        $this->behavior = $this->getDefaultBehavior();
        $this->options = new Options();
        $this->registeredServices = $this->getDefaultServices();
        $this->configuration = $this->resolveConfiguration();
    }

    public function report(UpdateCheckResult $result): void
    {
        $services = $this->buildServicesFromConfiguration();
        foreach ($services as $service) {
            $service->report($result);
        }
    }

    /**
     * @return ServiceInterface[]
     */
    private function buildServicesFromConfiguration(): array
    {
        $services = [];
        /** @var ServiceInterface $registeredService */
        foreach ($this->registeredServices as $registeredService) {
            if ($registeredService::isEnabled($this->configuration)) {
                $service = $registeredService::fromConfiguration($this->configuration);
                $service->setBehavior($this->behavior);
                $service->setOptions($this->options);
                $services[] = $service;
            }
        }

        return $services;
    }

    public function setBehavior(OutputBehavior $behavior): void
    {
        $this->behavior = $behavior;
    }

    public function setOptions(Options $options): void
    {
        $this->options = $options;
    }

    /**
     * @throws InvalidServiceException
     */
    public function registerService(string $service): self
    {
        if (!in_array(ServiceInterface::class, class_implements($service), true)) {
            throw InvalidServiceException::create($service);
        }

        if (!in_array($service, $this->registeredServices, true)) {
            $this->registeredServices[] = $service;
        }

        return $this;
    }

    public function unregisterService(string $service): self
    {
        if (($key = array_search($service, $this->registeredServices, true)) !== false) {
            unset($this->registeredServices[$key]);
        }

        return $this;
    }

    /**
     * @return string[]
     */
    private function getDefaultServices(): array
    {
        return [
            Email::class,
            GitLab::class,
            Mattermost::class,
            Slack::class,
            Teams::class,
        ];
    }

    private function getDefaultBehavior(): OutputBehavior
    {
        return new OutputBehavior(
            new Style(Style::NORMAL),
            new Verbosity(Verbosity::NORMAL),
            new NullIO()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveConfiguration(): array
    {
        return $this->composer->getPackage()->getExtra()['update-check'] ?? [];
    }
}
