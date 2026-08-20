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

/**
 * availability proctor upgrade
 * @param string $oldversion Oldversion
 * @return bool
 */
function xmldb_availability_proctor_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026041300) {
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

        upgrade_plugin_savepoint(true, 2026041300, 'availability', 'proctor');
    }

    if ($oldversion < 2026041517) {
        // Seed the three recommended starter presets (idempotent).
        \availability_proctor\preset::seed_initial_presets();
        upgrade_plugin_savepoint(true, 2026041517, 'availability', 'proctor');
    }

    if ($oldversion < 2026051101) {
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
            if (
                $DB->record_exists(
                    'availability_proctor_presets',
                    ['name' => $canonical, 'type' => 'global']
                )
            ) {
                continue;
            }
            [$insql, $params] = $DB->get_in_or_equal($aliases, SQL_PARAMS_NAMED, 'alias');
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
        upgrade_plugin_savepoint(true, 2026051101, 'availability', 'proctor');
    }

    if ($oldversion < 2026081700) {
        // NTG-185 introduces a second proctoring model. This site already has
        // exams running under the hosted one, so pin it explicitly.
        //
        // Explicitly, not by relying on the setting's default: get_config()
        // returns an admin_setting's declared default when nothing has been
        // written, so leaving the row absent would make the site's behaviour
        // depend on a value in settings.php that a later release might change.
        // Writing it means the choice is recorded in the database, where an admin
        // can see it and change it, and where nothing later can move it silently.
        //
        // Guarded so an admin who has already chosen a model -- or a site that
        // somehow gets here twice -- is not overridden.
        if (get_config('availability_proctor', 'proctormodel') === false) {
            set_config('proctormodel', \availability_proctor\config::MODEL_HOSTED, 'availability_proctor');
        }
        upgrade_plugin_savepoint(true, 2026081700, 'availability', 'proctor');
    }

    if ($oldversion < 2026081701) {
        // NTG-185: the two tables the SDK proctoring model keeps its sessions in.
        //
        // Both are empty after this step and stay empty on any site running the
        // hosted model, which is every site that upgrades. Nothing reads or writes
        // them until the routing branch exists, so this step cannot change the
        // behaviour of an exam.
        //
        // No data migration. A site that also ran the standalone quizaccess_proctorsdk
        // plugin has its sessions under quizaccess_proctorsdk_sess -- a different
        // component's tables, which that plugin may still be writing to and which
        // Moodle removes when it is uninstalled. Adopting those rows here would mean
        // two plugins owning one dataset.
        //
        // Keep every field, key and index identical to db/install.xml. A drift
        // between the two produces a different schema depending on whether the site
        // was installed or upgraded, and that only surfaces when a query fails on
        // one of them.

        // One proctoring session per quiz attempt.
        $table = new xmldb_table('availability_proctor_sess');
        if (!$dbman->table_exists($table)) {
            // attemptid is nullable because the session is created before the
            // attempt exists: the student is handed off to core's startattempt.php,
            // which runs the pre-flight chain and only then creates the attempt.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            // 230 is the maximum the Developer Guide allows for the sessionId claim.
            $table->add_field('sessionkey', XMLDB_TYPE_CHAR, '230', null, XMLDB_NOTNULL, null, null);
            $table->add_field('state', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('reason', XMLDB_TYPE_CHAR, '100', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            // One stamp per state entry, each written only on first entry.
            $table->add_field('timeactive', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timebound', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timefinishing', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timefinished', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timeabandoned', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            // Outside the state machine: both can resolve after the row is terminal.
            $table->add_field('videouploaded', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timefinishsent', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            // Written by the result webhook.
            $table->add_field('conclusion', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('score', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('comment', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('threshold', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('reporturl', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('archiveurl', XMLDB_TYPE_TEXT, null, null, null, null, null);
            // Named apart from the per-exam visibleWarnings setting, which is a
            // different thing entirely.
            $table->add_field('resultwarnings', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('warningtitles', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('sessionstart', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('sessionend', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timeresult', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

            $table->add_index('sessionkey', XMLDB_INDEX_UNIQUE, ['sessionkey']);
            // Deliberately not unique. The column is nullable, and databases
            // disagree on whether a unique index permits several NULLs -- MySQL
            // allows it -- and that difference must not decide whether two unbound
            // sessions can coexist. One session per attempt is enforced instead by
            // the conditional UPDATE in session::bind_attempt(), which is portable
            // and also settles the race between two simultaneous binds.
            $table->add_index('attemptid', XMLDB_INDEX_NOTUNIQUE, ['attemptid']);
            $table->add_index('quizid_userid_state', XMLDB_INDEX_NOTUNIQUE, ['quizid', 'userid', 'state']);

            $dbman->create_table($table);
        }

        // Blocked access attempts. This release ships no writer for this table --
        // every caller of session::record_denial() lives in the access rule that
        // was not carried across. It is created because session.php and the
        // deletion observers reference it, and because it is where mid-attempt
        // enforcement would record to if it is ever added, which would otherwise
        // mean a second schema migration for customers.
        $table = new xmldb_table('availability_proctor_deny');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('attemptid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            // No foreign key on sessionid: the session row may be pruned while the
            // denial record is kept.
            $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('sessionstate', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('reason', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('script', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

            $table->add_index(
                'attemptid_reason_time',
                XMLDB_INDEX_NOTUNIQUE,
                ['attemptid', 'reason', 'timecreated']
            );

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026081701, 'availability', 'proctor');
    }

    if ($oldversion < 2026081702) {
        // The presets table, for sites arriving from the GitHub v2.7 line.
        //
        // Every block that builds the preset system is guarded on a version below
        // 2026070200 -- 2026041300 creates the table, 2026041517 seeds it, 2026051101
        // normalises the seeded names. Those guards work for a site that followed the
        // GitLab line, which passed through each of those versions in turn. A site
        // coming from this repository's v2.7 has 2026070200 recorded and is *past* all
        // three, so Moodle skips them and the table is never created.
        //
        // This has to run before 2026081709, and that ordering is the whole reason the
        // block sits here rather than at the end of the file. 2026081709 calls
        // field_exists() on this table, and database_manager::field_exists() throws
        // ddl_table_missing_exception when the table is absent -- it does not answer
        // false. Measured on Moodle 4.5.13 upgrading a seeded v2.7 site: the upgrade
        // died with 'Table "availability_proctor_presets" does not exist' and stopped
        // with the plugin half-upgraded.
        //
        // 2026081702 and not a later number for a second reason: a site that already
        // tried the build without this block is now sitting on savepoint 2026081701,
        // and a fix numbered at or below that would never be reached on the retry.
        $table = new xmldb_table('availability_proctor_presets');

        if (!$dbman->table_exists($table)) {
            // Created from install.xml rather than by restating the columns, so there
            // is one schema definition rather than two that can drift. The file holds
            // the current shape, which is what a table being created now should look
            // like -- so the sdk* columns 2026081709 adds and 2026082000 drops are
            // already absent. That churn then runs over an empty table: sixteen
            // columns added and dropped again, no rows, no data, nothing to carry.
            // Left in place deliberately rather than guarded away, because it is the
            // same path the GitLab line already runs, and a new conditional here would
            // be a path nothing has exercised.
            $dbman->install_one_table_from_xmldb_file(
                $CFG->dirroot . '/availability/condition/proctor/db/install.xml',
                'availability_proctor_presets'
            );
        }

        upgrade_plugin_savepoint(true, 2026081702, 'availability', 'proctor');
    }

    if ($oldversion < 2026081709) {
        // NTG-185: the SDK's init() parameters, as preset columns.
        //
        // On a preset rather than on the settings page, because that is where every
        // other exam setting in this plugin lives -- the integration settings page
        // holds connection details only. An earlier build put them on that page and
        // broke the pattern; it also ran into Moodle drawing an unwritten checkbox as
        // unticked beside the words "Default: Yes", which a save then made permanent.
        //
        // Every column is nullable with no default, and NULL is load-bearing: it means
        // "this preset says nothing about the parameter", so the shipped default
        // applies. That is what keeps the three existing system presets behaving
        // exactly as they do today -- they get sixteen NULLs and nothing about them
        // changes.
        $table = new xmldb_table('availability_proctor_presets');

        // yes/no, or NULL to say nothing. Stored as text rather than as an integer
        // because a nullable integer would make 0 and "unset" easy to confuse, and
        // this is precisely the distinction the three-state fields exist to keep.
        $threestate = [
            'sdkallowcameraselection', 'sdkalwaysshowchat', 'sdkbeforeexamlist',
            'sdkdesktopcheckfirst', 'sdkexperimentwatchwindowsize',
            'sdkfocusrespectsrules', 'sdkfullscreenwatchfocusalert',
            'sdkopenagreementlinksiniframe', 'sdkaudionotification',
            'sdkshowfinishexambutton', 'sdkshowsyschecksupportnew',
            // Room Scan seconds, also as text: '0' means "hide the screen", which has
            // to stay distinguishable from NULL.
            'sdkobserveworkspace',
        ];
        foreach ($threestate as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_CHAR, '10', null, null, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $textfields = [
            'sdksyscheckhelpurl', 'sdkdisabledworkers',
            'sdkidentificationbullets', 'sdktranslationsoverride',
        ];
        foreach ($textfields as $name) {
            $field = new xmldb_field($name, XMLDB_TYPE_TEXT, null, null, null, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026081709, 'availability', 'proctor');
    }

    if ($oldversion < 2026081712) {
        // NTG-185 follow-up: drop the over-long column this plugin briefly shipped.
        //
        // 'sdkshouldenableaudionotification' is 32 characters and Moodle's DDL layer
        // refuses anything over 30 (sql_generator::getFieldSQL, lib/ddl). The check
        // lives in add_field(), so install.xml went straight to the database and a
        // clean install came up fine with a 32-character column -- MySQL's own limit
        // is 64 -- while every *upgrade* died on this exact field with
        // "name is too long. Limit is 30 chars." Measured 2026-08-18 on both paths:
        // clean install created the column, the upgrade raised a coding_exception and
        // stopped mid-way, leaving the plugin half-upgraded.
        //
        // The column is now 'sdkaudionotification'. This step removes the old one
        // where a clean install of the affected build created it. No data moves: the
        // column arrived with NTG-185, no released version ever wrote to it, and all
        // three system presets held NULL when measured. The add_field() loop above is
        // guarded by field_exists(), so it has already created the new column by the
        // time this runs.
        $table = new xmldb_table('availability_proctor_presets');
        $oldfield = new xmldb_field('sdkshouldenableaudionotification', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        if ($dbman->field_exists($table, $oldfield)) {
            $dbman->drop_field($table, $oldfield);
        }

        upgrade_plugin_savepoint(true, 2026081712, 'availability', 'proctor');
    }

    if ($oldversion < 2026081902) {
        // NTG-185 follow-up: give every preset a value for the six SDK parameters an
        // exam can vary.
        //
        // The exam form used to offer a third "Use the default" option, so a NULL
        // column was a legitimate state meaning "fall through to what Constructor
        // ships". The form now offers Yes/No only and opens on the preset's value,
        // which leaves a NULL column with nothing to preselect -- so the presets that
        // already exist have to be filled in.
        //
        // Filled with the same values preset::default_field_values() seeds, which are
        // config::SDK_CONFIG -- exactly what a NULL column resolved to before. No
        // preset changes behaviour: this writes down what was already being applied.
        //
        // Only NULL and empty columns are touched. A site that set one of these on the
        // preset form keeps its value.
        $defaults = \availability_proctor\preset::default_field_values();

        foreach (\availability_proctor\config::SDK_PER_QUIZ as $prop) {
            if (!isset($defaults[$prop])) {
                continue;
            }
            $DB->execute(
                "UPDATE {availability_proctor_presets}
                    SET {$prop} = ?
                  WHERE {$prop} IS NULL OR {$prop} = ''",
                [$defaults[$prop]]
            );
        }

        upgrade_plugin_savepoint(true, 2026081902, 'availability', 'proctor');
    }

    if ($oldversion < 2026082000) {
        // NTG-185 follow-up: the SDK parameters move from preset columns to site
        // settings, so the columns go.
        //
        // No data is carried across. These columns arrived with NTG-185 and exist
        // only on this branch -- no released version ever had them, so nothing a
        // customer configured is stored here. A site that set them while testing
        // re-enters them on the integration settings page.
        //
        // Why they moved: ten of the sixteen were readable only through the exam's
        // preset_id, and that link turned out to be fragile in three measured ways.
        // Editing any field on the activity form dropped it, which reset all ten to
        // the shipped defaults; preset::is_used_by_exams() finds exams by the same
        // link, so a preset every exam had been edited away from counted as unused
        // and became deletable; and a site that did not want presets could not reach
        // the ten at all. A site setting is always present and nothing can unlink
        // from it. See config::SITE_SDK_SETTINGS.
        $table = new xmldb_table('availability_proctor_presets');

        $columns = [
            'sdkallowcameraselection', 'sdkalwaysshowchat', 'sdkbeforeexamlist',
            'sdkdesktopcheckfirst', 'sdkdisabledworkers', 'sdkexperimentwatchwindowsize',
            'sdkfocusrespectsrules', 'sdkfullscreenwatchfocusalert',
            'sdkidentificationbullets', 'sdkobserveworkspace',
            'sdkopenagreementlinksiniframe', 'sdkaudionotification',
            'sdkshowfinishexambutton', 'sdkshowsyschecksupportnew',
            'sdksyscheckhelpurl', 'sdktranslationsoverride',
        ];

        foreach ($columns as $name) {
            $field = new xmldb_field($name);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026082000, 'availability', 'proctor');
    }

    if ($oldversion < 2026082003) {
        // Seed the three system presets, for sites arriving from the GitHub v2.7 line.
        //
        // The companion to 2026081702, which created the table. Seeding is separate
        // and deliberately later: it writes rows, so it wants the schema in its final
        // shape, which it only reaches after 2026081709, 2026081712 and 2026082000
        // have run. Creating the table has the opposite constraint -- it has to come
        // before those three, because they read it.
        //
        // Without this the failure surfaces away from the upgrade, which reports
        // success: the first teacher to open an activity's restriction form reaches
        // frontend::get_javascript_init_params(), which calls preset::get_default(),
        // and finds no default preset to return.
        //
        // Guarded on existence rather than on a version, so it is reached by every
        // site. Idempotent: seed_preset_if_missing() keys on the preset name, so a
        // site that already has them gains nothing and loses nothing. Also covers a
        // site whose table exists but is empty -- a failed earlier upgrade, a manual
        // truncate -- which is left with a working plugin rather than an empty picker.
        $table = new xmldb_table('availability_proctor_presets');

        if (!$dbman->table_exists($table)) {
            // Belt and braces. 2026081702 creates the table for every site that can
            // reach this line, so nothing should get here without it; if something
            // does, seeding into a missing table would be the second failure in a row.
            $dbman->install_one_table_from_xmldb_file(
                $CFG->dirroot . '/availability/condition/proctor/db/install.xml',
                'availability_proctor_presets'
            );
        }

        \availability_proctor\preset::seed_initial_presets();

        upgrade_plugin_savepoint(true, 2026082003, 'availability', 'proctor');
    }

    if ($oldversion < 2026082004) {
        // Carry the old site-wide defaults into a preset.
        //
        // Before presets, this plugin held one set of site defaults as a single
        // JSON blob in config_plugins under 'default_proctoring_settings', written
        // by defaults.php and read by frontend.php to prefill a new restriction.
        // Presets replaced both. Nothing reads the blob any more, so without this
        // an upgrading site keeps the row and loses the behaviour: every new
        // restriction would start from the shipped Low-stakes preset instead of
        // whatever the institution had configured.
        //
        // Migrated as a normal, editable preset (is_system = 0) rather than a
        // seeded one, because it is the site's own configuration and an
        // administrator should be able to rename or delete it.
        $json = get_config('availability_proctor', 'default_proctoring_settings');
        $blob = empty($json) ? null : json_decode($json);

        // A name collision would make preset::save() throw and fail the whole
        // upgrade, so an existing row wins and this does nothing. Relevant when
        // the savepoint below was not reached on an earlier attempt.
        $migratedname = 'Site defaults (migrated)';
        $alreadythere = $DB->record_exists(\availability_proctor\preset::TABLE, [
            'name' => $migratedname,
            'type' => \availability_proctor\preset::TYPE_GLOBAL,
        ]);

        if (is_object($blob) && !$alreadythere) {
            // Only the fields a preset can hold. duration, customrules and groups
            // are in the blob but are exam-level in this model, with no preset
            // column to put them in -- see condition::PRESET_FIELDS. Their site
            // defaults are not carried across, and that is a real loss rather than
            // an oversight: there is nowhere for them to go.
            $fields = [];
            foreach (\availability_proctor\condition::PRESET_FIELDS as $key) {
                if (isset($blob->$key)) {
                    $fields[$key] = $blob->$key;
                }
            }

            if ($fields) {
                // Layered over default_field_values() so a column the blob says
                // nothing about lands on the same value a seeded preset carries,
                // rather than NULL.
                $data = (object) array_merge(
                    \availability_proctor\preset::default_field_values(),
                    $fields
                );
                $data->name = $migratedname;
                $data->type = \availability_proctor\preset::TYPE_GLOBAL;
                $data->is_system = 0;

                // Made the default, which is the whole point: these values were
                // what every new restriction started from, and they should carry
                // on being that. preset::save() clears the flag on the seeded
                // preset for us.
                $data->is_default = 1;

                \availability_proctor\preset::save($data);
            }
        }

        // The config row is deliberately left in place. Nothing reads it, so it is
        // inert, and keeping it means this migration is reversible by hand and the
        // original values are still recoverable if the mapping turns out wrong.
        upgrade_plugin_savepoint(true, 2026082004, 'availability', 'proctor');
    }

    return true;
}
