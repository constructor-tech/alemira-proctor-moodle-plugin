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

$string['proctor:logaccess'] = 'Proctor log access';
$string['proctor:logaccess_course'] = 'Proctor log access for course';
$string['proctor:logaccess_all'] = 'Proctor log access for all courses';

$string['description'] = 'Allows students to use Proctor by Constructor';
$string['pluginname'] = 'Proctor by Constructor';
$string['title'] = 'Proctor by Constructor';

$string['error_no_entry_found'] = 'No exam entry found by accesscode';

$string['settings_proctor_url'] = 'Proctor by Constructor URL';
$string['settings_proctor_url_desc'] = '';
$string['settings_integration_name'] = 'Integration Name';
$string['settings_integration_name_desc'] = '';
$string['settings_jwt_secret'] = 'JWT Secret';
$string['settings_jwt_secret_desc'] = '';
$string['settings_account_name'] = 'Account Name';
$string['settings_account_name_desc'] = '';
$string['settings_account_id'] = 'Account ID';
$string['settings_account_id_desc'] = '';
$string['settings_user_emails'] = 'Send user emails to Proctor by Constructor';
$string['settings_user_emails_desc'] = '';
$string['settings_seamless_auth'] = 'Seemless authorization';
$string['settings_seamless_auth_desc'] = 'Proctoring will store authorization token for users';

$string['use_proctor'] = 'Use proctor app to view this module';
$string['settings'] = 'Proctor by Constructor settings';
$string['log_section'] = 'Proctor by Constructor log';
$string['status'] = 'Status';
$string['module'] = 'Module';
$string['new_entry'] = 'New entry';
$string['new_entry_force'] = 'New entry';
$string['error_setduration'] = 'Duration must be a multiple of 30';
$string['duration'] = 'Duration in minutes (a multiple of 30)';
$string['log_review'] = 'Review';
$string['log_archive_link'] = 'Video';
$string['log_report_link'] = 'Report';
$string['log_attempt'] = 'Attempt';
$string['log_attempt_missing'] = 'deleted';

$string['new_entry_created'] = 'New entry created';
$string['entry_exist'] = 'New entry already exist';
$string['date_modified'] = 'Date of last change';

$string['proctoring_mode'] = 'Proctoring mode';
$string['online_mode'] = 'Real-time proctoring';
$string['offline_mode'] = 'Post-review';
$string['auto_mode'] = 'Fully automated';
$string['identification_mode'] = 'Live identification';

$string['identification'] = 'Identification mode';
$string['face_passport_identification'] = 'Face & Passport';
$string['passport_identification'] = 'Passport';
$string['face_identification'] = 'Face';
$string['skip_identification'] = 'Skip';

$string['is_trial'] = 'Trial exam';
$string['noprotection'] = 'No protection against starting outside Proctor by Constructor APP';
$string['auxiliary_camera'] = 'Auxiliary camera (mobile) ';
$string['enable_ldb'] = 'Require locked down browser';

$string['rules'] = "Rules";
$string['custom_rules'] = "Custom rules";

$string['user_agreement_url'] = "User agreement URL";

$string['biometry_header'] = 'Biometric identification settings';
$string['biometry_enabled'] = 'Enable biometric identification';
$string['biometry_skipfail'] = 'Allow user to continue on failure of identification';
$string['biometry_flow'] = 'Verification flow name';
$string['biometry_theme'] = 'Theme';

$string['time_scheduled'] = 'Scheduled';
$string['time_finish'] = 'Attempt finished at';

$string['auto_rescheduling'] = 'Automatic reset for missed exams';
$string['enable'] = 'Enable';

$string['allow_to_use_websites'] = 'Allow to use websites';
$string['allow_to_use_books'] = 'Allow to use books';
$string['allow_to_use_paper'] = 'Allow to use paper';
$string['allow_to_use_messengers'] = 'Allow to use messengers';
$string['allow_to_use_calculator'] = 'Allow to use calculator';
$string['allow_to_use_excel'] = 'Allow to use excel';
$string['allow_to_use_human_assistant'] = 'Allow to use human assistant';
$string['allow_absence_in_frame'] = 'Allow absence in frame';
$string['allow_voices'] = 'Allow voices';
$string['allow_wrong_gaze_direction'] = 'Allow wrong gaze direction';

$string['scoring_params_header'] = 'Scoring params';
$string['scoring_cheater_level'] = 'Cheater threshold';
$string['scoring_extra_user'] = 'An extra person in frame';
$string['scoring_user_replaced'] = 'The student is replaced';
$string['scoring_absent_user'] = 'The student is absent';
$string['scoring_look_away'] = 'Wrong gaze direction';
$string['scoring_active_window_changed'] = 'The active window is changed';
$string['scoring_forbidden_device'] = 'Forbidden hardware';
$string['scoring_voice'] = 'Microphone voice is detected';
$string['scoring_phone'] = 'A phone is used';

$string['status_new'] = 'New';
$string['status_started'] = 'Started';
$string['status_unknown'] = 'Unknown';
$string['status_accepted'] = 'Accepted';
$string['status_rejected'] = 'Rejected';
$string['status_force_reset'] = 'Force reset';
$string['status_finished'] = 'Finished';
$string['status_scheduled'] = 'Scheduled';

$string['scheduling_required'] = 'A calendar entry is required';
$string['apply_filter'] = 'Apply filter';
$string['allcourses'] = 'All courses';
$string['allstatuses'] = 'All statuses';
$string['userquery'] = 'Username or Email starts with';
$string['fromdate'] = 'From date:';
$string['todate'] = 'To date:';

$string['score'] = 'Score';
$string['threshold_attention'] = 'Threshold: Attention';
$string['threshold_rejected'] = 'Threshold: Rejection';
$string['session_start'] = 'Session start';
$string['session_end'] = 'Session end';
$string['warnings'] = 'Warnings';
$string['comment'] = 'Comment';

$string['details'] = 'Details';

// Fader screen.
$string['fader_awaiting_proctoring'] = 'Proctor by Constructor proctoring starts...';
$string['fader_instructions'] = '<p>please wait</p>';
$string['fader_reset'] = 'Session was reset, you need to restart the exam';

$string['log_details_warnings'] = 'Warnings';
$string['log_details_warning_type'] = 'Type';
$string['log_details_warning_title'] = 'Description';
$string['log_details_warning_start'] = 'Start';
$string['log_details_warning_end'] = 'End';

$string['visible_warnings'] = 'Visible warnings';

$string['warning_extra_user_in_frame'] = 'An extra person in frame';
$string['warning_substitution_user'] = 'The student is replaced';
$string['warning_no_user_in_frame'] = 'The student is absent';
$string['warning_avert_eyes'] = 'Wrong gaze direction';
$string['warning_timeout'] = 'Timeout, no connection';
$string['warning_change_active_window_on_computer'] = 'The active window is changed';
$string['warning_talk'] = 'Human voice in audiostream';
$string['warning_forbidden_software'] = 'Forbidden sites/software';
$string['warning_forbidden_device'] = 'Forbidden hardware';
$string['warning_voice_detected'] = 'Microphone voice is detected';
$string['warning_extra_display'] = 'Extra display is detected';
$string['warning_books'] = 'Books/notes are used';
$string['warning_cheater'] = 'Cheater';
$string['warning_mic_muted'] = 'Microphone is muted';
$string['warning_mic_no_sound'] = 'No sound from microphone';
$string['warning_mic_no_device_connected'] = 'Microphone is not connected';
$string['warning_camera_no_picture'] = 'No picture from camera';
$string['warning_camera_no_device_connected'] = 'Camera is not connected';
$string['warning_nonverbal'] = 'Non-verbal communication';
$string['warning_phone'] = 'Phone is used';
$string['warning_phone_screen'] = 'Phone screen detected';
$string['warning_no_ping'] = 'No connection';
$string['warning_desktop_request_pending'] = 'Desktop is not shared';

$string['privacy:path'] = 'Proctor exam entries';
$string['privacy:metadata:availability_proctor_entries'] = 'List of exam entries in proctoring system';
$string['privacy:metadata:availability_proctor_entries:courseid'] = 'Course ID';
$string['privacy:metadata:availability_proctor_entries:cmid'] = 'Course Module ID';
$string['privacy:metadata:availability_proctor_entries:attemptid'] = 'Quiz attempt ID';
$string['privacy:metadata:availability_proctor_entries:userid'] = 'The ID of the user that this record relates to.';
$string['privacy:metadata:availability_proctor_entries:accesscode'] = 'Accesscode';
$string['privacy:metadata:availability_proctor_entries:status'] = 'Entry status';
$string['privacy:metadata:availability_proctor_entries:review_link'] = 'Review URL';
$string['privacy:metadata:availability_proctor_entries:archiveurl'] = 'Archive URL';
$string['privacy:metadata:availability_proctor_entries:timecreated'] = 'Time when entry was created';
$string['privacy:metadata:availability_proctor_entries:timemodified'] = 'Time when entry was last modified';
$string['privacy:metadata:availability_proctor_entries:timescheduled'] = '';
$string['privacy:metadata:availability_proctor_entries:score'] = 'Proctoring score';
$string['privacy:metadata:availability_proctor_entries:comment'] = 'Proctor\'s comment';
$string['privacy:metadata:availability_proctor_entries:threshold'] = 'Score threshold';
$string['privacy:metadata:availability_proctor_entries:warnings'] = 'List of warnings received during proctoring session';
$string['privacy:metadata:availability_proctor_entries:sessionstart'] = 'Time when proctoring session started';
$string['privacy:metadata:availability_proctor_entries:sessionend'] = 'Time when proctoring session ended';
