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
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_proctor;

defined('MOODLE_INTERNAL') || die();

/**
 * Certory preset catalog: one default preset, fully declarative.
 *
 * Applied via the release script's branding overlay; replaces the default
 * (Constructor) preset_seed.php in the Certory build.
 */
class preset_seed {
    public static function definitions() {
        return [
            [
                'name' => 'Certory default preset',
                'is_default' => true,
                'fields' => [
                    // Mode + camera + identification (key Certory config).
                    'mode' => 'online',
                    'webcameramainview' => 'front',
                    'auxiliarycamera' => 1,
                    'identification' => 'passport',

                    // All other AI-related verification params disabled.
                    'checkidphotoquality' => 0,
                    'preliminarycheck' => 0,
                    'sendmanualwarningstolearner' => 0,

                    // Camera-related.
                    'allowroomscanauxcamera' => 0,

                    // Streams / Calculator / Allowed extras.
                    'streamspreset' => 'default',
                    'calculator' => 'off',
                    'allowtouseadditionalresources' => 0,
                    'allowmultipledisplays' => 0,
                    'allowvirtualenvironment' => 0,
                    'useragreementurl' => null,

                    // Secure browser off.
                    'securebrowser' => 0,
                    'securebrowserlevel' => 'basic',
                    'allowedprocesses' => null,
                    'forbiddenprocesses' => null,

                    // Allowed items during exam: all off except paper.
                    'rules' => [
                        'allow_to_use_websites' => false,
                        'allow_to_use_books' => false,
                        'allow_to_use_paper' => false,
                        'allow_to_use_messengers' => false,
                        'allow_to_use_calculator' => false,
                        'allow_to_use_excel' => false,
                        'allow_to_use_human_assistant' => false,
                        'allow_absence_in_frame' => false,
                        'allow_voices' => false,
                        'allow_wrong_gaze_direction' => false,
                    ],

                    // AI warnings: all off.
                    'warnings' => [
                        'warning_extra_user_in_frame' => false,
                        'warning_substitution_user' => false,
                        'warning_no_user_in_frame' => false,
                        'warning_avert_eyes' => false,
                        'warning_change_active_window_on_computer' => false,
                        'warning_forbidden_device' => false,
                        'warning_voice_detected' => false,
                        'warning_phone' => false,
                    ],

                    // Scoring weights: all null (no AI scoring).
                    'scoring' => [
                        'cheater_level' => null,
                        'extra_user' => null,
                        'user_replaced' => null,
                        'absent_user' => null,
                        'look_away' => null,
                        'active_window_changed' => null,
                        'forbidden_device' => null,
                        'voice' => null,
                        'phone' => null,
                    ],
                ],
            ],
        ];
    }
}
