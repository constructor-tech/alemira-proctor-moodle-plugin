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

$string['description'] = 'Allows students to use Constructor Proctor';
$string['pluginname'] = 'Constructor Proctor';
$string['title'] = 'Constructor Proctor';

$string['error_no_entry_found'] = 'No exam entry found by accesscode';
$string['error_not_in_range'] = 'This value should be between %d and %d';
$string['error_setduration'] = 'Duration must be a multiple of 30';

$string['settings_proctor_url'] = 'Constructor Proctor URL';
$string['settings_proctor_url_desc'] = '';
$string['settings_integration_name'] = 'Integration Name';
$string['settings_integration_name_desc'] = '';
$string['settings_jwt_secret'] = 'JWT Secret';
$string['settings_jwt_secret_desc'] = '';
$string['settings_account_name'] = 'Account Name';
$string['settings_account_name_desc'] = '';
$string['settings_account_id'] = 'Account ID';
$string['settings_account_id_desc'] = '';
$string['settings_user_emails'] = 'Send user emails to Constructor Proctor';
$string['settings_user_emails_desc'] = '';
$string['settings_seamless_auth'] = 'Seemless authorization';
$string['settings_seamless_auth_desc'] = 'Proctoring will store authorization token for users';

$string['description_proctor'] = 'You will be redirected to Constructor Proctor';
$string['description_no_webservices'] = 'Can not be accessed via Moodle mobile app';

$string['settings'] = 'Integration settings';
$string['log_section'] = 'Constructor Proctor log';
$string['status'] = 'Status';
$string['module'] = 'Module';
$string['new_entry'] = 'New entry';
$string['new_entry_force'] = 'New entry';
$string['duration'] = 'Exam duration (in minutes)';
$string['log_review'] = 'Review';
$string['log_archive_link'] = 'Video';
$string['log_report_link'] = 'Report';
$string['log_attempt'] = 'Attempt';
$string['log_attempt_missing'] = 'deleted';

$string['new_entry_created'] = 'New entry created';
$string['entry_exist'] = 'New entry already exist';
$string['date_modified'] = 'Date of last change';

$string['proctoring_mode'] = 'Proctoring mode';
$string['online_mode'] = 'Live review';
$string['offline_mode'] = 'Post-exam review';
$string['auto_mode'] = 'AI review';
$string['identification_mode'] = 'Live identity verification';

$string['identification'] = 'Identity verification mode';
$string['face_passport_identification'] = 'Face and Passport/ID';
$string['passport_identification'] = 'Passport/ID';
$string['face_identification'] = 'Face';
$string['skip_identification'] = 'None (Skip verification)';

$string['web_camera_main_view'] = 'Main camera positioning';
$string['web_camera_main_view_front'] = 'Front view';
$string['web_camera_main_view_side'] = 'Side view';

$string['calculator'] = 'Calculator';
$string['calculator_off'] = 'Off';
$string['calculator_simple'] = 'Simple';
$string['calculator_scientific'] = 'Scientific';

$string['select_groups'] = 'Enable proctoring for selected groups';

$string['is_trial'] = 'Demo exam mode';
$string['auxiliary_camera'] = 'Secondary camera';
$string['enable_ldb'] = 'Require Secure Browser';
$string['allowmultipledisplays'] = 'Allow multiple displays';
$string['allowvirtualenvironment'] = 'Virtual machine accesss';
$string['checkidphotoquality'] = 'Assess ID photo quality';

$string['rules'] = "Allow during exam";
$string['custom_rules'] = "Custom exam rules";

$string['user_agreement_url'] = "Terms and conditions URL";

$string['biometry_header'] = 'Biometric identification';
$string['biometry_enabled'] = 'Enable biometric identification';
$string['biometry_skipfail'] = 'Allow user to continue on failure of identification';
$string['biometry_flow'] = 'Verification flow name';
$string['biometry_theme'] = 'Theme';

$string['time_scheduled'] = 'Scheduled';
$string['time_finish'] = 'Attempt finished at';

$string['auto_rescheduling'] = 'Automatic rescheduling';
$string['enable'] = 'Enable';

$string['allow_to_use_websites'] = 'Browsing the internet';
$string['allow_to_use_books'] = 'Using books or reference materials';
$string['allow_to_use_paper'] = 'Taking notes on paper';
$string['allow_to_use_messengers'] = 'Using messengers';
$string['allow_to_use_calculator'] = 'Using calculator';
$string['allow_to_use_excel'] = 'Using Microsoft Excel';
$string['allow_to_use_human_assistant'] = 'Using other person’s help';
$string['allow_absence_in_frame'] = 'Leaving webcam frame';
$string['allow_voices'] = 'Talking';
$string['allow_wrong_gaze_direction'] = 'Prolonged looking away from screen';

$string['scoring_params_header'] = 'Scoring params';
$string['scoring_cheater_level'] = 'Сheat threshold';
$string['scoring_extra_user'] = 'Multiple persons in frame';
$string['scoring_user_replaced'] = 'Test taker substituted';
$string['scoring_absent_user'] = 'Test taker is absent';
$string['scoring_look_away'] = 'Prolonged looking away from screen';
$string['scoring_active_window_changed'] = 'Active window is changed';
$string['scoring_forbidden_device'] = 'Forbidden hardware';
$string['scoring_voice'] = 'Voice detected';
$string['scoring_phone'] = 'Phone is used';

$string['status_new'] = 'New';
$string['status_started'] = 'Started';
$string['status_unknown'] = 'Unknown';
$string['status_accepted'] = 'Accepted';
$string['status_rejected'] = 'Rejected';
$string['status_force_reset'] = 'Force reset';
$string['status_finished'] = 'Finished';
$string['status_scheduled'] = 'Scheduled';

$string['scheduling_required'] = 'Calendar booking required';
$string['apply_filter'] = 'Apply filter';
$string['allcourses'] = 'All courses';
$string['allstatuses'] = 'All statuses';
$string['userquery'] = 'Username or Email starts with';
$string['fromdate'] = 'From date:';
$string['todate'] = 'To date:';

$string['score'] = 'Cheating score modifier';
$string['threshold_attention'] = 'Threshold: Attention';
$string['threshold_rejected'] = 'Threshold: Rejection';
$string['session_start'] = 'Session start';
$string['session_end'] = 'Session end';
$string['warnings'] = 'Warnings';
$string['comment'] = 'Comment';

$string['details'] = 'Details';

// Fader screen.
$string['fader_awaiting_proctoring'] = 'Constructor Proctor proctoring starts...';
$string['fader_instructions'] = '<p>please wait</p>';
$string['fader_reset'] = 'Session was reset, you need to restart the exam';

// Dafault settings
$string['defaults'] = 'Default proctoring settings';
$string['defaults_proctoring_settings'] = 'Proctoring settings';

$string['log_details_warnings'] = 'Warnings';
$string['log_details_warning_type'] = 'Type';
$string['log_details_warning_title'] = 'Description';
$string['log_details_warning_start'] = 'Start';
$string['log_details_warning_end'] = 'End';

$string['visible_warnings'] = 'AI alert visibility';

$string['warning_extra_user_in_frame'] = 'Multiple persons in frame';
$string['warning_substitution_user'] = 'Test taker substituted';
$string['warning_no_user_in_frame'] = 'Test taker is absent';
$string['warning_avert_eyes'] = 'Prolonged looking away from screen';
$string['warning_timeout'] = 'Timeout, no connection';
$string['warning_change_active_window_on_computer'] = 'Active window is changed';
$string['warning_talk'] = 'Human voice in audiostream';
$string['warning_forbidden_software'] = 'Forbidden sites/software';
$string['warning_forbidden_device'] = 'Forbidden hardware';
$string['warning_voice_detected'] = 'Voice detected';
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
