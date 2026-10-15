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
use html_writer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Displays and filters log entries
 *
 * @package    availability_proctor
 * @copyright  2019-2022 Maksim Burnin <maksim.burnin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log {
    /**
     * @var array Entries to display
     */
    protected $entries = [];

    /**
     * @var int Total count of entries
     */
    protected $entriescount = null;

    /**
     * @var int
     */
    protected $perpage = 50;

    /**
     * @var int
     */
    protected $page = 0;

    /**
     * @var \flexible_table
     */
    protected $table = null;

    /**
     * @var string URL
     */
    protected $url = null;

    /**
     * @var array list of filters
     */
    protected $filters = null;

    /**
     * Constructor
     * @param array $filters Filters
     * @param integer $page Page
     */
    public function __construct($filters, $page) {
        global $PAGE;

        $this->url = $PAGE->url;
        $this->filters = $filters;
        $this->page = $page;

        $this->url->params($filters);

        $this->setup_table();
        $this->fetch_data();
    }

    /**
     * Fetches data for log table
     */
    protected function fetch_data() {
        global $DB;

        $courseids = array_keys($this->get_course_list());
        // PHP 7.0 requires long list syntax.
        // phpcs:disable Universal.Lists.DisallowLongListSyntax.Found
        list($webappsql, $webappcountsql, $webappparams) = $this->webapp_query($courseids);
        list($sdksql, $sdkcountsql, $sdkparams) = $this->sdk_query($courseids);

        // Each source keeps its own table and lifecycle. UNION ALL only creates a
        // common read view; it does not migrate, duplicate or modify either row.
        // get_recordset_sql() is intentional: get_records_sql() keys its result by
        // the first selected column, so Webapp id=7 and SDK id=7 would overwrite
        // one another in PHP even though SQL returned both.
        $query = $webappsql . ' UNION ALL ' . $sdksql
               . ' ORDER BY ' . $this->report_sort();
        $params = array_merge($webappparams, $sdkparams);

        $recordset = $DB->get_recordset_sql(
            $query,
            $params,
            $this->page * $this->perpage,
            $this->perpage
        );
        foreach ($recordset as $entry) {
            $this->entries[] = $entry;
        }
        $recordset->close();

        $this->entriescount = $DB->count_records_sql($webappcountsql, $webappparams)
                            + $DB->count_records_sql($sdkcountsql, $sdkparams);
        $this->table->pagesize($this->perpage, $this->entriescount);
    }

    /**
     * Build the normalized Webapp half of the report query.
     *
     * @param array $courseids Courses the current reviewer may access.
     * @return array Data SQL, count SQL and parameters.
     */
    protected function webapp_query(array $courseids): array {
        global $DB;

        $filters = $this->filters;
        $where = [];
        $params = [];

        $fromtimestamp = $this->date_filter_timestamp(isset($filters['fromdate']) ? $filters['fromdate'] : '');
        if ($fromtimestamp !== null) {
            $params['webfrom'] = $fromtimestamp;
            $where[] = '(a.timefinish > :webfrom OR a.timefinish IS NULL OR a.timefinish = 0)';
        }

        $totimestamp = $this->date_filter_timestamp(isset($filters['todate']) ? $filters['todate'] : '', true);
        if ($totimestamp !== null) {
            $params['webto'] = $totimestamp;
            $where[] = '(a.timefinish <= :webto OR a.timefinish IS NULL OR a.timefinish = 0)';
        }

        if (!empty($filters['courseid'])) {
            $where[] = 'e.courseid = :webcourseid';
            $params['webcourseid'] = $filters['courseid'];
        }
        if (!empty($filters['timefinish'])) {
            $where[] = 'a.timefinish = :webtimefinish';
            $params['webtimefinish'] = $filters['timefinish'];
        }
        if (!empty($filters['moduleid'])) {
            $where[] = 'e.cmid = :webmoduleid';
            $params['webmoduleid'] = $filters['moduleid'];
        }
        if (!empty($filters['userquery'])) {
            $value = trim($filters['userquery']);
            $likevalue = '%' . $DB->sql_like_escape($value) . '%';
            foreach (['email', 'username', 'firstname', 'lastname'] as $field) {
                $params['webuser' . $field] = $likevalue;
            }
            $where[] = '(' . $DB->sql_like('u.email', ':webuseremail', false)
                     . ' OR ' . $DB->sql_like('u.username', ':webuserusername', false)
                     . ' OR ' . $DB->sql_like('u.firstname', ':webuserfirstname', false)
                     . ' OR ' . $DB->sql_like('u.lastname', ':webuserlastname', false) . ')';
        }
        if (!empty($filters['status'])) {
            list($statussql, $statusparams) = $this->webapp_status_condition(
                trim($filters['status']),
                'webdisplay'
            );
            $where[] = $statussql;
            $params = array_merge($params, $statusparams);
        }

        if ($courseids) {
            list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'webcid');
            $where[] = 'e.courseid ' . $insql;
            $params = array_merge($params, $inparams);
        } else {
            $where[] = '1=0';
        }

        $from = ' FROM {availability_proctor_entries} e '
              . ' LEFT JOIN {user} u ON u.id = e.userid '
              . ' LEFT JOIN {quiz_attempts} a ON a.id = e.attemptid ';
        $wheresql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $select = "SELECT e.id AS id, '" . config::MODEL_WEBAPP . "' AS proctormodel, "
                . 'e.timecreated AS timecreated, '
                . 'COALESCE(a.timefinish, 0) AS sorttime, '
                . 'a.timefinish AS timefinish, e.timescheduled AS timescheduled, '
                . 'u.firstname AS u_firstname, u.lastname AS u_lastname, '
                . 'u.email AS u_email, u.username AS u_username, u.id AS userid, '
                . 'e.status AS status, e.review_link AS review_link, '
                . 'e.archiveurl AS archiveurl, e.cmid AS cmid, '
                . 'e.courseid AS courseid, e.score AS score, '
                . 'e.threshold AS threshold, a.id AS attemptid, '
                . 'a.state AS attemptstate, 1 AS islatest';

        return [
            $select . $from . $wheresql,
            'SELECT COUNT(e.id)' . $from . $wheresql,
            $params,
        ];
    }

    /**
     * Build the normalized SDK half of the report query.
     *
     * @param array $courseids Courses the current reviewer may access.
     * @return array Data SQL, count SQL and parameters.
     */
    protected function sdk_query(array $courseids): array {
        global $DB;

        $filters = $this->filters;
        $where = [];
        $params = ['sdkmodname' => 'quiz'];
        $finishedtime = $this->sdk_finished_time_sql();

        $fromtimestamp = $this->date_filter_timestamp(isset($filters['fromdate']) ? $filters['fromdate'] : '');
        if ($fromtimestamp !== null) {
            $params['sdkfrom'] = $fromtimestamp;
            $where[] = '(' . $finishedtime . ' > :sdkfrom OR ' . $finishedtime . ' = 0)';
        }

        $totimestamp = $this->date_filter_timestamp(isset($filters['todate']) ? $filters['todate'] : '', true);
        if ($totimestamp !== null) {
            $params['sdkto'] = $totimestamp;
            $where[] = '(' . $finishedtime . ' <= :sdkto OR ' . $finishedtime . ' = 0)';
        }

        if (!empty($filters['courseid'])) {
            $where[] = 'q.course = :sdkcourseid';
            $params['sdkcourseid'] = $filters['courseid'];
        }
        if (!empty($filters['timefinish'])) {
            $where[] = $finishedtime . ' = :sdktimefinish';
            $params['sdktimefinish'] = $filters['timefinish'];
        }
        if (!empty($filters['moduleid'])) {
            $where[] = 'cm.id = :sdkmoduleid';
            $params['sdkmoduleid'] = $filters['moduleid'];
        }
        if (!empty($filters['userquery'])) {
            $value = trim($filters['userquery']);
            $likevalue = '%' . $DB->sql_like_escape($value) . '%';
            foreach (['email', 'username', 'firstname', 'lastname'] as $field) {
                $params['sdkuser' . $field] = $likevalue;
            }
            $where[] = '(' . $DB->sql_like('u.email', ':sdkuseremail', false)
                     . ' OR ' . $DB->sql_like('u.username', ':sdkuserusername', false)
                     . ' OR ' . $DB->sql_like('u.firstname', ':sdkuserfirstname', false)
                     . ' OR ' . $DB->sql_like('u.lastname', ':sdkuserlastname', false) . ')';
        }
        if (!empty($filters['status'])) {
            list($statussql, $statusparams) = $this->status_condition(trim($filters['status']), 'sdk');
            $where[] = $statussql;
            $params = array_merge($params, $statusparams);
        }

        if ($courseids) {
            list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'sdkcid');
            $where[] = 'q.course ' . $insql;
            $params = array_merge($params, $inparams);
        } else {
            $where[] = '1=0';
        }

        $from = ' FROM {' . session::TABLE . '} s '
              . ' JOIN {quiz} q ON q.id = s.quizid '
              . ' JOIN {course_modules} cm ON cm.instance = q.id '
              . ' JOIN {modules} m ON m.id = cm.module AND m.name = :sdkmodname '
              . ' LEFT JOIN {user} u ON u.id = s.userid '
              . ' LEFT JOIN {quiz_attempts} a ON a.id = s.attemptid ';
        $wheresql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $select = "SELECT s.id AS id, '" . config::MODEL_SDK . "' AS proctormodel, "
                . 's.timecreated AS timecreated, '
                . $finishedtime . ' AS sorttime, '
                . $finishedtime . ' AS timefinish, 0 AS timescheduled, '
                . 'u.firstname AS u_firstname, u.lastname AS u_lastname, '
                . 'u.email AS u_email, u.username AS u_username, u.id AS userid, '
                . $this->sdk_status_sql() . ' AS status, '
                . 's.reporturl AS review_link, s.archiveurl AS archiveurl, '
                . 'cm.id AS cmid, q.course AS courseid, s.score AS score, '
                . 's.threshold AS threshold, a.id AS attemptid, '
                . 'a.state AS attemptstate, '
                . 'CASE WHEN s.attemptid IS NOT NULL AND s.id = ('
                . 'SELECT MAX(slatest.id) FROM {' . session::TABLE . '} slatest '
                . 'WHERE slatest.attemptid = s.attemptid'
                . ') THEN 1 ELSE 0 END AS islatest';

        return [
            $select . $from . $wheresql,
            'SELECT COUNT(s.id)' . $from . $wheresql,
            $params,
        ];
    }

    /**
     * Best available end time for an SDK report row.
     *
     * A timeout can end Constructor while the Moodle attempt remains in progress,
     * so quiz_attempts.timefinish alone leaves a terminal session blank and sorts
     * it with live rows. Prefer Constructor's own sessionEnd, then the local
     * lifecycle finish stamp, then Moodle's attempt finish as a final fallback.
     * This order keeps sequential New entry rows at their own actual end times.
     *
     * @return string Portable SQL expression.
     */
    protected function sdk_finished_time_sql(): string {
        return 'CASE '
             . 'WHEN s.sessionend IS NOT NULL AND s.sessionend > 0 THEN s.sessionend '
             . 'WHEN s.timefinished IS NOT NULL AND s.timefinished > 0 THEN s.timefinished '
             . 'WHEN a.timefinish IS NOT NULL AND a.timefinish > 0 THEN a.timefinish '
             . 'ELSE 0 END';
    }

    /**
     * SQL form of sdk_status(), used so SDK rows sort with their displayed status.
     *
     * @return string Portable CASE expression.
     */
    protected function sdk_status_sql(): string {
        return "CASE "
             . "WHEN s.conclusion IN ('accepted', 'success', 'rejected') "
             . "THEN s.conclusion "
             . "WHEN s.state = 'finished' OR COALESCE(s.sessionend, 0) > 0 "
             . "OR COALESCE(s.timeresult, 0) > 0 THEN 'finished' "
             . "WHEN s.state = 'pending' THEN 'new' "
             . "WHEN s.state IN ('active', 'finishing') THEN 'started' "
             . "WHEN s.state = 'abandoned' THEN 'notsubmitted' "
             . "ELSE 'finished' END";
    }

    /**
     * Sort the combined report and keep equal values deterministic across pages.
     *
     * @return string ORDER BY body.
     */
    protected function report_sort(): string {
        $sort = (string) $this->table->get_sql_sort();

        if ($sort === '') {
            return 'sorttime DESC, proctormodel ASC, id DESC';
        }

        // sorttime is the portable, non-null form of timefinish. PostgreSQL
        // otherwise places NULL values first for DESC while MySQL places them
        // last, making the same report order differ by database.
        $sort = preg_replace('/\btimefinish\b/', 'sorttime', $sort);
        $finishtiebreaker = strpos($sort, 'sorttime') === false ? ', sorttime DESC' : '';

        return $sort . $finishtiebreaker . ', proctormodel ASC, id DESC';
    }

    /**
     * Normalize an SDK lifecycle record to a source status shared by the report.
     *
     * Constructor's terminal result outranks the local state. This is essential
     * for timeout: the browser sends no event, so the local row can remain active
     * after sessionEnd and the result webhook have proved the recording ended.
     *
     * @param \stdClass $entry A row from the SDK query.
     * @return string A Webapp status value.
     */
    public static function sdk_status(\stdClass $entry): string {
        $state = (string) ($entry->state ?? '');
        $conclusion = (string) ($entry->conclusion ?? '');

        if (in_array($conclusion, ['accepted', 'success', 'rejected'], true)) {
            return $conclusion;
        }

        $terminal = $state === session::STATE_FINISHED
            || !empty($entry->sessionend)
            || !empty($entry->timeresult);
        if ($terminal) {
            return 'finished';
        }

        switch ($state) {
            case session::STATE_PENDING:
                return 'new';

            case session::STATE_ACTIVE:
            case session::STATE_FINISHING:
                // finishing is the SDK's own Finish exam having been pressed while
                // the upload completes. Shown as started because the exam is not
                // over from a reviewer's point of view until it is finished, and
                // the Webapp vocabulary has no word for the intermediate step.
                return 'started';

            case session::STATE_FINISHED:
                return 'finished';

            case session::STATE_ABANDONED:
                // Not "timeout": measured, exactly two transitions write this state
                // and only one of them is a timeout. Not "needs review" either,
                // which would blur into the unknown conclusion. What both have in
                // common is the one thing this column then claims.
                return 'notsubmitted';

            default:
                // Future source states are reviewer work, never silently active.
                return 'finished';
        }
    }

    /**
     * Turn a report status group into a condition on the SDK columns.
     *
     * @param string $status Status group or legacy raw filter value.
     * @param string $prefix Prefix for named SQL parameters.
     * @return array [SQL condition, parameters]
     */
    protected function status_condition(string $status, string $prefix = ''): array {
        $status = self::status_group($status);
        $groups = [
            'notstarted' => ['new'],
            'inprogress' => ['started'],
            'needsreview' => ['finished'],
            'accepted' => ['accepted', 'success'],
            'rejected' => ['rejected'],
            'closed' => ['notsubmitted'],
        ];
        if (!isset($groups[$status])) {
            return ['1=0', []];
        }

        list($insql, $params) = $this->in_or_equal_named($groups[$status], $prefix . 'display');
        return ['(' . $this->sdk_status_sql() . ') ' . $insql, $params];
    }

    /**
     * Turn a user-facing status group into a Webapp status condition.
     *
     * @param string $status Status group or legacy raw filter value.
     * @param string $prefix Prefix for named SQL parameters.
     * @return array [SQL condition, parameters]
     */
    protected function webapp_status_condition(string $status, string $prefix): array {
        $groups = [
            'notstarted' => ['new', 'scheduled'],
            'inprogress' => ['started'],
            'needsreview' => ['finished', 'unknown'],
            'accepted' => ['accepted', 'success'],
            'rejected' => ['rejected'],
            'closed' => ['force_reset', 'canceled', 'rescheduled', 'notsubmitted'],
        ];
        $status = self::status_group($status);

        if (!isset($groups[$status])) {
            return ['1=0', []];
        }

        list($insql, $params) = $this->in_or_equal_named($groups[$status], $prefix);
        return ['e.status ' . $insql, $params];
    }

    /**
     * A named IN clause, for a list this class controls.
     *
     * @param array $values Values.
     * @param string $prefix Parameter name prefix.
     * @return array [SQL fragment, parameters]
     */
    protected function in_or_equal_named(array $values, string $prefix): array {
        global $DB;

        return $DB->get_in_or_equal($values, SQL_PARAMS_NAMED, $prefix);
    }

    /**
     * Convert a validated HTML date value to the current user's midnight.
     *
     * @param string $date Date in YYYY-MM-DD format.
     * @param bool $endofday Whether to return the final second of the day.
     * @return int|null Timestamp, or null for an invalid/empty value.
     */
    protected function date_filter_timestamp(string $date, bool $endofday = false) {
        if (
            !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
                || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
        ) {
            return null;
        }

        return make_timestamp(
            (int) $parts[1],
            (int) $parts[2],
            (int) $parts[3],
            $endofday ? 23 : 0,
            $endofday ? 59 : 0,
            $endofday ? 59 : 0
        );
    }

    /**
     * Sets up \flexible_table instance
     */
    protected function setup_table() {
        // The v2 id intentionally starts a fresh table preference after the
        // report changed its default sort and columns. No report data is affected.
        $table = new \flexible_table('availability_proctor_report_v2');

        $table->define_columns([
            'timefinish', 'u_email', 'courseid', 'cmid', 'proctormodel',
            'status', 'review_link', 'score', 'actions',
        ]);

        $table->define_headers([
            get_string('report_finished', 'availability_proctor'),
            get_string('user'),
            get_string('course'),
            get_string('report_activity', 'availability_proctor'),
            get_string('log_model', 'availability_proctor'),
            get_string('status', 'availability_proctor'),
            get_string('log_review', 'availability_proctor'),
            get_string('report_score', 'availability_proctor'),
            get_string('report_actions', 'availability_proctor'),
        ]);

        $table->define_baseurl($this->url);
        $table->sortable(true, 'timefinish', SORT_DESC);
        $table->no_sorting('courseid');
        $table->no_sorting('cmid');
        $table->no_sorting('review_link');
        $table->no_sorting('actions');
        $table->set_attribute('id', 'entries');
        $table->set_attribute('class', 'generaltable availability-proctor-report-table');
        $table->setup();
        $this->table = $table;
    }

    /**
     * Renders and echoes log table
     */
    public function render_table() {
        global $OUTPUT;

        $entries = $this->entries;
        $table = $this->table;

        echo html_writer::div(
            get_string('report_resultcount', 'availability_proctor', $this->entriescount),
            'availability-proctor-report-summary'
        );

        if (empty($entries)) {
            $hasfilter = !empty(array_filter($this->filters, function ($value) {
                return $value !== null && $value !== '' && $value !== 0;
            }));
            echo $OUTPUT->notification(
                get_string($hasfilter ? 'report_nomatches' : 'report_nosessions', 'availability_proctor'),
                \core\output\notification::NOTIFY_INFO
            );
            $table->finish_output();
            return;
        }

        foreach ($entries as $entry) {
            $row = [];
            try {
                $course = get_course($entry->courseid);
            } catch (\moodle_exception $e) {
                $course = null;
            }
            $cm = null;
            if ($course) {
                try {
                    $cm = get_fast_modinfo($course->id)->get_cm($entry->cmid);
                } catch (\moodle_exception $e) {
                    $cm = null;
                }
            }

            $row[] = empty($entry->timefinish) ? '' : common::format_date($entry->timefinish);
            $row[] = self::render_user($entry, (int) $entry->courseid);
            $row[] = $course
                ? html_writer::link(
                    new \moodle_url('/course/view.php', ['id' => $course->id]),
                    format_string($course->fullname)
                )
                : html_writer::span(
                    get_string('report_deleted_course', 'availability_proctor'),
                    'availability-proctor-report-muted'
                );
            $row[] = $cm
                ? html_writer::link($cm->url, $cm->get_formatted_name())
                : html_writer::span(
                    get_string('report_deleted_activity', 'availability_proctor'),
                    'availability-proctor-report-muted'
                );
            $row[] = self::render_model_badge($entry->proctormodel);
            $row[] = self::render_status_badge($entry->status);

            $reviewlinks = [];
            foreach (
                [
                'review_link' => 'log_report_link',
                'archiveurl' => 'log_archive_link',
                ] as $field => $stringid
            ) {
                $link = self::external_link($entry->$field, get_string($stringid, 'availability_proctor'));
                if ($link !== '') {
                    $reviewlinks[] = $link;
                }
            }
            if (!empty($entry->attemptid)) {
                $reviewlinks[] = html_writer::link(
                    new \moodle_url('/mod/quiz/review.php', ['attempt' => $entry->attemptid]),
                    get_string('log_attempt', 'availability_proctor'),
                    ['class' => 'btn btn-sm btn-outline-secondary']
                );
            }
            $row[] = html_writer::div(
                implode('', $reviewlinks),
                'availability-proctor-report-link-group'
            );
            $row[] = self::render_score($entry->score, $entry->threshold);

            $detailsurl = new \moodle_url('/availability/condition/proctor/index.php', [
                'id' => $entry->id,
                'action' => 'show',
                'model' => $entry->proctormodel,
            ]);
            $actions = [html_writer::link(
                $detailsurl,
                get_string('details', 'availability_proctor'),
                ['class' => 'btn btn-sm btn-primary']
            )];

            // Webapp keeps its established New entry behavior. SDK exposes the
            // same instructor action only while its Moodle attempt is still open;
            // the handler also verifies the old Constructor session has ended.
            if ($entry->proctormodel === config::MODEL_WEBAPP) {
                $scheduled = $entry->status === 'scheduled' && !empty($entry->timescheduled);
                $force = $entry->status === 'new' || $scheduled;
                $actions[] = $this->render_reset_form($entry->id, $force, config::MODEL_WEBAPP);
            } else if (
                $entry->proctormodel === config::MODEL_SDK
                    && $entry->attemptstate === 'inprogress'
                    && !empty($entry->islatest)
            ) {
                $actions[] = $this->render_reset_form($entry->id, false, config::MODEL_SDK);
            }
            $row[] = html_writer::div(
                implode('', $actions),
                'availability-proctor-report-link-group availability-proctor-report-actions'
            );

            $table->add_data($row);
        }

        $table->finish_output();
    }

    /**
     * Render a user while respecting Moodle's identity-field configuration.
     *
     * @param \stdClass $user Normalized report row or user record.
     * @param int $courseid Course used by the profile link.
     * @return string HTML.
     */
    public static function render_user(\stdClass $user, int $courseid = 0): string {
        global $CFG;

        $firstname = isset($user->u_firstname) ? $user->u_firstname : (isset($user->firstname) ? $user->firstname : '');
        $lastname = isset($user->u_lastname) ? $user->u_lastname : (isset($user->lastname) ? $user->lastname : '');
        $username = isset($user->u_username) ? $user->u_username : (isset($user->username) ? $user->username : '');
        $email = isset($user->u_email) ? $user->u_email : (isset($user->email) ? $user->email : '');
        $userid = isset($user->userid) ? $user->userid : (isset($user->id) ? $user->id : 0);
        $name = trim($firstname . ' ' . $lastname);

        if ($name === '') {
            return html_writer::span(
                get_string('report_deleted_user', 'availability_proctor'),
                'availability-proctor-report-muted'
            );
        }

        $namehtml = s($name);
        if ($userid) {
            $namehtml = html_writer::link(
                new \moodle_url('/user/view.php', ['id' => $userid, 'course' => $courseid ?: SITEID]),
                $namehtml,
                ['class' => 'availability-proctor-report-user-name']
            );
        }

        $identity = [];
        if (has_capability('moodle/site:viewuseridentity', \context_system::instance())) {
            $visible = array_flip(array_filter(explode(',', (string) ($CFG->showuseridentity ?? ''))));
            if (isset($visible['username']) && $username !== '') {
                $identity[] = s($username);
            }
            if (isset($visible['email']) && $email !== '') {
                $identity[] = s($email);
            }
        }

        if ($identity) {
            $namehtml .= html_writer::div(
                implode(' · ', $identity),
                'availability-proctor-report-user-meta'
            );
        }

        return $namehtml;
    }

    /**
     * Convert a source status into the report's reviewer-focused vocabulary.
     *
     * Grouping is presentation-only. Source values remain stored unchanged and
     * stay visible on the details page.
     *
     * @param string $status Source status or an existing group key.
     * @return string Status group key.
     */
    public static function status_group(string $status): string {
        $groups = [
            'notstarted' => ['notstarted', 'new', 'scheduled', 'pending'],
            'inprogress' => ['inprogress', 'started', 'active', 'finishing'],
            'needsreview' => ['needsreview', 'finished', 'unknown'],
            'accepted' => ['accepted', 'success'],
            'rejected' => ['rejected'],
            'closed' => ['closed', 'force_reset', 'canceled', 'rescheduled', 'notsubmitted', 'abandoned'],
        ];

        foreach ($groups as $group => $values) {
            if (in_array($status, $values, true)) {
                return $group;
            }
        }

        // A future source value is safest in Needs review: it remains visible
        // and does not falsely claim that the session passed or failed.
        return 'needsreview';
    }

    /**
     * Render a status as a Moodle-compatible badge.
     *
     * @param string $status Source status or report group.
     * @return string HTML.
     */
    public static function render_status_badge(string $status): string {
        $status = self::status_group($status);
        $classes = [
            'accepted' => 'success',
            'rejected' => 'danger',
            'inprogress' => 'info',
            'needsreview' => 'warning',
            'notstarted' => 'secondary',
            'closed' => 'light',
        ];

        return html_writer::span(
            self::render_status($status),
            'badge badge-' . $classes[$status] . ' availability-proctor-report-status'
        );
    }

    /**
     * Render the source model as a compact badge.
     *
     * @param string $model Stored model value.
     * @return string HTML.
     */
    public static function render_model_badge(string $model): string {
        return html_writer::span(
            self::render_model($model),
            'badge badge-light availability-proctor-report-model'
        );
    }

    /**
     * Render a score together with its stored rejection threshold.
     *
     * @param mixed $score Stored score.
     * @param string|null $threshold Stored threshold JSON.
     * @return string HTML.
     */
    public static function render_score($score, $threshold): string {
        if ($score === null || $score === '') {
            return '';
        }

        $decoded = json_decode((string) $threshold, true);
        $attention = is_array($decoded) && isset($decoded['attention']) ? $decoded['attention'] : null;
        $rejected = is_array($decoded) && isset($decoded['rejected']) ? $decoded['rejected'] : null;
        $class = 'availability-proctor-report-score-clear';

        if (is_numeric($rejected) && (float) $score > (float) $rejected) {
            $class = 'availability-proctor-report-score-rejected';
        } else if (is_numeric($attention) && (float) $score >= (float) $attention) {
            $class = 'availability-proctor-report-score-attention';
        }

        $html = html_writer::span(
            s((string) $score),
            'availability-proctor-report-score ' . $class
        );
        if (is_numeric($rejected)) {
            $html .= ' ' . html_writer::span(
                '/ ' . s((string) $rejected),
                'availability-proctor-report-score-threshold'
            );
        }

        return $html;
    }

    /**
     * Render a trusted-display external link only for HTTP(S) URLs.
     *
     * @param string|null $url Stored external URL.
     * @param string $label Link label.
     * @return string HTML, or an empty string for an unsafe URL.
     */
    public static function external_link($url, string $label): string {
        if (empty($url)) {
            return '';
        }

        $parts = parse_url((string) $url);
        if (
            !is_array($parts) || empty($parts['scheme'])
                || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        ) {
            return '';
        }

        return html_writer::link((string) $url, $label, [
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
            'class' => 'btn btn-sm btn-outline-secondary',
        ]);
    }

    /**
     * Render the model-specific New entry form.
     *
     * @param int $id Entry id.
     * @param bool $force Whether the not-started reset must be forced.
     * @param string $model Source model.
     * @return string HTML.
     */
    protected function render_reset_form($id, bool $force, string $model): string {
        $html = html_writer::start_tag('form', [
            'action' => 'index.php',
            'method' => 'post',
            'class' => 'availability-proctor-report-reset-form',
        ]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'model', 'value' => $model]);
        if ($force) {
            $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'force', 'value' => 'true']);
        }
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'renew']);
        $html .= html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string($force ? 'new_entry_force' : 'new_entry', 'availability_proctor'),
            'class' => 'btn btn-sm btn-outline-secondary',
        ]);
        $html .= html_writer::end_tag('form');

        return $html;
    }

    /**
     * The reviewer-facing label for a source status or report group.
     *
     * @param string $status Source status or report group.
     * @return string Label.
     */
    public static function render_status(string $status): string {
        if ($status === '') {
            return '';
        }

        return get_string('report_status_' . self::status_group($status), 'availability_proctor');
    }

    /**
     * The exact source-status label used on the details page.
     *
     * @param string $status Stored status, state or conclusion.
     * @return string Label.
     */
    public static function render_source_status(string $status): string {
        if ($status === '') {
            return '';
        }

        $key = 'status_' . $status;

        if (!get_string_manager()->string_exists($key, 'availability_proctor')) {
            return s($status);
        }

        return get_string($key, 'availability_proctor');
    }

    /**
     * The user-facing name of a report row's source model.
     *
     * @param string $model Stored model value.
     * @return string
     */
    public static function render_model(string $model): string {
        $key = 'settings_proctormodel_' . $model;

        if (!get_string_manager()->string_exists($key, 'availability_proctor')) {
            return s($model);
        }

        return get_string($key, 'availability_proctor');
    }

    /**
     * The label for an SDK session's reason.
     *
     * The reason is what distinguishes the two ways a session reaches abandoned,
     * which the status column deliberately does not: app_unmounted means the
     * student closed the SDK window, bind_timeout means the launch page opened but
     * no attempt appeared inside config::BIND_WINDOW. Only those two carry strings,
     * because only those two are conflated by the status.
     *
     * Everything else falls back to the stored value. reason is written from
     * several call sites -- token_issued, exam_finished, attempt_abandoned and more
     * -- and a future SDK event adds another; the fallback is what keeps that a
     * readable row rather than a missing-string notice.
     *
     * @param string|null $reason The stored reason.
     * @return string
     */
    public static function render_reason($reason): string {
        $reason = (string) $reason;

        if ($reason === '') {
            return '';
        }

        $key = 'reason_' . $reason;

        if (!get_string_manager()->string_exists($key, 'availability_proctor')) {
            return s($reason);
        }

        return get_string($key, 'availability_proctor');
    }

    /**
     * Return list of modules to show in selector.
     *
     * @return array list of courses.
     */
    public function get_module_list() {
        global $DB;

        $courses = ['' => get_string('allmodules', 'availability_proctor')];

        if ($courserecords = $DB->get_records("module", null, "fullname", "id,shortname,fullname,category")) {
            foreach ($courserecords as $course) {
                if ($course->id == SITEID) {
                    $courses[$course->id] = format_string($course->fullname) . ' (' . get_string('site') . ')';
                } else {
                    $courses[$course->id] = format_string(get_course_display_name_for_list($course));
                }
            }
        }
        \core_collator::asort($courses);

        return $courses;
    }

    /**
     * Return list of courses to show in selector.
     *
     * @return array list of courses.
     */
    public function get_course_list() {
        global $DB;

        $courses = [];

        $sitecontext = \context_system::instance();

        if ($courserecords = $DB->get_records("course", null, "fullname", "id,shortname,fullname,category")) {
            foreach ($courserecords as $course) {
                $coursecontext = \context_course::instance($course->id);
                if (!has_capability('availability/proctor:logaccess_all', $sitecontext)) {
                    if (!has_capability('availability/proctor:logaccess_course', $coursecontext)) {
                        continue;
                    }
                }

                if ($course->id == SITEID) {
                    $courses[$course->id] = format_string($course->fullname) . ' (' . get_string('site') . ')';
                } else {
                    $courses[$course->id] = format_string(get_course_display_name_for_list($course));
                }
            }
        }
        \core_collator::asort($courses);

        return $courses;
    }

    /**
     * Return list of courses to show in selector.
     *
     * @return array list of courses.
     */
    public function get_status_list() {
        $statuses = ['notstarted', 'inprogress', 'needsreview', 'accepted', 'rejected', 'closed'];

        $statuslist = [];

        foreach ($statuses as $status) {
            $statuslist[$status] = get_string('report_status_' . $status, 'availability_proctor');
        }

        return $statuslist;
    }

    /**
     * Return list of users.
     *
     * @return array list of users.
     */
    public function get_user_list() {
        global $CFG, $SITE;

        $courseid = $SITE->id;
        if (!empty($this->course)) {
            $courseid = $this->course->id;
        }
        $context = \context_course::instance($courseid);
        $limitfrom = 0;
        $limitnum  = 10000;
        $courseusers = get_enrolled_users(
            $context,
            '',
            null,
            'u.id, ' . get_all_user_name_fields(true, 'u'),
            null,
            $limitfrom,
            $limitnum
        );

        $users = [];
        if ($courseusers) {
            foreach ($courseusers as $courseuser) {
                $users[$courseuser->id] = fullname($courseuser, has_capability('moodle/site:viewfullnames', $context));
            }
        }
        $users[$CFG->siteguest] = get_string('guestuser');

        return $users;
    }

    /**
     * Return list of date options.
     *
     * @return array date options.
     */
    public function get_date_options() {
        global $SITE;

        $strftimedate = get_string("strftimedate");
        $strftimedaydate = get_string("strftimedaydate");

        // Get all the possible dates.
        // Note that we are keeping track of real (GMT) time and user time.
        // User time is only used in displays - all calcs and passing is GMT.
        $timenow = time(); // GMT.

        // What day is it now for the user, and when is midnight that day (in GMT).
        $timemidnight = usergetmidnight($timenow);

        // Put today up the top of the list.
        $dates = ["$timemidnight" => get_string("today") . ", " . userdate($timenow, $strftimedate)];

        // If course is empty, get it from frontpage.
        $course = $SITE;
        if (!empty($this->course)) {
            $course = $this->course;
        }
        if (!$course->startdate || ($course->startdate > $timenow)) {
            $course->startdate = $course->timecreated;
        }

        $numdates = 1;
        while ($timemidnight > $course->startdate && $numdates < 365) {
            $timemidnight = $timemidnight - 86400;
            $timenow = $timenow - 86400;
            $dates["$timemidnight"] = userdate($timenow, $strftimedaydate);
            $numdates++;
        }
        return $dates;
    }

    /**
     * Renders end echoes table filters form
     */
    public function render_filter_form() {
        $courseid = isset($this->filters['courseid']) ? $this->filters['courseid'] : null;
        $userquery = isset($this->filters['userquery']) ? $this->filters['userquery'] : '';
        $status = isset($this->filters['status']) ? $this->filters['status'] : '';
        $fromdate = isset($this->filters['fromdate']) ? $this->filters['fromdate'] : '';
        $todate = isset($this->filters['todate']) ? $this->filters['todate'] : '';
        $courses = $this->get_course_list();
        $statuses = $this->get_status_list();
        $formurl = new \moodle_url('/availability/condition/proctor/index.php');
        $hasfilter = !empty($courseid) || trim((string) $userquery) !== ''
            || trim((string) $status) !== '' || $fromdate !== '' || $todate !== '';

        echo html_writer::start_div('availability-proctor-report-filters');
        echo html_writer::start_tag('form', [
            'class' => 'availability-proctor-report-filter-form',
            'action' => $formurl->out(false),
            'method' => 'get',
        ]);
        echo html_writer::start_tag('fieldset', ['class' => 'availability-proctor-report-filter-row']);
        echo html_writer::tag(
            'legend',
            get_string('report_filterlegend', 'availability_proctor'),
            ['class' => 'sr-only']
        );

        echo html_writer::start_div('availability-proctor-report-filter-field');
        echo html_writer::label(get_string('course'), 'availability-proctor-course', false);
        echo html_writer::select(
            $courses,
            'courseid',
            $courseid,
            get_string('allcourses', 'availability_proctor'),
            ['id' => 'availability-proctor-course', 'class' => 'form-control']
        );
        echo html_writer::end_div();

        echo html_writer::start_div('availability-proctor-report-filter-field availability-proctor-report-filter-user');
        echo html_writer::label(get_string('user'), 'availability-proctor-user');
        echo html_writer::empty_tag('input', [
            'type' => 'search',
            'name' => 'userquery',
            'id' => 'availability-proctor-user',
            'value' => $userquery,
            'placeholder' => get_string('userquery', 'availability_proctor'),
            'class' => 'form-control',
        ]);
        echo html_writer::end_div();

        echo html_writer::start_div('availability-proctor-report-filter-field');
        echo html_writer::label(get_string('status', 'availability_proctor'), 'availability-proctor-status');
        echo html_writer::select(
            $statuses,
            'status',
            $status,
            get_string('allstatuses', 'availability_proctor'),
            ['id' => 'availability-proctor-status', 'class' => 'form-control']
        );
        echo html_writer::end_div();

        foreach (
            [
            'fromdate' => ['availability-proctor-fromdate', get_string('fromdate', 'availability_proctor'), $fromdate],
            'todate' => ['availability-proctor-todate', get_string('todate', 'availability_proctor'), $todate],
            ] as $name => $datefield
        ) {
            echo html_writer::start_div('availability-proctor-report-filter-field availability-proctor-report-filter-date');
            echo html_writer::label($datefield[1], $datefield[0]);
            echo html_writer::empty_tag('input', [
                'type' => 'date',
                'name' => $name,
                'id' => $datefield[0],
                'value' => $datefield[2],
                'class' => 'form-control',
            ]);
            echo html_writer::end_div();
        }

        echo html_writer::start_div('availability-proctor-report-filter-actions');
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('apply_filter', 'availability_proctor'),
            'class' => 'btn btn-primary',
        ]);
        if ($hasfilter) {
            echo html_writer::link(
                $formurl,
                get_string('report_clearfilters', 'availability_proctor'),
                ['class' => 'btn btn-link']
            );
        }
        echo html_writer::end_div();

        echo html_writer::end_tag('fieldset');
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
    }
}
