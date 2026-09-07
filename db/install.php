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
 * Post-install hook: seed default presets and pick the proctoring model.
 *
 * @package    availability_proctor
 * @copyright  2019-2024 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Seed the default presets and pick the proctoring model for a new install.
 *
 * @return void
 */
function xmldb_availability_proctor_install() {
    \availability_proctor\preset::seed_initial_presets();

    // A brand-new site has no exams to disturb, so it starts on the SDK model.
    // Written explicitly rather than left to the setting's default, which is the
    // Webapp model on purpose -- see settings.php and db/upgrade.php. An existing
    // site reaches the upgrade path instead and keeps the Webapp flow.
    set_config('proctormodel', \availability_proctor\config::MODEL_SDK, 'availability_proctor');
}
