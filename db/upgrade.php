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

/**
 * availability proctor upgrade
 * @param string $oldversion Oldversion
 * @return bool
 */
function xmldb_availability_proctor_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026082601) {
        // Create the new presets table.
        $table = new xmldb_table('availability_proctor_presets');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('is_default', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('is_system', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'global');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('mode', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('schedulingrequired', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('autorescheduling', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('identification', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('checkidphotoquality', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('useragreementurl', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('preliminarycheck', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('webcameramainview', XMLDB_TYPE_CHAR, '10', null, null, null, null);
            $table->add_field('auxiliarycamera', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('allowroomscanauxcamera', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('streamspreset', XMLDB_TYPE_CHAR, '30', null, null, null, null);
            $table->add_field('sendmanualwarningstolearner', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('securebrowser', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('securebrowserlevel', XMLDB_TYPE_CHAR, '10', null, null, null, null);
            $table->add_field('allowedprocesses', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('forbiddenprocesses', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('allowvirtualenvironment', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('allowtouseadditionalresources', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('allowmultipledisplays', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('calculator', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('rules', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('warnings', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('scoring', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('type_default', XMLDB_INDEX_NOTUNIQUE, ['type', 'is_default']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082601, 'availability', 'proctor');
    }

    if ($oldversion < 2026082602) {
        // Seed the three recommended starter presets (idempotent).
        \availability_proctor\preset::seed_initial_presets();
        upgrade_plugin_savepoint(true, 2026082602, 'availability', 'proctor');
    }

    if ($oldversion < 2026082603) {
        // Rename previously-seeded system presets from their localized names
        // to locale-independent canonicals, so future upgrades dedupe correctly
        // regardless of $CFG->lang. Only matches rows still bearing a known
        // localized seed name AND flagged is_system=1 — admin-renamed rows
        // are left untouched.
        $renames = [
            \availability_proctor\preset::SEED_NAME_HIGH_STAKES => [
                'High-stakes exam', 'Экзамен с высокой ставкой',
            ],
            \availability_proctor\preset::SEED_NAME_LOW_STAKES => [
                'Low-stakes exam', 'Экзамен с низкой ставкой',
            ],
            \availability_proctor\preset::SEED_NAME_OPEN_BOOK => [
                'Open-book exam', 'Экзамен с открытыми материалами',
            ],
        ];
        foreach ($renames as $canonical => $aliases) {
            // Skip if the canonical name is already in use by another row.
            if ($DB->record_exists('availability_proctor_presets',
                    ['name' => $canonical, 'type' => 'global'])) {
                continue;
            }
            list($insql, $params) = $DB->get_in_or_equal($aliases, SQL_PARAMS_NAMED, 'alias');
            $params['type'] = 'global';
            $params['issystem'] = 1;
            $params['newname'] = $canonical;
            $DB->execute(
                "UPDATE {availability_proctor_presets}
                    SET name = :newname
                  WHERE type = :type AND is_system = :issystem AND name $insql",
                $params
            );
        }
        upgrade_plugin_savepoint(true, 2026082603, 'availability', 'proctor');
    }

    return true;
}
