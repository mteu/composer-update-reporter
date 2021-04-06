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

use EliasHaeussler\ComposerUpdateCheck\IO\OutputBehavior;
use EliasHaeussler\ComposerUpdateCheck\Options;
use EliasHaeussler\ComposerUpdateCheck\Package\UpdateCheckResult;

/**
 * ServiceInterface
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
interface ServiceInterface
{
    /**
     * @param array $configuration
     * @return static
     */
    public static function fromConfiguration(array $configuration): self;

    /**
     * @param array $configuration
     * @return bool
     */
    public static function isEnabled(array $configuration): bool;

    /**
     * @param UpdateCheckResult $result
     * @return bool
     */
    public function report(UpdateCheckResult $result): bool;

    /**
     * @param OutputBehavior $behavior
     * @return static
     */
    public function setBehavior(OutputBehavior $behavior): self;

    /**
     * @param Options $options
     * @return static
     */
    public function setOptions(Options $options): self;
}
