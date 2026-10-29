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

namespace availability_proctor;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Outputs detailed info about log entry
 *
 * @package    availability_proctor
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_details {
    /**
     * @var int Entry id
     */
    protected $id = null;

    /**
     * @var string URL
     */
    protected $url = null;

    /**
     * @var string Source model for this numeric id.
     */
    protected $model = null;

    /**
     * Constructor
     * @param int $id Entry id
     * @param \moodle_url $url page URL the details page was reached from
     * @param string $model Source model. Missing legacy links are Webapp links.
     */
    public function __construct($id, $url, $model = config::MODEL_WEBAPP) {
        $this->id = $id;
        $this->url = $url;
        $this->model = $model;
    }

    /**
     * Renders and echoes log entry detail page
     */
    public function render() {
        global $DB, $OUTPUT;

        echo $OUTPUT->action_link(
            new \moodle_url('/availability/condition/proctor/index.php'),
            get_string('report_back', 'availability_proctor'),
            null,
            ['class' => 'btn btn-secondary availability-proctor-report-back']
        );
        echo \html_writer::start_div('availability-proctor-report-details');

        // The source travels with the link. The site's current setting controls new
        // exams only; it must not redirect an old row to a different table with the
        // same numeric id.
        if ($this->model === config::MODEL_SDK) {
            $this->render_sdk();
            echo \html_writer::end_div();
            return;
        }

        $entry = $DB->get_record('availability_proctor_entries', ['id' => $this->id]);
        if (!$entry) {
            echo $OUTPUT->notification(
                get_string('log_entry_missing', 'availability_proctor'),
                \core\output\notification::NOTIFY_ERROR
            );
            echo \html_writer::end_div();
            return;
        }
        $user = $DB->get_record('user', ['id' => $entry->userid]);

        $course = $DB->get_record('course', ['id' => $entry->courseid]);
        $cm = null;
        if (!empty($course)) {
            try {
                $cm = get_fast_modinfo($course)->get_cm($entry->cmid);
            } catch (\moodle_exception $e) {
                $cm = null;
            }
        }

        // NTG-185: the body of this parsing moved to parse_warnings() so that the
        // SDK page can reuse it. The logic is unchanged -- the SDK's resultwarnings
        // and warningtitles columns hold the same two shapes as the Webapp
        // warnings/warningstitles, because the same server writes both.
        $warnings = self::parse_warnings($entry->warnings, $entry->warningstitles);

        $table = new \flexible_table('availability_proctor_show');

        $table->define_columns(['key', 'value']);
        $table->define_headers([
            get_string('report_field', 'availability_proctor'),
            get_string('report_value', 'availability_proctor'),
        ]);
        $table->sortable(false);
        $table->set_attribute('class', 'generaltable availability-proctor-report-detail-table');
        $table->define_baseurl($this->url);
        $table->setup();

        $threshold = $entry->threshold ? json_decode($entry->threshold) : null;
        if (!is_object($threshold)) {
            $threshold = (object) ['attention' => null, 'rejected' => null];
        }

        $reviewlink = log::external_link(
            $entry->review_link,
            get_string('log_report_link', 'availability_proctor')
        );
        $archivelink = log::external_link(
            $entry->archiveurl,
            get_string('log_archive_link', 'availability_proctor')
        );

        if ($entry->attemptid) {
            $attempt = $DB->get_record('quiz_attempts', ['id' => $entry->attemptid]);
            if ($attempt) {
                $attempturl  = new \moodle_url('/mod/quiz/review.php', ['attempt' => $entry->attemptid]);
                $attemptlink = \html_writer::link(
                    $attempturl,
                    s((string) $entry->attemptid),
                    ['class' => 'btn btn-sm btn-outline-secondary']
                );
            } else {
                $attemptlink = s((string) $entry->attemptid) . ' ('
                    . get_string('log_attempt_missing', 'availability_proctor') . ')';
            }
        } else {
            $attemptlink = null;
        }

        $table->add_data([
            get_string('report_accesscode', 'availability_proctor'),
            s((string) $entry->accesscode),
        ]);

        $table->add_data([
            get_string('date_modified', 'availability_proctor'),
            common::format_date($entry->timemodified),
        ]);

        $table->add_data([
            get_string('time_scheduled', 'availability_proctor'),
            common::format_date($entry->timescheduled),
        ]);

        $table->add_data([
            get_string('log_model', 'availability_proctor'),
            log::render_model_badge(config::MODEL_WEBAPP),
        ]);

        $table->add_data([
            get_string('username'),
            $user ? s($user->username) : '',
        ]);

        $table->add_data([
            get_string('user'),
            $user ? log::render_user($user, (int) $entry->courseid) : '',
        ]);

        $table->add_data([
            get_string('course'),
            !empty($course) ? format_string($course->fullname) : '',
        ]);

        $table->add_data([
            get_string('module', 'availability_proctor'),
            $cm ? $cm->get_formatted_name() : '',
        ]);

        $table->add_data([
            get_string('status', 'availability_proctor'),
            log::render_status_badge($entry->status),
        ]);

        $table->add_data([
            get_string('report_source_status', 'availability_proctor'),
            log::render_source_status((string) $entry->status),
        ]);

        $table->add_data([
            get_string('log_review', 'availability_proctor'),
            \html_writer::div(
                implode('', array_filter([$reviewlink, $archivelink])),
                'availability-proctor-report-link-group'
            ),
        ]);

        $table->add_data([
            get_string('log_attempt', 'availability_proctor'),
            $attemptlink,
        ]);

        $table->add_data([
            get_string('score', 'availability_proctor'),
            log::render_score($entry->score, $entry->threshold),
        ]);

        $table->add_data([
            get_string('threshold_attention', 'availability_proctor'),
            s((string) $threshold->attention),
        ]);

        $table->add_data([
            get_string('threshold_rejected', 'availability_proctor'),
            s((string) $threshold->rejected),
        ]);

        $table->add_data([
            get_string('session_start', 'availability_proctor'),
            common::format_date($entry->sessionstart),
        ]);
        $table->add_data([
            get_string('session_end', 'availability_proctor'),
            common::format_date($entry->sessionend),
        ]);

        $table->add_data([
            get_string('comment', 'availability_proctor'),
            s((string) $entry->comment),
        ]);
        $table->print_html();

        // NTG-185: moved to render_warnings() so the SDK page renders warnings
        // identically. Body unchanged.
        $this->render_warnings($warnings);
        echo \html_writer::end_div();
    }

    /**
     * NTG-185: the details page for an SDK session.
     *
     * A sibling of render() because the two source tables have different fields.
     *
     * Presents the same rows in the same order where a row has an SDK counterpart,
     * so a reviewer who knows one page can read the other. Two rows are
     * deliberately absent and one is new; see plans/phase9.md.
     *
     * @return void
     */
    protected function render_sdk() {
        global $DB, $OUTPUT;

        $session = $DB->get_record(session::TABLE, ['id' => $this->id]);
        if (!$session) {
            echo $OUTPUT->notification(
                get_string('log_entry_missing', 'availability_proctor'),
                \core\output\notification::NOTIFY_ERROR
            );
            return;
        }

        $user = $DB->get_record('user', ['id' => $session->userid]);

        // The quiz is reached through the instance id, and may be gone: Phase 7's
        // cleanup removes these rows, but a details URL held open in a tab can
        // outlive it. IGNORE_MISSING rather than MUST_EXIST so the page degrades to
        // blank course and module cells instead of a stack trace.
        $cm = null;
        $course = null;
        if (!empty($session->quizid)) {
            $cminfo = get_coursemodule_from_instance('quiz', $session->quizid, 0, false, IGNORE_MISSING);
            if ($cminfo) {
                $course = $DB->get_record('course', ['id' => $cminfo->course]);
                if ($course) {
                    try {
                        $cm = get_fast_modinfo($course)->get_cm($cminfo->id);
                    } catch (\moodle_exception $e) {
                        $cm = null;
                    }
                }
            }
        }

        $warnings = self::parse_warnings($session->resultwarnings, $session->warningtitles);

        $threshold = $session->threshold ? json_decode($session->threshold) : null;
        if (!is_object($threshold)) {
            $threshold = (object) ['attention' => null, 'rejected' => null];
        }

        $links = [];
        foreach (['reporturl' => 'log_report_link', 'archiveurl' => 'log_archive_link'] as $field => $stringid) {
            $link = log::external_link($session->$field, get_string($stringid, 'availability_proctor'));
            if ($link !== '') {
                $links[] = $link;
            }
        }

        // A session may have no attempt at all -- it is created before the attempt
        // exists and binds later, so an abandoned launch never acquires one. That is
        // a normal state here rather than an error, unlike on the Webapp page.
        $attemptlink = null;
        if (!empty($session->attemptid)) {
            if ($DB->record_exists('quiz_attempts', ['id' => $session->attemptid])) {
                $attempturl = new \moodle_url('/mod/quiz/review.php', ['attempt' => $session->attemptid]);
                $attemptlink = \html_writer::link(
                    $attempturl,
                    s((string) $session->attemptid),
                    ['class' => 'btn btn-sm btn-outline-secondary']
                );
            } else {
                $attemptlink = s((string) $session->attemptid)
                    . ' (' . get_string('log_attempt_missing', 'availability_proctor') . ')';
            }
        }

        $table = new \flexible_table('availability_proctor_show');
        $table->define_columns(['key', 'value']);
        $table->define_headers([
            get_string('report_field', 'availability_proctor'),
            get_string('report_value', 'availability_proctor'),
        ]);
        $table->sortable(false);
        $table->set_attribute('class', 'generaltable availability-proctor-report-detail-table');

        $table->define_baseurl($this->url);
        $table->setup();

        // sessionkey is deliberately not shown. It is the JWT sessionId claim -- a
        // live credential for the Constructor side rather than an identifier for a
        // person to read. Phase 7 excluded it from the privacy export for the same
        // reason, and putting it on a screen would undo that.

        $table->add_data([
            get_string('date_modified', 'availability_proctor'),
            common::format_date($session->timemodified),
        ]);

        $table->add_data([
            get_string('log_model', 'availability_proctor'),
            log::render_model_badge(config::MODEL_SDK),
        ]);

        $table->add_data([
            get_string('username'),
            $user ? s($user->username) : '',
        ]);

        $table->add_data([
            get_string('user'),
            $user ? log::render_user($user, $course ? (int) $course->id : 0) : '',
        ]);

        $table->add_data([
            get_string('course'),
            $course ? format_string($course->fullname) : '',
        ]);

        $table->add_data([
            get_string('module', 'availability_proctor'),
            $cm ? $cm->get_formatted_name() : '',
        ]);

        $table->add_data([
            get_string('status', 'availability_proctor'),
            log::render_status_badge(log::sdk_status($session)),
        ]);

        $table->add_data([
            get_string('report_source_state', 'availability_proctor'),
            log::render_source_status((string) $session->state),
        ]);

        $table->add_data([
            get_string('report_source_conclusion', 'availability_proctor'),
            log::render_source_status((string) $session->conclusion),
        ]);

        // The row the status column cannot carry. Both ways a session reaches
        // abandoned are shown as Not submitted, and this is what tells them apart:
        // the student closed the window, or no attempt ever appeared.
        $table->add_data([
            get_string('log_reason', 'availability_proctor'),
            log::render_reason($session->reason),
        ]);

        $table->add_data([
            get_string('log_review', 'availability_proctor'),
            \html_writer::div(implode('', $links), 'availability-proctor-report-link-group'),
        ]);

        $table->add_data([
            get_string('log_attempt', 'availability_proctor'),
            $attemptlink,
        ]);

        $table->add_data([
            get_string('score', 'availability_proctor'),
            log::render_score($session->score, $session->threshold),
        ]);

        $table->add_data([
            get_string('threshold_attention', 'availability_proctor'),
            isset($threshold->attention) ? s((string) $threshold->attention) : '',
        ]);

        $table->add_data([
            get_string('threshold_rejected', 'availability_proctor'),
            isset($threshold->rejected) ? s((string) $threshold->rejected) : '',
        ]);

        $table->add_data([
            get_string('session_start', 'availability_proctor'),
            common::format_date($session->sessionstart),
        ]);

        $table->add_data([
            get_string('session_end', 'availability_proctor'),
            common::format_date($session->sessionend),
        ]);

        $table->add_data([
            get_string('comment', 'availability_proctor'),
            s((string) $session->comment),
        ]);

        $table->print_html();

        $this->render_warnings($warnings);
    }

    /**
     * NTG-185: decode the warnings a proctoring result carries.
     *
     * Extracted from render() unchanged, so that both models' pages present
     * warnings the same way. The SDK's resultwarnings / warningtitles columns hold
     * the same two shapes as the Webapp warnings / warningstitles, because the same
     * server writes both -- a list that may hold either objects or JSON strings, and
     * a per-type title map that may hold either a string or a language map.
     *
     * @param string|null $warningsjson The warnings list, as stored.
     * @param string|null $titlesjson The per-type title map, as stored.
     * @return array Decoded warnings.
     */
    protected static function parse_warnings($warningsjson, $titlesjson): array {
        $warnings = [];
        $lang = current_language();

        if (!$warningsjson) {
            $warningsraw = [];
        } else {
            $warningsraw = json_decode($warningsjson, true);
        }

        // A malformed list must not become a foreach over a string.
        if (!is_array($warningsraw)) {
            $warningsraw = [];
        }

        $titles = @json_decode((string) $titlesjson, true);
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

        return $warnings;
    }

    /**
     * NTG-185: render the warnings table.
     *
     * Extracted from render() unchanged so both models' pages share it.
     *
     * @param array $warnings Decoded warnings.
     * @return void
     */
    protected function render_warnings(array $warnings) {
        global $OUTPUT;

        if (count($warnings) == 0) {
            return;
        }

        echo $OUTPUT->render_from_template('availability_proctor/log_details_separator', [
            'heading' => get_string('log_details_warnings', 'availability_proctor'),
        ]);

        $table = new \flexible_table('availability_proctor_warnings');

        $table->define_columns(['type', 'title', 'start', 'end']);
        $table->define_headers([
            get_string('log_details_warning_type', 'availability_proctor'),
            get_string('log_details_warning_title', 'availability_proctor'),
            get_string('log_details_warning_start', 'availability_proctor'),
            get_string('log_details_warning_end', 'availability_proctor'),
        ]);
        $table->sortable(false);
        $table->set_attribute('class', 'generaltable availability-proctor-report-detail-table');
        $table->define_baseurl($this->url);
        $table->setup();

        foreach ($warnings as $warning) {
            $table->add_data([
                (isset($warning['type']) ? s((string) $warning['type']) : ''),
                (isset($warning['title']) ? s((string) $warning['title']) : ''),
                (isset($warning['start']) ? common::format_date($warning['start']) : ''),
                (isset($warning['end']) ? common::format_date($warning['end']) : ''),
            ]);
        }

        $table->print_html();
    }
}
