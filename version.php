<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Availability plugin for integration with Proctor by Constructor.
 *
 * @package    availability_proctor
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'availability_proctor';
$plugin->version = 2026102900;
$plugin->release = 'v2.8';
// Moodle 3.6, unchanged from v2.7. Deliberately not raised: this release adds
// nothing that needs a newer core, and raising the floor would withhold the upgrade
// from 3.6 and 3.7 sites that are running the Webapp model perfectly well today.
//
// What keeps that true: the external functions use the pre-4.2 external_api global
// namespace rather than core_external; the hook callbacks in db/hooks.php are simply
// not read by a core that predates the hooks API, exactly as in v2.7; and no syntax
// above PHP 7.0 is used anywhere.
//
// The SDK model needs 3.11, and that floor is enforced in code by
// config::SDK_MIN_VERSION / config::is_sdk_model() rather than declared here, so a
// site below it installs the plugin and runs the Webapp model instead of being
// refused the plugin altogether.
//
// No $plugin->supported is declared. That field is a closed range whose upper bound
// means "newer releases are not supported" -- and there is no upper bound here: both
// models keep working on current releases.
$plugin->requires = 2018111800;
$plugin->maturity = MATURITY_STABLE;
