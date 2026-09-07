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
 * Tests for the combined Webapp and SDK report.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_proctor;

/**
 * Tests for the combined Webapp and SDK report.
 *
 * @package    availability_proctor
 * @copyright  2026 Constructor Tech
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_proctor\log
 * @covers     \availability_proctor\log_details
 */
final class log_test extends \advanced_testcase {
    /**
     * Create one Webapp row and one SDK row with the same numeric id.
     *
     * @return array Webapp row, SDK row and page URL.
     */
    private function make_collision(): array {
        global $DB, $PAGE;

        $this->setAdminUser();
        $PAGE->set_url(new \moodle_url('/availability/condition/proctor/index.php'));

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $user = $generator->create_user();
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
        $now = time();

        $webapp = (object) [
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'attemptid' => null,
            'userid' => $user->id,
            'accesscode' => 'webapp-collision',
            'status' => 'success',
            'review_link' => 'javascript:alert(1)',
            'archiveurl' => 'https://example.test/webapp-video',
            'score' => 72,
            'threshold' => '{"attention":50,"rejected":70}',
            'timecreated' => $now - 60,
            'timemodified' => $now - 60,
        ];
        $webapp->id = $DB->insert_record('availability_proctor_entries', $webapp);

        $sdk = (object) [
            'attemptid' => null,
            'quizid' => $quiz->id,
            'userid' => $user->id,
            'sessionkey' => 'sdk-collision',
            // Timeout evidence can arrive while the browser-owned local state is
            // still active. The terminal result must still drive the report.
            'state' => session::STATE_ACTIVE,
            'reason' => 'app_unmounted',
            'conclusion' => 'accepted',
            'reporturl' => 'https://example.test/sdk-report',
            'score' => 20,
            'threshold' => '{"attention":50,"rejected":70}',
            'sessionend' => $now,
            'timeresult' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $sdk->id = $DB->insert_record(session::TABLE, $sdk);

        // PHPUnit gives different tables separate id ranges. Move the SDK primary
        // key onto the Webapp value to reproduce the collision possible on a real
        // upgraded site, where both tables normally begin at 1.
        if ($sdk->id !== $webapp->id) {
            $DB->set_field(session::TABLE, 'id', $webapp->id, ['id' => $sdk->id]);
            $sdk->id = $webapp->id;
        }

        $this->assertSame(
            $webapp->id,
            $sdk->id,
            'the fixture must reproduce the numeric id collision between source tables'
        );

        return [$webapp, $sdk, $PAGE->url];
    }

    /**
     * Both source rows survive a numeric id collision and render source-safe links.
     */
    public function test_report_combines_both_models_without_id_collision(): void {
        $this->resetAfterTest();
        [$webapp, $sdk] = $this->make_collision();
        set_config('proctormodel', config::MODEL_SDK, 'availability_proctor');

        $log = new class ([
            'courseid' => null,
            'timefinish' => null,
            'moduleid' => null,
            'userquery' => null,
            'status' => 'accepted',
        ], 0) extends log {
            /**
             * Return normalized report rows in display order.
             *
             * @return array
             */
            public function rows(): array {
                return array_values($this->entries);
            }

            /**
             * Return the row count before pagination.
             *
             * @return int
             */
            public function total(): int {
                return (int) $this->entriescount;
            }
        };

        $rows = $log->rows();
        $this->assertSame(2, $log->total());
        $this->assertCount(2, $rows);
        $this->assertSame((int) $sdk->id, (int) $rows[0]->id, 'the newer SDK row must be first');
        $this->assertSame(config::MODEL_SDK, $rows[0]->proctormodel);
        $this->assertSame((int) $webapp->id, (int) $rows[1]->id);
        $this->assertSame(config::MODEL_WEBAPP, $rows[1]->proctormodel);
        $this->assertSame(
            ['notstarted', 'inprogress', 'needsreview', 'accepted', 'rejected', 'closed'],
            array_keys($log->get_status_list())
        );

        ob_start();
        $log->render_table();
        $html = ob_get_clean();

        $this->assertStringContainsString('model=sdk', $html);
        $this->assertStringContainsString('model=webapp', $html);
        $this->assertSame(
            1,
            substr_count($html, 'name="action" value="renew"'),
            'only the Webapp row may offer the Webapp reset action'
        );
        $this->assertStringContainsString('badge-success', $html);
        $this->assertStringContainsString(get_string('status_accepted', 'availability_proctor'), $html);
        $this->assertStringNotContainsString('>success<', $html);
        $this->assertStringContainsString('https://example.test/sdk-report', $html);
        $this->assertStringNotContainsString('javascript:alert(1)', $html);
        $this->assertStringContainsString('noopener noreferrer', $html);
    }

    /**
     * Report presentation preserves meaning while safely handling external data.
     */
    public function test_report_presentation_helpers(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $groups = [
            'new' => 'notstarted',
            'scheduled' => 'notstarted',
            'started' => 'inprogress',
            'active' => 'inprogress',
            'finished' => 'needsreview',
            'unknown' => 'needsreview',
            'success' => 'accepted',
            'accepted' => 'accepted',
            'rejected' => 'rejected',
            'force_reset' => 'closed',
            'abandoned' => 'closed',
        ];
        foreach ($groups as $source => $group) {
            $this->assertSame($group, log::status_group($source));
        }

        $this->assertStringContainsString(
            'badge-danger',
            log::render_status_badge('rejected')
        );
        $this->assertStringContainsString(
            'availability-proctor-report-score-rejected',
            log::render_score(71, '{"attention":50,"rejected":70}')
        );
        $this->assertSame('', log::external_link('data:text/html,unsafe', 'Unsafe'));
        $this->assertStringContainsString(
            'target="_blank"',
            log::external_link('https://example.test/report', 'Report')
        );
        $this->assertStringContainsString('badge-light', log::render_model_badge(config::MODEL_SDK));
        $this->assertStringNotContainsString(
            'badge-primary',
            log::render_model_badge(config::MODEL_SDK)
        );

        $this->assertSame('started', log::sdk_status((object) [
            'state' => session::STATE_ACTIVE,
            'conclusion' => null,
            'sessionend' => null,
            'timeresult' => null,
        ]));
        $this->assertSame('finished', log::sdk_status((object) [
            'state' => session::STATE_ACTIVE,
            'conclusion' => 'unknown',
            'sessionend' => 123,
            'timeresult' => 124,
        ]));
        $this->assertSame('rejected', log::sdk_status((object) [
            'state' => session::STATE_ACTIVE,
            'conclusion' => 'rejected',
            'sessionend' => 123,
            'timeresult' => 124,
        ]));
    }

    /**
     * An explicit SDK source wins over the active site model.
     */
    public function test_sdk_detail_uses_link_source_when_webapp_is_active(): void {
        $this->resetAfterTest();
        [$webapp, $sdk, $url] = $this->make_collision();
        set_config('proctormodel', config::MODEL_WEBAPP, 'availability_proctor');

        ob_start();
        (new log_details($sdk->id, $url, config::MODEL_SDK))->render();
        $html = ob_get_clean();

        $this->assertStringContainsString(get_string('settings_proctormodel_sdk', 'availability_proctor'), $html);
        $this->assertStringContainsString(get_string('reason_app_unmounted', 'availability_proctor'), $html);
        $this->assertStringNotContainsString($webapp->accesscode, $html);
    }

    /**
     * A details URL from the public pre-SDK plugin remains a Webapp URL.
     */
    public function test_legacy_detail_without_source_stays_on_webapp(): void {
        $this->resetAfterTest();
        [$webapp, $sdk, $url] = $this->make_collision();
        set_config('proctormodel', config::MODEL_SDK, 'availability_proctor');

        ob_start();
        (new log_details($webapp->id, $url))->render();
        $html = ob_get_clean();

        $this->assertStringContainsString(get_string('settings_proctormodel_webapp', 'availability_proctor'), $html);
        $this->assertStringContainsString(get_string('status_success', 'availability_proctor'), $html);
        $this->assertStringContainsString($webapp->accesscode, $html);
        $this->assertStringNotContainsString(get_string('reason_app_unmounted', 'availability_proctor'), $html);
    }
}
