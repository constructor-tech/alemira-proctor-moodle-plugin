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
    protected $perpage = 30;

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

        // NTG-185: an SDK site's rows live in a different table, so a different
        // query reads them. Everything below this point is the hosted query, and it
        // is left exactly as it shipped -- this is the report page every existing
        // customer runs, and the two tables cannot be merged into one statement:
        // they share 11 column names out of 27 and 19, and the status column is not
        // among them (hosted has one status of eight values, the SDK has state plus
        // a separate conclusion).
        //
        // The model is chosen site-wide, so a site's rows all come from one table
        // and the two vocabularies never appear on the same page.
        if (config::is_sdk_model()) {
            $this->fetch_sdk_data();
            return;
        }

        $select = [
            'e.id id',
            'e.timecreated timecreated',
            'a.timefinish timefinish',
            'timescheduled',
            'u.firstname u_firstname',
            'u.lastname u_lastname',
            'u.email u_email',
            'u.username u_username',
            'u.id userid',
            'e.status status',
            'review_link',
            'archiveurl',
            'cmid',
            'courseid',
            'score',
            'comment',
            'threshold',
        ];

        $where = [];
        $params = $this->filters;

        if (isset($params['from[day]']) && isset($params['from[month]']) && isset($params['from[year]'])) {
            $month = $params['from[month]'];
            $day = $params['from[day]'];
            $year = $params['from[year]'];
            unset($params['from[month]'], $params['from[day]'], $params['from[year]']);

            $params['from'] = mktime(0, 0, 0, $month, $day, $year);
        };

        if (isset($params['to[day]']) && isset($params['to[month]']) && isset($params['to[year]'])) {
            $month = $params['to[month]'];
            $day = $params['to[day]'];
            $year = $params['to[year]'];
            unset($params['to[month]'], $params['to[day]'], $params['to[year]']);

            $params['to'] = mktime(23, 59, 59, $month, $day, $year);
        };

        foreach ($params as $key => $value) {
            if (empty($value)) {
                continue;
            }
            $value = trim($value);
            switch ($key) {
                case 'from':
                    $where[] = '(a.timefinish > :' . $key . ' OR a.timefinish IS NULL OR a.timefinish = 0)';
                    break;

                case 'to':
                    $where[] = '(a.timefinish <= :' . $key . ' OR a.timefinish IS NULL OR a.timefinish = 0)';
                    break;

                case 'userquery':
                    $params[$key . '1'] = $DB->sql_like_escape($value) . '%';
                    $params[$key . '2'] = $DB->sql_like_escape($value) . '%';

                    $emaillike = $DB->sql_like("u.email", ':' . $key . '1');
                    $usernamelike = $DB->sql_like("u.username", ':' . $key . '2');
                    $where[] = "(" . $emaillike . " OR " . $usernamelike . ")";
                    break;

                default:
                    $where[] = $key . ' = :' . $key;
            }
        }

        $courseids = array_keys($this->get_course_list());
        if (!empty($courseids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
            $where[] = 'courseid ' . $insql;
            $params = array_merge($params, $inparams);
        } else {
            // Always false condition. Can't use FALSE because of mssql.
            $where[] = '1=0';
        }

        $orderby = $this->table->get_sql_sort();

        $limitfrom = ($this->page * $this->perpage);
        $limitnum  = $this->perpage;

        $query = 'SELECT ' . implode(', ', $select) . ' FROM {availability_proctor_entries} e '
               . ' LEFT JOIN {user} u ON u.id=e.userid '
               . ' LEFT JOIN {quiz_attempts} a ON a.id=e.attemptid '
               . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '')
               . ' ORDER BY ' . ($orderby ? $orderby : 'id');

        $querycount = 'SELECT count(e.id) as count FROM {availability_proctor_entries} e '
                    . ' LEFT JOIN {user} u ON u.id=e.userid '
                    . ' LEFT JOIN {quiz_attempts} a ON a.id=e.attemptid '
                    . (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');

        $this->entries = $DB->get_records_sql($query, $params, $limitfrom, $limitnum);

        $result = $DB->get_records_sql($querycount, $params);
        $this->entriescount = reset($result)->count;

        $this->table->pagesize($this->perpage, $this->entriescount);
    }

    /**
     * NTG-185: fetch the same table's worth of data from the SDK session rows.
     *
     * Deliberately a sibling of fetch_data() rather than a set of branches inside
     * it. The hosted query is what every existing customer's report page runs, and
     * the surest way not to change it is not to edit the method it lives in.
     *
     * Produces rows carrying the same property names the renderer already reads --
     * status, review_link, archiveurl, cmid, courseid and the rest -- so that
     * render_table() needs no second code path for the common columns. The row is
     * shaped here; what cannot exist on an SDK row is handled where it is rendered.
     *
     * @return void
     */
    protected function fetch_sdk_data() {
        global $DB;

        $select = [
            's.id id',
            's.timecreated timecreated',
            'a.timefinish timefinish',
            'u.firstname u_firstname',
            'u.lastname u_lastname',
            'u.email u_email',
            'u.username u_username',
            'u.id userid',
            's.state state',
            's.conclusion conclusion',
            's.reason reason',
            's.reporturl review_link',
            's.archiveurl archiveurl',
            'cm.id cmid',
            'q.course courseid',
            's.score score',
            's.comment comment',
            's.threshold threshold',
        ];

        $where = [];
        $params = [];

        // The date filters read quiz_attempts.timefinish, and _sess.attemptid is
        // nullable: a session that was never bound to an attempt has none. So the
        // join stays a LEFT JOIN and the IS NULL clauses are kept, exactly as the
        // hosted query has them. An inner join would hide precisely the abandoned
        // sessions this page is most useful for.
        $filters = $this->filters;

        if (isset($filters['from[day]']) && isset($filters['from[month]']) && isset($filters['from[year]'])) {
            $params['from'] = mktime(
                0,
                0,
                0,
                $filters['from[month]'],
                $filters['from[day]'],
                $filters['from[year]']
            );
            $where[] = '(a.timefinish > :from OR a.timefinish IS NULL OR a.timefinish = 0)';
        }

        if (isset($filters['to[day]']) && isset($filters['to[month]']) && isset($filters['to[year]'])) {
            $params['to'] = mktime(
                23,
                59,
                59,
                $filters['to[month]'],
                $filters['to[day]'],
                $filters['to[year]']
            );
            $where[] = '(a.timefinish <= :to OR a.timefinish IS NULL OR a.timefinish = 0)';
        }

        if (!empty($filters['courseid'])) {
            $where[] = 'q.course = :courseid';
            $params['courseid'] = $filters['courseid'];
        }

        if (!empty($filters['timefinish'])) {
            $where[] = 'a.timefinish = :timefinish';
            $params['timefinish'] = $filters['timefinish'];
        }

        // The hosted query filters e.cmid directly; _sess stores the quiz instance,
        // so the cm reached through the join is what carries the id being filtered.
        if (!empty($filters['moduleid'])) {
            $where[] = 'cm.id = :moduleid';
            $params['moduleid'] = $filters['moduleid'];
        }

        if (!empty($filters['userquery'])) {
            $value = trim($filters['userquery']);
            $params['userquery1'] = $DB->sql_like_escape($value) . '%';
            $params['userquery2'] = $DB->sql_like_escape($value) . '%';
            $where[] = '(' . $DB->sql_like('u.email', ':userquery1')
                     . ' OR ' . $DB->sql_like('u.username', ':userquery2') . ')';
        }

        // The filter form offers the hosted vocabulary, so a chosen status has to be
        // turned back into whichever SDK column carries it. See status_condition().
        if (!empty($filters['status'])) {
            [$statussql, $statusparams] = $this->status_condition(trim($filters['status']));
            $where[] = $statussql;
            $params = array_merge($params, $statusparams);
        }

        // The same course scoping the hosted query uses, from the same method: the
        // 1=0 fallback means no capability shows no rows rather than every row.
        $courseids = array_keys($this->get_course_list());
        if (!empty($courseids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
            $where[] = 'q.course ' . $insql;
            $params = array_merge($params, $inparams);
        } else {
            // Always false condition. Can't use FALSE because of mssql.
            $where[] = '1=0';
        }

        // Joining {quiz} is what reaches the course, since _sess stores a quiz id
        // and has no courseid of its own. It carries a second consequence worth
        // keeping: a session whose quiz has been deleted drops out of the report,
        // which is consistent with the cleanup in observers::purge_orphans().
        //
        // cm.instance is unique only within a module type, hence the module name
        // condition on the {modules} join.
        $from = ' FROM {' . session::TABLE . '} s '
              . ' JOIN {quiz} q ON q.id = s.quizid '
              . ' JOIN {course_modules} cm ON cm.instance = q.id '
              . ' JOIN {modules} m ON m.id = cm.module AND m.name = :modname '
              . ' LEFT JOIN {user} u ON u.id = s.userid '
              . ' LEFT JOIN {quiz_attempts} a ON a.id = s.attemptid ';
        $params['modname'] = 'quiz';

        $wheresql = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';

        // The sort comes from the table, whose column names are the hosted ones. Two
        // of them do not exist on this query, so they are dropped rather than
        // passed through into a syntax error: timescheduled has no SDK counterpart
        // at all, and status is computed in PHP rather than selected.
        $orderby = $this->sdk_sort();

        $query = 'SELECT ' . implode(', ', $select) . $from . $wheresql
               . ' ORDER BY ' . $orderby;

        $querycount = 'SELECT count(s.id) as count' . $from . $wheresql;

        $this->entries = $DB->get_records_sql(
            $query,
            $params,
            $this->page * $this->perpage,
            $this->perpage
        );

        // Translate into the vocabulary the page speaks, once, here -- so the
        // renderer reads $entry->status on either model.
        foreach ($this->entries as $entry) {
            $entry->status = self::sdk_status($entry);
            $entry->timescheduled = null;
        }

        $result = $DB->get_records_sql($querycount, $params);
        $this->entriescount = reset($result)->count;

        $this->table->pagesize($this->perpage, $this->entriescount);
    }

    /**
     * The ORDER BY for the SDK query, with columns this query has no equivalent for
     * removed.
     *
     * flexible_table builds its sort from the column names given to
     * define_columns(), which are the hosted ones. Passing that straight through
     * would put timescheduled -- a column the SDK table does not have -- into the
     * statement, and status, which is derived in PHP after the rows are read.
     *
     * @return string An ORDER BY body, never empty.
     */
    protected function sdk_sort(): string {
        $sort = (string) $this->table->get_sql_sort();

        if ($sort === '') {
            return 's.id';
        }

        $keep = [];
        foreach (explode(',', $sort) as $clause) {
            $clause = trim($clause);
            $column = strtok($clause, ' ');

            if (in_array($column, ['timescheduled', 'status'], true)) {
                continue;
            }

            // u_email is an alias of a joined column; the rest name columns this
            // query selects under the same names the table uses.
            $keep[] = $clause;
        }

        return $keep ? implode(', ', $keep) : 's.id';
    }

    /**
     * Which hosted status an SDK session row is shown as.
     *
     * The report page keeps one vocabulary, the hosted one, and SDK rows are
     * translated into it rather than the page learning a second set of words. Six
     * of the eight hosted values have a direct SDK source; see plans/phase9.md for
     * the mapping table and for why force_reset and scheduled have none.
     *
     * A conclusion outranks the state, because it is the later and more specific
     * fact: a finished session that has been reviewed is shown as accepted or
     * rejected rather than merely finished. But only for a session that has
     * actually finished -- a verdict cannot legitimately arrive before the exam
     * ends, and if one does, the state is the honest answer rather than a verdict
     * about an exam still in progress.
     *
     * @param \stdClass $entry A row from the SDK query.
     * @return string A hosted status value.
     */
    public static function sdk_status(\stdClass $entry): string {
        $state = (string) ($entry->state ?? '');
        $conclusion = (string) ($entry->conclusion ?? '');

        if ($state === session::STATE_FINISHED && $conclusion !== '') {
            // Only the three conclusions the hosted vocabulary shares. Anything
            // else the server may introduce falls through to the state below,
            // rather than reaching get_string() as a status that has no lang entry.
            if (in_array($conclusion, ['accepted', 'rejected', 'unknown'], true)) {
                return $conclusion;
            }
        }

        switch ($state) {
            case session::STATE_PENDING:
                return 'new';

            case session::STATE_ACTIVE:
            case session::STATE_FINISHING:
                // finishing is the SDK's own Finish exam having been pressed while
                // the upload completes. Shown as started because the exam is not
                // over from a reviewer's point of view until it is finished, and
                // the hosted vocabulary has no word for the intermediate step.
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
                // An unrecognised state renders as itself rather than raising. The
                // renderer resolves status_<value>, so this degrades to a missing
                // string rather than to a fatal -- see render_status().
                return $state;
        }
    }

    /**
     * Turn a chosen hosted status back into a condition on the SDK columns.
     *
     * The reverse of sdk_status(), and kept next to it deliberately: the filter and
     * the column have to agree, and the two directions drift apart the moment they
     * live in different places. This is also why the mapping is PHP rather than a
     * CASE expression in the SELECT -- as SQL, the forward and reverse forms would
     * be two unrelated fragments with nothing keeping them consistent.
     *
     * @param string $status A hosted status value from the filter form.
     * @return array [SQL condition, parameters]
     */
    protected function status_condition(string $status): array {
        global $DB;

        // A verdict lives in its own column, and only counts on a finished session
        // -- the same rule sdk_status() applies when it prefers the conclusion.
        if (in_array($status, ['accepted', 'rejected', 'unknown'], true)) {
            return [
                's.conclusion = :conclusion AND s.state = :constate',
                ['conclusion' => $status, 'constate' => session::STATE_FINISHED],
            ];
        }

        $states = [
            'new' => [session::STATE_PENDING],
            'started' => [session::STATE_ACTIVE, session::STATE_FINISHING],
            'notsubmitted' => [session::STATE_ABANDONED],
        ];

        if (isset($states[$status])) {
            [$insql, $inparams] = $this->in_or_equal_named($states[$status], 'st');
            return ['s.state ' . $insql, $inparams];
        }

        // finished means finished and not yet reviewed: a session carrying a
        // verdict is shown as that verdict, so it must not also answer this filter.
        if ($status === 'finished') {
            return [
                's.state = :fstate AND (s.conclusion IS NULL OR '
                    . $DB->sql_isempty(session::TABLE, 's.conclusion', true, true) . ')',
                ['fstate' => session::STATE_FINISHED],
            ];
        }

        // force_reset and scheduled have no SDK counterpart -- there is no button
        // that resets an SDK session, and payload.php sends 'schedule' => false, so
        // a scheduled session cannot exist. Both stay in the filter list and return
        // no rows here, which is what a filter matching nothing should do.
        return ['1=0', []];
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
     * Sets up \flexible_table instance
     */
    protected function setup_table() {
        $table = new \flexible_table('availability_proctor_table');

        $table->define_columns([
            'timefinish', 'timescheduled', 'u_email',
            'courseid', 'cmid', 'status', 'review_link', 'score', 'details',
            'create_entry',
        ]);

        $table->define_headers([
            get_string('time_finish', 'availability_proctor'),
            get_string('time_scheduled', 'availability_proctor'),
            get_string('user'),
            get_string('course'),
            get_string('module', 'availability_proctor'),
            get_string('status', 'availability_proctor'),
            get_string('log_review', 'availability_proctor'),
            get_string('score', 'availability_proctor'),
            '',
            '',
        ]);

        $table->define_baseurl($this->url);
        $table->sortable(true);
        $table->no_sorting('courseid');
        $table->no_sorting('cmid');
        $table->set_attribute('id', 'entries');
        $table->set_attribute('class', 'generaltable generalbox');
        $table->setup();
        $this->table = $table;
    }

    /**
     * Renders and echoes log table
     */
    public function render_table() {
        $entries = $this->entries;
        $table = $this->table;

        if (!empty($entries)) {
            foreach ($entries as $entry) {
                $row = [];

                $row[] = common::format_date($entry->timefinish);

                if ($entry->timescheduled) {
                    $row[] = common::format_date($entry->timescheduled);
                } else {
                    $row[] = '';
                }

                $row[] = s($entry->u_firstname) . " " . s($entry->u_lastname) . "<br>"
                       . s($entry->u_username)
                       . ' (' . s($entry->u_email) . ')';

                $course = get_course($entry->courseid);
                $modinfo = get_fast_modinfo($course);
                try {
                    $cm = $modinfo->get_cm($entry->cmid);
                } catch (\moodle_exception $e) {
                    $cm = null;
                }

                $reportlinks = [];
                if ($entry->review_link !== null) {
                     $reportlinks[] = "<a href='" . s($entry->review_link) . "'>"
                                    . get_string('log_report_link', 'availability_proctor')
                                    . "</a>";
                }
                if ($entry->archiveurl !== null) {
                     $reportlinks[] = "<a href='" . s($entry->archiveurl) . "'>"
                                    . get_string('log_archive_link', 'availability_proctor')
                                    . "</a>";
                }

                $row[] = $course->fullname;
                $row[] = $cm ? $cm->get_formatted_name() : '';
                $row[] = self::render_status($entry->status);
                $row[] = implode(',&nbsp;', $reportlinks);

                $scheduled = $entry->status == 'scheduled' && $entry->timescheduled;

                $notstarted = $entry->status == 'new' || $scheduled;

                $row[] = $entry->score;

                $detailsurl = new \moodle_url('/availability/condition/proctor/index.php', [
                    'id' => $entry->id,
                    'action' => 'show',
                ]);

                $row[] = '<a href="' . $detailsurl . '">' . get_string('details', 'availability_proctor') . '</a>';

                // NTG-185: both buttons post action=renew, which calls
                // common::reset_entry() -- a write against
                // availability_proctor_entries. An SDK row has no entry to reset,
                // and an SDK session cannot be restarted from Moodle at all: the
                // student relaunches, and launch.php reuses or creates the session.
                // So the column is left empty rather than offering a control that
                // would either do nothing or touch the wrong table.
                if (config::is_sdk_model()) {
                    $row[] = '';
                    $table->add_data($row);
                    continue;
                }

                // Changed condition. Allow to reset all entries.
                // Consequences unknown.
                if (!$notstarted) {
                    $row[] =
                        "<form action='index.php' method='post'>" .
                           "<input type='hidden' name='sesskey' value='" . sesskey() . "'>" .
                           "<input type='hidden' name='id' value='" . $entry->id . "'>" .
                           "<input type='hidden' name='action' value='renew'>" .
                           "<input type='submit' value='" . get_string('new_entry', 'availability_proctor') . "'>" .
                        "</form>";
                } else {
                    $row[] =
                        "<form action='index.php' method='post'>" .
                           "<input type='hidden' name='sesskey' value='" . sesskey() . "'>" .
                           "<input type='hidden' name='id' value='" . $entry->id . "'>" .
                           "<input type='hidden' name='force' value='true'>" .
                           "<input type='hidden' name='action' value='renew'>" .
                           "<input type='submit' value='" . get_string('new_entry_force', 'availability_proctor') . "'>" .
                        "</form>";
                }
                $table->add_data($row);
            }
            $table->print_html();
        }
    }

    /**
     * The label for a status value.
     *
     * Was an inline get_string('status_' . $status, ...) call. It became a method
     * when the SDK model added a status whose value is derived rather than stored:
     * sdk_status() returns the raw state for a state it does not recognise, so a
     * future SDK state would otherwise reach get_string() with no lang entry and
     * raise a debugging notice on a reviewer's page.
     *
     * Falls back to the raw value, which is readable even untranslated -- the point
     * of the report is to tell a reviewer what happened, and an English state name
     * does that better than an error does.
     *
     * @param string $status A status value, hosted vocabulary.
     * @return string
     */
    public static function render_status(string $status): string {
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
    public static function render_reason(?string $reason): string {
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

        $courses = ['' => 'All modules'];

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
        $statuses = ['new', 'started', 'unknown', 'accepted', 'rejected', 'force_reset', 'finished', 'scheduled'];

        // NTG-185: Not submitted exists only under the SDK model, and it is the one
        // status that model has and the hosted vocabulary does not -- so without
        // this an SDK site could see the status in the column but never filter on
        // it.
        //
        // Appended rather than inserted, and only for an SDK site: the hosted list
        // is what customers' saved report URLs and habits are built around.
        //
        // force_reset and scheduled are deliberately left in place on both. Neither
        // can occur under the SDK model -- nothing resets an SDK session, and
        // payload.php sends 'schedule' => false -- so both simply return no rows.
        // Removing them would make the filter list disagree with itself depending on
        // the model, to save two entries that behave exactly as an unmatched filter
        // should.
        if (config::is_sdk_model()) {
            $statuses[] = 'notsubmitted';
        }

        $statuslist = [];

        foreach ($statuses as $status) {
            $statuslist[$status] = get_string('status_' . $status, 'availability_proctor');
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
        global $OUTPUT;

        $courseid = $this->filters['courseid'];

        $userquery = $this->filters['userquery'];
        $status = $this->filters['status'];

        echo html_writer::start_tag('form', ['class' => 'proctorlogselecform', 'action' => $this->url, 'method' => 'get']);
        echo html_writer::start_div();

        // Add course selector.
        $courses = $this->get_course_list();
        $statuses = $this->get_status_list();

        echo html_writer::start_div(null, ['class' => '', 'style' => 'padding: 0 0 0.8rem;']);
        echo html_writer::label(get_string('selectacourse'), 'menuid', false, ['class' => 'accesshide']);
        echo html_writer::select(
            $courses,
            "courseid",
            $courseid,
            get_string('allcourses', 'availability_proctor'),
            ['style' => 'height: 2.5rem;margin-right: 0.5rem']
        );

        // Add user selector.
        echo html_writer::label(get_string('selectauser', 'availability_proctor'), 'menuuser', false, ['class' => 'accesshide']);
        echo html_writer::empty_tag('input', [
            'name' => 'userquery',
            'value' => $userquery,
            'placeholder' => get_string("userquery", 'availability_proctor'),
            'class' => 'form-control',
            'style' => implode(';', [
                'width: auto',
                'clear: none',
                'display: inline-block',
                'vertical-align: middle',
                'font-size:inherit',
                'height: 2.5rem',
                'margin-right: 0.5rem',
            ]),
        ]);

        // Add status selector.
        echo html_writer::select(
            $statuses,
            "status",
            $status,
            get_string('allstatuses', 'availability_proctor'),
            ['style' => 'height: 2.5rem;margin-right: 0.5rem'],
        );

        // Add date selector.

        // Get the calendar type used - see MDL-18375.
        $calendartype = \core_calendar\type_factory::get_calendar_instance();
        $dateformat = $calendartype->get_date_order(2000, date('Y'));
        // Reverse date element (Day, Month, Year), in RTL mode.
        if (right_to_left()) {
            $dateformat = array_reverse($dateformat);
        }

        echo html_writer::end_div();

        // From date.
        echo html_writer::start_div(null, ['class' => 'fdate_selector', 'style' => 'padding: 0 0 0.8rem;']);

        echo html_writer::label(get_string('fromdate', 'availability_proctor'), '', false, ['style' => 'width: 12%;']);

        foreach ($dateformat as $key => $value) {
            $name = 'from[' . $key . ']';
            $current = isset($this->filters[$name]) ? $this->filters[$name] : null;

            echo html_writer::select($value, $name, $current, null, ['style' => 'height: 2.5rem;margin-right: 0.5rem']);
        }
        // The YUI2 calendar only supports the gregorian calendar type so only display the calendar image if this is being used.
        if ($calendartype->get_name() === 'gregorian') {
            form_init_date_js();
            echo html_writer::start_tag('a', [
                'href' => '#',
                'title' => get_string('calendar', 'calendar'),
                'class' => 'visibleifjs',
                'name' => 'from[calendar]',
            ]);
            echo $OUTPUT->pix_icon('i/calendar', get_string('calendar', 'calendar'), 'moodle');
            echo html_writer::end_tag('a');
        }
        echo html_writer::end_div();

        // To date.
        echo html_writer::start_div(null, ['class' => 'fdate_selector', 'style' => 'padding: 0 0 0.8rem;']);

        echo html_writer::label(get_string('todate', 'availability_proctor'), '', false, ['style' => 'width: 12%;']);

        foreach ($dateformat as $key => $value) {
            $name = 'to[' . $key . ']';
            $current = isset($this->filters[$name]) ? $this->filters[$name] : null;

            echo html_writer::select($value, $name, $current, null, ['style' => 'height: 2.5rem;margin-right: 0.5rem']);
        }
        // The YUI2 calendar only supports the gregorian calendar type so only display the calendar image if this is being used.
        if ($calendartype->get_name() === 'gregorian') {
            form_init_date_js();
            echo html_writer::start_tag('a', [
                'href' => '#',
                'title' => get_string('calendar', 'calendar'),
                'class' => 'visibleifjs',
                'name' => 'to[calendar]',
            ]);
            echo $OUTPUT->pix_icon('i/calendar', get_string('calendar', 'calendar'), 'moodle');
            echo html_writer::end_tag('a');
        }
        echo html_writer::end_div();

        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('apply_filter', 'availability_proctor'),
            'class' => 'btn btn-secondary',
        ]);

        echo html_writer::end_div();
        echo html_writer::end_tag('form');
    }
}
