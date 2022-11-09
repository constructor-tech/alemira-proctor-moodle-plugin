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
 * Availability plugin for integration with Examus proctoring system.
 *
 * @package    availability_examus2
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_examus2;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Outputs detailed info about log entry
 */
class log_details {
    /**
     * @var integer Entry id
     */
    protected $id = null;

    /**
     * @var string URL
     */
    protected $url = null;

    /**
     * Constructor
     * @param integer $id Entry id
     * @param string $url
     */
    public function __construct($id, $url) {
        $this->id = $id;
    }

    /**
     * Renders and echoes log entry detail page
     */
    public function render() {
        global $DB;
        $entry = $DB->get_record('availability_examus2_entries', ['id' => $this->id]);
        $user = $DB->get_record('user', ['id' => $entry->userid]);

        $course = $DB->get_record('course', ['id' => $entry->courseid]);
        if (!empty($course)) {
            $modinfo = get_fast_modinfo($course);
            $cm = $modinfo->get_cm($entry->cmid);
        }

        $warnings = [];
        $lang = current_language();

        if (!$entry->warnings) {
            $warningsraw = [];
        } else {
            $warningsraw = json_decode($entry->warnings, true);
        }

        $titles = @json_decode($entry->warningstitles, true);
        if (empty($titles) || !is_array($titles)) {
            $titles = [];
        }

        foreach ($warningsraw as $warningraw) {
            $warning = [];
            if (is_string($warningraw)) {
                $warning = @json_decode($warningraw, true);
                if (!$warning) {
                    $warning = ['type' => $warningraw];
                }
            } else {
                $warning = $warningraw;
            }

            $type  = isset($warning['type']) ? $warning['type'] : null;
            $title = isset($titles[$type]) ? $titles[$type] : null;

            if (is_array($title)) {
                $localized = null;

                // Try current language.
                if (isset($title[$lang])) {
                    $localized = $title[$lang];
                }

                // Default to english.
                if (isset($title['en']) && !$localized) {
                    $localized = $title['en'];
                }

                // Default to first.
                if (!$localized) {
                    $localized = reset($title);
                }

                $warning['title'] = $localized;
            }

            if (isset($warning['start']) && !is_numeric($warning['start'])) {
                $warning['start'] = common::parse_date($warning['start']);
            }

            if (isset($warning['end']) && !is_numeric($warning['end'])) {
                $warning['end'] = common::parse_date($warning['end']);
            }

            $warnings[] = $warning;
        }

        $table = new \flexible_table('availability_examus2_show');

        $table->define_columns(['key', 'value']);
        $table->define_headers(['Key', 'Value']);
        $table->sortable(false);
        $table->set_attribute('class', 'generaltable generalbox');
        $table->define_baseurl($this->url);
        $table->setup();

        $threshold = $entry->threshold ? json_decode($entry->threshold) : (object)['attention' => null, 'rejected' => null];

        if ($entry->review_link !== null) {
            $reviewlink = "<a href='" . $entry->review_link . "'>" . get_string('log_report_link', 'availability_examus2') . "</a>";
        } else {
            $reviewlink = null;
        }

        if ($entry->archiveurl !== null) {
            $archivelink = "<a href='" . $entry->archiveurl . "'>" . get_string('log_archive_link', 'availability_examus2') . "</a>";
        } else {
            $archivelink = null;
        }

        if ($entry->attemptid) {
            $attempt = $DB->get_record('quiz_attempts', ['id' => $entry->attemptid]);
            if ($attempt) {
                $attempturl  = new \moodle_url('/mod/quiz/review.php', ['attempt' => $entry->attemptid]);
                $attemptlink = '<a href="' . $attempturl . '">' . $entry->attemptid . '</a>';
            } else {
                $attemptlink = $entry->attemptid . ' ('. get_string('log_attempt_missing', 'availability_examus2') . ')';
            }
        } else {
            $attemptlink = null;
        }


        $table->add_data([
            'accesscode',
            $entry->accesscode
        ]);

        $table->add_data([
            get_string('date_modified', 'availability_examus2'),
            common::format_date($entry->timemodified)
        ]);

        $table->add_data([
            get_string('time_scheduled', 'availability_examus2'),
            common::format_date($entry->timescheduled)
        ]);

        $table->add_data([
            get_string('user'),
            $user->firstname . " " . $user->lastname . "<br>" . $user->email
        ]);

        $table->add_data([
            get_string('course'),
            !empty($course) ? $course->fullname : null,
        ]);

        $table->add_data([
            get_string('module', 'availability_examus2'),
            !empty($course) ? $cm->get_formatted_name() : null,
        ]);

        $table->add_data([
            get_string('status', 'availability_examus2'),
            $entry->status,
        ]);

        $table->add_data([
            get_string('log_review', 'availability_examus2'),
            implode(', ', array_filter([$reviewlink, $archivelink])),
        ]);

        $table->add_data([
            get_string('log_attempt', 'availability_examus2'),
            $attemptlink,
        ]);

        $table->add_data([
            get_string('score', 'availability_examus2'),
            $entry->score,
        ]);

        $table->add_data([
            get_string('threshold_attention', 'availability_examus2'),
            $threshold->attention,
        ]);

        $table->add_data([
            get_string('threshold_rejected', 'availability_examus2'),
            $threshold->rejected,
        ]);

        $table->add_data([
            get_string('session_start', 'availability_examus2'),
            common::format_date($entry->sessionstart),
        ]);
        $table->add_data([
            get_string('session_end', 'availability_examus2'),
            common::format_date($entry->sessionend),
        ]);

        $table->add_data([
            get_string('comment', 'availability_examus2'),
            $entry->comment,
        ]);
        $table->print_html();

        if (count($warnings) == 0) {
            return;
        }

        echo "<hr>";
        echo "<h2>".get_string('log_details_warnings', 'availability_examus2')."</h2>";

        $table = new \flexible_table('availability_examus2_show');

        $table->define_columns(['type', 'title', 'start', 'end']);
        $table->define_headers([
            get_string('log_details_warning_type', 'availability_examus2'),
            get_string('log_details_warning_title', 'availability_examus2'),
            get_string('log_details_warning_start', 'availability_examus2'),
            get_string('log_details_warning_end', 'availability_examus2'),
        ]);
        $table->sortable(false);
        $table->set_attribute('class', 'generaltable generalbox');
        $table->define_baseurl($this->url);
        $table->setup();

        foreach ($warnings as $warning) {
            $table->add_data([
                (isset($warning['type']) ? $warning['type'] : ''),
                (isset($warning['title']) ? $warning['title'] : ''),
                (isset($warning['start']) ? common::format_date($warning['start']) : ''),
                (isset($warning['end']) ? common::format_date($warning['end']) : '')
            ]);
        }

        $table->print_html();
    }
}
