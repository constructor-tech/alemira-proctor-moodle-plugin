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
 * Per-brand catalog of system presets seeded on install/upgrade.
 *
 * This file ships the Constructor catalog (three starter presets). The release
 * script overlays a brand-specific copy from branding/<brand>/classes/preset_seed.php
 * when packaging a non-default variant.
 *
 * Contract: definitions() returns an array of entries, each
 *   ['name' => string, 'is_default' => bool, 'fields' => array]
 * where 'fields' is a complete preset record (defaults filled from
 * preset::default_field_values()).
 */
class preset_seed {
    public static function definitions() {
        $base = preset::default_field_values();

        // Low-stakes: paper notes are not allowed.
        $low_stakes_rules = $base['rules'];
        $low_stakes_rules['allow_to_use_paper'] = false;

        // Open-book: websites, books, and look-away are allowed.
        $open_book_rules = $base['rules'];
        $open_book_rules['allow_to_use_websites'] = true;
        $open_book_rules['allow_to_use_books'] = true;
        $open_book_rules['allow_wrong_gaze_direction'] = true;

        return [
            // High-stakes: Live + Face & ID + Secure Browser (medium) + Aux camera.
            [
                'name' => preset::SEED_NAME_HIGH_STAKES,
                'is_default' => false,
                'fields' => array_merge($base, [
                    'mode' => 'online',
                    'sendmanualwarningstolearner' => 1,
                    'identification' => 'face_and_passport',
                    'checkidphotoquality' => 1,
                    'auxiliarycamera' => 1,
                    'securebrowser' => 1,
                    'securebrowserlevel' => 'medium',
                ]),
            ],
            // Low-stakes (default): Review + Only face, no Secure Browser, no aux cam.
            [
                'name' => preset::SEED_NAME_LOW_STAKES,
                'is_default' => true,
                'fields' => array_merge($base, [
                    'mode' => 'offline',
                    'sendmanualwarningstolearner' => 0,
                    'identification' => 'face',
                    'checkidphotoquality' => 0,
                    'auxiliarycamera' => 0,
                    'securebrowser' => 0,
                    'securebrowserlevel' => 'basic',
                    'rules' => $low_stakes_rules,
                ]),
            ],
            // Open-book: Review + Only face + websites/books/look-away allowed.
            [
                'name' => preset::SEED_NAME_OPEN_BOOK,
                'is_default' => false,
                'fields' => array_merge($base, [
                    'mode' => 'offline',
                    'sendmanualwarningstolearner' => 0,
                    'identification' => 'face',
                    'checkidphotoquality' => 0,
                    'auxiliarycamera' => 0,
                    'securebrowser' => 0,
                    'securebrowserlevel' => 'basic',
                    'rules' => $open_book_rules,
                ]),
            ],
        ];
    }
}
