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
$string['proctor:exemptproctoring'] = 'Sit a proctored quiz without proctoring';

$string['pluginname'] = 'Constructor Proctor';
$string['description'] = 'Allows students to use Constructor Proctor';
$string['title'] = 'Constructor Proctor';
$string['cachedef_session'] = 'Per-session proctoring state (access code, reset flag, secure browser session)';

$string['error_no_entry_found'] = 'No exam entry found by accesscode';
$string['error_not_in_range'] = 'This value should be between %d and %d';
$string['error_setduration'] = 'Duration must be a multiple of 30';
$string['error_preset_cannot_delete'] = 'This preset cannot be deleted (it is the system preset, the last remaining preset, or is in use by an exam).';
$string['error_preset_not_found'] = 'Preset not found.';
$string['proctor:managepresets'] = 'Manage proctoring presets';
$string['presets'] = 'Global proctoring presets';
$string['preset_create'] = 'Create preset';
$string['preset_edit'] = 'Edit preset';
$string['preset_name'] = 'Preset name';
$string['preset_is_default'] = 'Default';
$string['preset_is_system'] = 'System';
$string['preset_duplicate'] = 'Duplicate';
$string['preset_set_default'] = 'Set as default';
$string['preset_default_changed'] = 'Default preset changed.';
$string['preset_deleted'] = 'Preset deleted.';
$string['preset_saved'] = 'Preset saved.';
$string['preset_delete_confirm'] = 'Delete this preset?';
$string['preset_settings_summary'] = 'Settings';
$string['preset_section_identity_meta'] = 'Preset';
$string['preset_section_mode'] = 'Proctoring mode';
$string['preset_section_identity'] = 'Identity verification';
$string['preset_section_camera'] = 'Camera and monitoring';
$string['preset_section_securebrowser'] = 'Secure Browser';
$string['preset_section_resources'] = 'Exam environment';
$string['preset_section_rules'] = 'Allow during exam';
$string['preset_section_warnings'] = 'Alerts shown to student';
$string['preset_section_scoring'] = 'Scoring parameters';
$string['preset_section_exam'] = 'Exam settings';

// ---- Help strings (shown via Moodle addHelpButton and inline hints). ----
$string['proctoring_mode_help'] = 'Live — Live proctoring mode in which the exam is monitored by a human proctor in real-time (in addition to AI monitoring).<br>Review — Post-exam review mode in which examinees take the exam at any time under AI supervision. The session is recorded and later reviewed by a human proctor to assess for violations.<br>AI review — Fully automated mode without human review. The session is monitored by AI only; violations are flagged based on the configured scoring thresholds.';
$string['sendmanualwarningstolearner_help'] = 'Allows the proctor to send manual warnings to the learner during the exam, in addition to automatic AI warnings.';
$string['identification_help'] = 'Method used to verify the learner before the exam.';
$string['checkidphotoquality_help'] = 'Checks that the ID photo is readable and prompts the learner to re-take it if poor.';
$string['preliminary_check_help'] = 'Compares the pre-exam webcam capture to the learner\'s Moodle profile picture.';
$string['web_camera_main_view_help'] = 'Front view (default) uses the standard laptop webcam.
Auxiliary (side view) is for specific setups with a side-mounted camera and uses different AI models.';
$string['auxiliary_camera_help'] = 'A secondary camera accessed via a QR code scanned with the learner\'s phone. Recommended for high-stakes exams — captures the keyboard area and keeps the phone occupied.';
$string['allowroomscanauxcamera_help'] = 'Lets the learner perform a 360° room scan with the secondary camera.';
$string['allowmultipledisplays_help'] = 'When disabled, learners with multiple monitors connected cannot proceed (detected during the pre-exam screen-share step).';
$string['streamspreset_help'] = 'Default — standard video recording (recommended for online exams).
<br>No Video — disables video recording. It\'s typically used for on-campus exam rooms where the Secure Browser ensures learners cannot leave the exam page, making video recording unnecessary in a controlled physical environment.';
$string['enable_secure_browser_help'] = 'Requires learners to install the Constructor Proctor Secure Browser, a dedicated application that enforces machine-level restrictions during the exam.';
$string['secure_browser_level_help'] = 'Basic: detects virtual machines and USB devices.
<br>Medium: adds restrictions while allowing limited flexibility (recommended when allowing custom processes).
<br>High: full-screen always-on-top mode, blocks background applications.';
$string['allowtouseadditionalresources_help'] = 'Allows the learner to open additional PDF or web resources during the exam.';
$string['allowed_processes_help'] = 'Process names (one per line) that are allowed to run under Secure Browser.';
$string['forbidden_processes_help'] = 'Extra process names (one per line) to block under Secure Browser.';
$string['allowvirtualenvironment_help'] = 'Allows taking the exam from within a virtual machine. Detection requires Secure Browser. Typically used for computer-science exams.';
$string['calculator_help'] = 'Embeds an in-exam calculator widget available to the learner.';
$string['user_agreement_url_help'] = 'Optional URL displayed at the start of the pre-exam process so learners can review your exam terms.';
$string['is_trial_help'] = 'Runs this exam in demo mode — no video is uploaded to the proctoring dashboard. For testing equipment and flow without generating recordings.';
$string['custom_rules_help'] = 'Additional rules specific to your organization, shown to learners on the rules page in addition to the preset allow/forbid rules.';
$string['proctor_emails_help'] = 'Enter the emails of the proctors in this field. If nothing is entered, any available proctor will be assigned automatically.';
$string['select_groups_help'] = 'Apply proctoring only to the selected course groups. Learners in other groups can take the exam without proctoring.';

// Per-rule help — most are self-explanatory; the ones that also suppress AI warnings are called out.
$string['allow_to_use_websites_help'] = 'Allow learners to browse the Internet. Also disables the Active Window Changed warning.';
$string['allow_to_use_books_help'] = 'Allow learners to consult printed books or reference materials.';
$string['allow_to_use_paper_help'] = 'Allow learners to take notes on paper during the exam.';
$string['allow_to_use_messengers_help'] = 'Allow learners to use messaging apps.';
$string['allow_to_use_excel_help'] = 'Allow learners to use Microsoft Excel.';
$string['allow_to_use_human_assistant_help'] = 'Allow another person to be present with the learner.';
$string['allow_absence_in_frame_help'] = 'Allow the learner to briefly leave the webcam frame. Disables the Test-taker absent warning.';
$string['allow_voices_help'] = 'Allow the learner to talk during the exam. Disables the Voice Detected warning.';
$string['allow_wrong_gaze_direction_help'] = 'Useful for open-book exams. Disables the Looking Away warning.';

// Shared help for sections of uniform items. Moodle's addHelpButton requires
// both a title string (the identifier) and a body string (identifier + _help).
$string['warnings_help'] = 'Pop-up shown to the learner in-exam when this violation is detected. Detection still happens regardless of this setting — only learner visibility changes.';
$string['scoring'] = 'Scoring weight';
$string['scoring_help'] = 'Weight applied to this violation type when computing the learner\'s cheating score.';
$string['scoring_section_hint'] = 'Weight applied to this violation type when computing the learner\'s cheating score. Leave blank to use the default.';
// Per-warning hints.
$string['warning_change_active_window_on_computer_help'] = 'Detected when the learner switches windows during the exam. Automatically disabled when "Browsing the Internet" is allowed.';
$string['warning_voice_detected_help'] = 'Detected when voice is heard during the exam. Automatically disabled when "Talking" is allowed.';
$string['warning_avert_eyes_help'] = 'Detected when the learner looks away from the screen for a prolonged time. Automatically disabled when "Prolonged looking away from screen" is allowed.';
$string['warning_no_user_in_frame_help'] = 'Detected when no user is visible in the webcam. Automatically disabled when "Leaving webcam frame" is allowed.';
$string['warning_extra_user_in_frame_help'] = 'Detected when an additional person is visible in the webcam alongside the learner.';
$string['warning_substitution_user_help'] = 'Detected when the person currently in the webcam does not match the identified learner.';
$string['warning_forbidden_device_help'] = 'Detected when an external device (phone, tablet, second monitor) is visible in the webcam view.';
$string['warning_phone_help'] = 'Detected when a phone is visible in the webcam view.';

$string['scoring_cheater_level'] = 'Cheater score threshold';
$string['scoring_cheater_level_help'] = 'Overall threshold (0–100). Sessions scoring above this value are auto-flagged as cheaters. Default 80.';
$string['load_preset'] = '(change)';
$string['save_personal_preset'] = 'Save as personal preset';
$string['save_personal_preset_prompt'] = 'Enter a name for your personal preset.';
$string['save_personal_preset_hint'] = 'Personal presets are saved to your account and can be reused in any of your exams. They are not visible to other users. Global presets are managed by site administrators in Site administration > Plugins.';
$string['loaded_preset'] = 'Current preset: {$a}';
$string['loaded_preset_none'] = 'None';
$string['loaded_preset_custom'] = 'Custom';
$string['global_presets'] = 'Global presets';
$string['personal_presets'] = 'Personal presets';
$string['no_presets'] = 'No presets';
$string['delete'] = 'Delete';
$string['error_preset_name_required'] = 'Preset name is required.';
$string['error_useragreementurl'] = 'Please enter a valid URL starting with http:// or https:// (for example: https://example.com/terms).';
$string['error_invalid_payload'] = 'Invalid preset payload.';
$string['error_invalid_action'] = 'Invalid action.';
$string['error_internal'] = 'An internal error occurred. Please try again or contact your administrator.';
$string['error_preset_name_taken'] = 'A preset with this name already exists.';
$string['error_preset_user_quota'] = 'You have reached the maximum number of personal presets ({$a}). Delete an unused preset before saving a new one.';
$string['error_preset_default_missing'] = 'No default proctoring preset is available and the starter set could not be created automatically. Please contact your administrator.';
$string['preset_display_high_stakes'] = 'High-stakes exam';
$string['preset_display_low_stakes'] = 'Low-stakes exam';
$string['preset_display_open_book'] = 'Open-book exam';
$string['redirecting_to_proctor'] = 'Redirecting to {$a}';
$string['proctor_go_to_system'] = 'Go to proctoring system';
$string['secure_browser_enabled'] = 'Enabled';
$string['secure_browser_disabled'] = 'Disabled';

$string['settings_proctormodel'] = 'Proctoring model';
$string['settings_proctormodel_desc'] = 'How {$a} runs a proctored exam. The choice applies to the whole site.

<b>Webapp</b>
Constructor Proctor\'s own web application runs the proctoring session and shows Moodle inside an iframe on its page. Moodle must therefore allow Constructor to frame it: serve this site over HTTPS, turn on Allow frame embedding in Site security settings, and send session cookies with SameSite=None and Secure.

<b>SDK</b>
The proctoring session runs on a page belonging to this plugin, and the quiz opens in an iframe on that same page. Both are on this site, so nothing is framed across websites and no frame embedding permission is needed.

You can change this at any time. It takes effect on exams that have not started yet. A proctoring session already under way finishes the way it began.';
$string['settings_proctormodel_webapp'] = 'Webapp';
$string['settings_proctormodel_sdk'] = 'SDK';
$string['settings_proctor_url'] = '{$a} URL';
$string['settings_proctor_url_desc'] = '';
$string['settings_integration_name'] = 'Integration Name';
$string['settings_integration_name_desc'] = '';
$string['settings_jwt_secret'] = 'JWT Secret';
$string['settings_jwt_secret_desc'] = '';
$string['settings_account_name'] = 'Account Name';
$string['settings_account_name_desc'] = '';
$string['settings_account_id'] = 'Account ID';
$string['settings_account_id_desc'] = '';
$string['settings_user_emails'] = 'Send user emails to {$a}';
$string['settings_user_emails_desc'] = 'If enabled, Moodle sends learner email addresses to Constructor Proctor, where they identify the learner in the dashboard. If disabled, no email address leaves Moodle: Constructor Proctor generates a unique placeholder address for each learner and uses that as the identifier instead.';

// SDK proctoring model: the launch page, its session endpoints and the
// scheduled task. Only used when the site runs the SDK model.
$string['backtoquiz'] = 'Back to the quiz';

$string['error_abandoned'] = 'Proctoring stopped unexpectedly. Your Moodle attempt and saved answers are kept, but your teacher must create a New entry before you can continue.';
$string['error_nojavascript'] = 'JavaScript is turned off in your browser. The proctoring software needs it, and this quiz cannot be attempted without a proctoring session. Turn JavaScript on and reload this page.';
$string['error_noquestions'] = 'This quiz has no questions yet, so it cannot be attempted. Contact your teacher.';

// Thrown by token.php when the session cannot be activated from the state it is
// in, and by jwt::sign() when the claims cannot be encoded. Both reach the learner
// -- proctor.js shows the error property of an AJAX failure -- so both are written
// for them rather than for whoever reads the log. The state, and the encoding
// failure, travel in the exception's debug channel instead.
$string['error_badtransition'] = 'This proctoring session can no longer be started. Reload this page to begin a new one, and contact your teacher if it happens again.';
$string['error_jwtencode'] = 'Proctoring could not be started because the session details could not be prepared. Contact your teacher or site administrator.';
// Admin-facing: this one is raised inside a scheduled task, so it is only ever
// read in the task log.
$string['error_finishfailed'] = 'Could not tell Constructor Proctor to stop recording this session. The task will be retried.';
$string['error_nosession'] = 'No proctoring session has been started for this attempt. Start the exam from the launch page.';
$string['error_notconfigured_learner'] = 'Proctoring is not set up correctly on this site, so the exam cannot start. Contact your teacher or site administrator.';
$string['error_notimelimit'] = 'This quiz has no time limit. A proctoring session needs a duration, so set a time limit in the quiz settings.';
$string['error_notproctored'] = 'Proctoring is not enabled for this quiz.';
$string['error_notyoursession'] = 'That proctoring session belongs to a different user.';
$string['error_sessionfinished'] = 'This attempt is finished and cannot be reopened.';
$string['error_sessionended_newentry'] = 'This proctoring session has ended. Your Moodle attempt and saved answers are still available, but your teacher must create a New entry before you can continue.';
$string['error_sdk_new_entry_attempt_finished'] = 'A new SDK entry cannot be created because the Moodle attempt is no longer in progress.';
$string['error_sdk_new_entry_busy'] = 'Another New entry request is already being processed. Try again.';
$string['error_sdk_new_entry_session_active'] = 'The current Constructor session has not finished, so no new entry was created. End it first or wait for the reconnect timeout.';
$string['error_sdk_status_unavailable'] = 'Constructor session status could not be verified, so no new entry was created. Try again later.';
$string['error_unknownevent'] = 'Unknown proctoring session event.';

$string['launch_error_lead'] = 'Proctoring could not start.';
$string['launch_error_server'] = 'The server returned an unexpected response.';
$string['launch_error_technical'] = 'Technical details';
$string['launch_requirement_browser'] = 'Use desktop Google Chrome or Microsoft Edge. Other browsers are not supported.';
$string['launch_requirement_camera'] = 'You need a working camera and microphone, and you will be asked to share your screen.';
$string['launch_requirement_stay'] = 'Stay on this page until the exam is over and the recording has finished uploading. Closing it early can lose part of the recording.';
$string['launch_preparing'] = 'Preparing your proctored exam';
$string['launch_retry'] = 'Try again';
$string['launch_starting'] = 'The system check and identification steps will open in a moment.';
$string['launch_timeout'] = 'This is taking longer than expected. Check your connection and try again.';
$string['launch_timeout_pending'] = 'Proctoring is taking longer than expected to start. You can wait or return to the quiz page. Try again will become available if startup fails.';
$string['notice_recordingstopped'] = 'Recording has stopped, but your attempt is still open. Submit your answers now to finish.';

$string['task_finishsession'] = 'Retry stopping a proctoring session';
$string['task_purgeorphans'] = 'Remove proctoring records for deleted quizzes';

$string['event_session_started'] = 'Proctoring session started';
$string['event_session_finished'] = 'Proctoring session finished';
$string['event_user_logged_in_via_token'] = 'User logged in via seamless auth token';

$string['settings_seamless_auth'] = 'Seamless authorization';
$string['settings_seamless_auth_desc'] = 'Forwards a short-lived, single-use Moodle authentication token to Proctor so learners are logged in automatically when redirected back. Recommended when using Secure Browser. Security note: tokens are valid for 8 hours and bound to a specific exam entry. On shared exam devices, learners should log out after completing the exam.';

$string['description_proctor'] = 'You will be redirected to {$a}';
$string['description_no_webservices'] = 'Can not be accessed via Moodle mobile app';

$string['settings'] = 'Integration settings';
$string['log_section'] = '{$a} log';
$string['log_model'] = 'Model';
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
$string['report_heading'] = 'Proctoring reports';
$string['report_intro'] = 'Review Webapp and SDK proctoring sessions in one place.';
$string['report_filterlegend'] = 'Filter proctoring sessions';
$string['report_clearfilters'] = 'Clear filters';
$string['report_resultcount'] = 'Sessions: {$a}';
$string['report_nomatches'] = 'No proctoring sessions match these filters.';
$string['report_nosessions'] = 'No proctoring sessions have been recorded yet.';
$string['report_finished'] = 'Finished at';
$string['report_activity'] = 'Activity';
$string['report_score'] = 'Score';
$string['report_actions'] = 'Actions';
$string['report_details_heading'] = 'Proctoring session details';
$string['report_back'] = 'Back to reports';
$string['report_field'] = 'Field';
$string['report_value'] = 'Value';
$string['report_accesscode'] = 'Access code';
$string['report_deleted_user'] = 'Deleted user';
$string['report_deleted_course'] = 'Deleted course';
$string['report_deleted_activity'] = 'Deleted activity';
$string['report_status_notstarted'] = 'Not started';
$string['report_status_inprogress'] = 'In progress';
$string['report_status_needsreview'] = 'Needs review';
$string['report_status_accepted'] = 'Accepted';
$string['report_status_rejected'] = 'Rejected';
$string['report_status_closed'] = 'Closed';
$string['report_source_status'] = 'Source status';
$string['report_source_state'] = 'Source state';
$string['report_source_conclusion'] = 'Source conclusion';

$string['new_entry_created'] = 'New entry created';
$string['entry_exist'] = 'A new entry already exists.';
$string['date_modified'] = 'Date of last change';

$string['proctoring_mode'] = 'Proctoring mode';
$string['online_mode'] = 'Live';
$string['offline_mode'] = 'Review';
$string['auto_mode'] = 'AI review';
$string['identification_mode'] = 'Identity verification';

$string['identification'] = 'Identity verification mode';
$string['face_passport_identification'] = 'Face and ID';
$string['passport_identification'] = 'Only ID';
$string['face_identification'] = 'Only face';
$string['skip_identification'] = 'Skip';

$string['web_camera_main_view'] = 'Main camera positioning';
$string['web_camera_main_view_front'] = 'Front view';
$string['web_camera_main_view_side'] = 'Auxiliary (side view)';

$string['calculator'] = 'Calculator';
$string['calculator_off'] = 'Off';
$string['calculator_simple'] = 'Simple';
$string['calculator_scientific'] = 'Scientific';

$string['streamspreset'] = 'Recording mode';
$string['streamspreset_default'] = 'Default';
$string['streamspreset_no_video'] = 'No video';
$string['streamspreset_no_ai_detection'] = 'No AI Processing';
$string['streamspreset_no_webcam'] = 'No webcam';
$string['streamspreset_auxcam_and_desktop'] = 'Aux camera and desktop';
$string['streamspreset_auxcam_only'] = 'Aux camera only';

$string['select_groups'] = 'Enable proctoring for selected groups';

$string['is_trial'] = 'Demo exam mode';

$string['auxiliary_camera'] = 'Secondary camera';
$string['auxiliary_camera_off'] = 'Off';
$string['auxiliary_camera_on'] = 'On';


$string['enable_secure_browser'] = 'Require Secure Browser';
$string['secure_browser_level'] = 'Secure Browser level';
$string['secure_browser_level_basic'] = 'Basic';
$string['secure_browser_level_medium'] = 'Medium';
$string['secure_browser_level_high'] = 'High';
$string['allowtouseadditionalresources'] = 'Allow using additional resources (PDF/web)';
$string['allowed_processes'] = 'Allowed processes';
$string['forbidden_processes'] = 'Forbidden processes';
$string['processes_list_hint'] = 'One process name per line';

$string['allowmultipledisplays'] = 'Allow multiple displays';
$string['allowvirtualenvironment'] = 'Virtual machine accesss';
$string['checkidphotoquality'] = 'Assess ID photo quality';
$string['sendmanualwarningstolearner'] = 'Send manual warnings to learner';
$string['allowroomscanauxcamera'] = 'Allow room scan using aux camera';

$string['rules'] = "Allow during exam";
$string['custom_rules'] = "Custom exam rules";
$string['proctor_emails'] = "Assign proctors";
$string['proctor_emails_placeholder'] = "proctor1@example.com\nproctor2@example.com";

$string['user_agreement_url'] = "Terms and conditions URL";

$string['preliminary_check'] = 'Preliminary check of student\'s identity';

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
$string['scoring_extra_user'] = 'Multiple persons in frame';
$string['scoring_user_replaced'] = 'Test taker substituted';
$string['scoring_absent_user'] = 'Test taker is absent';
$string['scoring_look_away'] = 'Prolonged looking away from screen';
$string['scoring_active_window_changed'] = 'Active window is changed';
$string['scoring_forbidden_device'] = 'Forbidden hardware';
$string['scoring_voice'] = 'Voice detected';
$string['scoring_phone'] = 'Phone is used';
$string['scoring_mic_muted'] = 'Microphone is muted';
$string['scoring_mic_no_device'] = 'Microphone is not connected';
$string['scoring_mic_no_sound'] = 'Microphone silence';
$string['scoring_camera_no_device'] = 'Camera is not connected';
$string['scoring_camera_no_picture'] = 'No image from camera';
$string['scoring_no_aux_camera_photo'] = 'No aux camera photo';
$string['scoring_no_ping'] = 'Test taker application lost its connection with the server';
$string['scoring_desktop_request_pending'] = 'Desktop request pending';
$string['scoring_account_collision'] = 'Using multiple accounts across one exam';

$string['status_new'] = 'New';
$string['status_started'] = 'Started';
$string['status_unknown'] = 'Unknown';
$string['status_accepted'] = 'Accepted';
$string['status_rejected'] = 'Rejected';
$string['status_force_reset'] = 'Force reset';
$string['status_finished'] = 'Finished';
$string['status_scheduled'] = 'Scheduled';
$string['status_success'] = 'Success (legacy)';
$string['status_canceled'] = 'Canceled';
$string['status_rescheduled'] = 'Rescheduled';
$string['status_pending'] = 'Pending';
$string['status_active'] = 'Active';
$string['status_finishing'] = 'Finishing';
$string['status_abandoned'] = 'Abandoned';

$string['scheduling_required'] = 'Calendar booking required';
$string['apply_filter'] = 'Apply filter';
$string['allcourses'] = 'All courses';
$string['allmodules'] = 'All modules';
$string['allstatuses'] = 'All statuses';
$string['selectauser'] = 'Select a user';
$string['userquery'] = 'Name, username or email';
$string['fromdate'] = 'From date';
$string['todate'] = 'To date';

$string['score'] = 'Cheating score modifier';
$string['threshold_attention'] = 'Threshold: Attention';
$string['threshold_rejected'] = 'Threshold: Rejection';
$string['session_start'] = 'Session start';
$string['session_end'] = 'Session end';
$string['comment'] = 'Comment';

$string['details'] = 'Details';

// Fader screen.
$string['fader_awaiting_proctoring'] = 'Proctoring is launching.';
$string['fader_instructions'] = '<p>Please wait and do not close the page</p>';
$string['fader_reset'] = 'Session was reset, you need to restart the exam';

// Default settings.
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
$string['privacy:metadata:proctor_service'] = 'Personal data transmitted to the Constructor Proctor service to create and manage proctored exam sessions.';
$string['privacy:metadata:proctor_service:userid'] = 'The Moodle username used as a unique identifier in the proctoring service.';
$string['privacy:metadata:proctor_service:firstname'] = 'The first name of the user.';
$string['privacy:metadata:proctor_service:lastname'] = 'The last name of the user.';
$string['privacy:metadata:proctor_service:middlename'] = 'The middle name of the user.';
$string['privacy:metadata:proctor_service:email'] = 'The email address of the user (sent only when the "Send user emails" setting is enabled).';
$string['privacy:metadata:proctor_service:photo_url'] = 'URL of the user\'s Moodle profile picture, used for identity verification during the preliminary check.';
$string['privacy:metadata:proctor_service:language'] = 'The interface language of the user.';
$string['privacy:metadata:proctor_service:specialaccommodationsinfo'] = 'Special accommodations information from the user\'s custom Moodle profile field.';
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
$string['privacy:metadata:availability_proctor_entries:timescheduled'] = 'Time when the exam session was scheduled';
$string['privacy:metadata:availability_proctor_entries:score'] = 'Proctoring score';
$string['privacy:metadata:availability_proctor_entries:comment'] = 'Proctor\'s comment';
$string['privacy:metadata:availability_proctor_entries:threshold'] = 'Score threshold';
$string['privacy:metadata:availability_proctor_entries:warnings'] = 'List of warnings received during proctoring session';
$string['privacy:metadata:availability_proctor_entries:sessionstart'] = 'Time when proctoring session started';
$string['privacy:metadata:availability_proctor_entries:sessionend'] = 'Time when proctoring session ended';

$string['privacy:path:presets'] = 'Personal proctoring presets';
$string['privacy:metadata:availability_proctor_presets'] = 'Personal proctoring presets created by the user and stored in the Moodle database.';
$string['privacy:metadata:availability_proctor_presets:userid'] = 'The ID of the user who owns this preset.';
$string['privacy:metadata:availability_proctor_presets:name'] = 'The name given to the preset by the user.';
$string['privacy:metadata:availability_proctor_presets:mode'] = 'Proctoring mode configured in the preset.';
$string['privacy:metadata:availability_proctor_presets:schedulingrequired'] = 'Whether calendar booking is required.';
$string['privacy:metadata:availability_proctor_presets:autorescheduling'] = 'Whether automatic rescheduling is enabled.';
$string['privacy:metadata:availability_proctor_presets:identification'] = 'Identity verification method configured in the preset.';
$string['privacy:metadata:availability_proctor_presets:checkidphotoquality'] = 'Whether ID photo quality check is enabled.';
$string['privacy:metadata:availability_proctor_presets:useragreementurl'] = 'URL of the terms and conditions configured in the preset.';
$string['privacy:metadata:availability_proctor_presets:preliminarycheck'] = 'Whether preliminary identity check is enabled.';
$string['privacy:metadata:availability_proctor_presets:webcameramainview'] = 'Main camera positioning configured in the preset.';
$string['privacy:metadata:availability_proctor_presets:auxiliarycamera'] = 'Secondary camera setting configured in the preset.';
$string['privacy:metadata:availability_proctor_presets:allowroomscanauxcamera'] = 'Whether room scan with auxiliary camera is allowed.';
$string['privacy:metadata:availability_proctor_presets:streamspreset'] = 'Recording mode configured in the preset.';
$string['privacy:metadata:availability_proctor_presets:sendmanualwarningstolearner'] = 'Whether manual warnings to learner are enabled.';
$string['privacy:metadata:availability_proctor_presets:securebrowser'] = 'Whether Secure Browser is required.';
$string['privacy:metadata:availability_proctor_presets:securebrowserlevel'] = 'Secure Browser restriction level configured in the preset.';
$string['privacy:metadata:availability_proctor_presets:allowedprocesses'] = 'List of allowed processes configured in the preset.';
$string['privacy:metadata:availability_proctor_presets:forbiddenprocesses'] = 'List of forbidden processes configured in the preset.';
$string['privacy:metadata:availability_proctor_presets:allowvirtualenvironment'] = 'Whether virtual machine access is allowed.';
$string['privacy:metadata:availability_proctor_presets:allowtouseadditionalresources'] = 'Whether using additional resources is allowed.';
$string['privacy:metadata:availability_proctor_presets:allowmultipledisplays'] = 'Whether multiple displays are allowed.';
$string['privacy:metadata:availability_proctor_presets:calculator'] = 'Calculator type configured in the preset.';
$string['privacy:metadata:availability_proctor_presets:rules'] = 'Exam rules configured in the preset.';
$string['privacy:metadata:availability_proctor_presets:warnings'] = 'Warning visibility settings configured in the preset.';
$string['privacy:metadata:availability_proctor_presets:scoring'] = 'Scoring parameters configured in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkallowcameraselection'] = 'SDK camera-selection setting stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkalwaysshowchat'] = 'SDK chat-visibility setting stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkbeforeexamlist'] = 'SDK pre-exam checklist setting stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkdesktopcheckfirst'] = 'SDK screen-sharing order setting stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkdisabledworkers'] = 'SDK background checks disabled by the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkexperimentwatchwindowsize'] = 'SDK window-size monitoring setting stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkfocusrespectsrules'] = 'SDK focus detection setting stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkfullscreenwatchfocusalert'] = 'SDK full-screen alert setting stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkidentificationbullets'] = 'SDK identification onboarding JSON stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkobserveworkspace'] = 'SDK Room Scan duration stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkopenagreementlinksiniframe'] = 'SDK agreement-link behavior stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkaudionotification'] = 'SDK chat audio notification setting stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkshowfinishexambutton'] = 'SDK Finish exam button setting stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdkshowsyschecksupportnew'] = 'SDK support-link visibility setting stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdksyscheckhelpurl'] = 'SDK support URL stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:sdktranslationsoverride'] = 'SDK translation override JSON stored in the preset.';
$string['privacy:metadata:availability_proctor_presets:timecreated'] = 'Time when the preset was created.';
$string['privacy:metadata:availability_proctor_presets:timemodified'] = 'Time when the preset was last modified.';

// NTG-185: the SDK proctoring model's session and denial tables.
$string['privacy:path:sdk'] = 'Proctoring sessions';
$string['privacy:metadata:availability_proctor_sess'] = 'One proctoring session per quiz attempt under the SDK proctoring model, including its outcome as reported by Constructor Proctor.';
$string['privacy:metadata:availability_proctor_sess:attemptid'] = 'The quiz attempt the session was bound to.';
$string['privacy:metadata:availability_proctor_sess:quizid'] = 'The quiz the session belongs to.';
$string['privacy:metadata:availability_proctor_sess:userid'] = 'The user the session belongs to.';
$string['privacy:metadata:availability_proctor_sess:sessionkey'] = 'The identifier this session is known by on the Constructor Proctor side.';
$string['privacy:metadata:availability_proctor_sess:state'] = 'How far the session progressed: pending, active, finishing, finished or abandoned.';
$string['privacy:metadata:availability_proctor_sess:reason'] = 'Why the session ended, when it did not end with a submission.';
$string['privacy:metadata:availability_proctor_sess:timecreated'] = 'Time when the session was created.';
$string['privacy:metadata:availability_proctor_sess:timemodified'] = 'Time when the session was last modified.';
$string['privacy:metadata:availability_proctor_sess:timeactive'] = 'Time when proctoring became active.';
$string['privacy:metadata:availability_proctor_sess:timebound'] = 'Time when the session was bound to a quiz attempt.';
$string['privacy:metadata:availability_proctor_sess:timefinishing'] = 'Time when recording stopped.';
$string['privacy:metadata:availability_proctor_sess:timefinished'] = 'Time when the quiz attempt was submitted.';
$string['privacy:metadata:availability_proctor_sess:timeabandoned'] = 'Time when the proctoring window was closed without submitting.';
$string['privacy:metadata:availability_proctor_sess:videouploaded'] = 'Time when the session recording finished uploading.';
$string['privacy:metadata:availability_proctor_sess:timefinishsent'] = 'Time when Constructor Proctor was told the exam was over.';
$string['privacy:metadata:availability_proctor_sess:conclusion'] = 'The proctoring conclusion: accepted, rejected or unknown.';
$string['privacy:metadata:availability_proctor_sess:score'] = 'The proctoring score.';
$string['privacy:metadata:availability_proctor_sess:comment'] = 'Notes recorded by the proctor.';
$string['privacy:metadata:availability_proctor_sess:threshold'] = 'The score thresholds the session was judged against.';
$string['privacy:metadata:availability_proctor_sess:reporturl'] = 'Link to the session report.';
$string['privacy:metadata:availability_proctor_sess:archiveurl'] = 'Link to the session recording.';
$string['privacy:metadata:availability_proctor_sess:resultwarnings'] = 'Warnings registered during the exam.';
$string['privacy:metadata:availability_proctor_sess:warningtitles'] = 'Translated labels for the warning types registered during the exam.';
$string['privacy:metadata:availability_proctor_sess:sessionstart'] = 'Time when proctoring started, as reported by Constructor Proctor.';
$string['privacy:metadata:availability_proctor_sess:sessionend'] = 'Time when proctoring finished, as reported by Constructor Proctor.';
$string['privacy:metadata:availability_proctor_sess:timeresult'] = 'Time when the proctoring result was last received.';
$string['privacy:metadata:availability_proctor_deny'] = 'Occasions on which a user was refused access to a proctored quiz.';
$string['privacy:metadata:availability_proctor_deny:attemptid'] = 'The quiz attempt the refusal related to.';
$string['privacy:metadata:availability_proctor_deny:quizid'] = 'The quiz the refusal related to.';
$string['privacy:metadata:availability_proctor_deny:userid'] = 'The user who was refused.';
$string['privacy:metadata:availability_proctor_deny:sessionid'] = 'The proctoring session the refusal related to.';
$string['privacy:metadata:availability_proctor_deny:sessionstate'] = 'The state the proctoring session was in when access was refused.';
$string['privacy:metadata:availability_proctor_deny:reason'] = 'Why access was refused.';
$string['privacy:metadata:availability_proctor_deny:script'] = 'The page on which access was refused.';
$string['privacy:metadata:availability_proctor_deny:timecreated'] = 'Time when access was refused.';

// NTG-185: the SDK's own init() parameters. Every description below is written from
// the Constructor Proctor Developer Guide's own definition of the parameter.
// SDK preset fields are rendered in groups; see config::SDK_GROUPS.
$string['settings_sdk_group_before'] = 'Before the exam';
$string['settings_sdk_group_during'] = 'During the exam';
$string['settings_sdk_group_violations'] = 'Rule violations';
$string['settings_sdk_group_wording'] = 'Wording';
$string['settings_sdk_group_advanced'] = 'Advanced';

$string['settings_sdk_allowcameraselection'] = 'Allow camera selection';
$string['settings_sdk_allowcameraselection_desc'] = 'Lets the learner pick which camera records them during the pre-exam check. It only makes a difference for learners with more than one camera, such as a laptop webcam plus an external one; with a single camera, or when this is disabled, Constructor Proctor selects the camera itself.';

$string['settings_sdk_alwaysshowchat'] = 'Always show the chat';
$string['settings_sdk_alwaysshowchat_desc'] = 'Keeps the chat available in every proctoring mode, not only in Live mode. A learner who runs into a problem then always has a way to report it.';

$string['settings_sdk_beforeexamlist'] = 'Show the pre-exam checklist';
$string['settings_sdk_beforeexamlist_desc'] = 'Shows the learner the list of steps in the pre-exam check before it starts, so they know what to expect.';

$string['settings_sdk_desktopcheckfirst'] = 'Check screen sharing first';
$string['settings_sdk_desktopcheckfirst_desc'] = 'Puts the screen-sharing check before the camera check. Useful when screen sharing is the step learners most often fail, since they find out sooner.';

$string['settings_sdk_focusrespectsrules'] = 'Focus detection respects exam rules';
$string['settings_sdk_focusrespectsrules_desc'] = 'When on, switching away from the exam window is not reported as a violation if the exam rules permit the use of other applications or websites. Turn off to report every focus change regardless of the rules.';

$string['settings_sdk_fullscreenwatchfocusalert'] = 'Show full-screen violation alerts';
$string['settings_sdk_fullscreenwatchfocusalert_desc'] = 'Shows the learner a full-screen alert when a rule violation is detected. Turning this off hides the alerts from the learner but does not stop detection: focus changes are still recorded and still appear in the report.';

$string['settings_sdk_observeworkspace'] = 'Room Scan duration';
$string['settings_sdk_observeworkspace_desc'] = 'How long the Room Scan screen is shown to the learner, in seconds. This is a minimum display time, not a check interval. Set 0 to skip the Room Scan screen entirely.';

$string['settings_sdk_openagreementlinksiniframe'] = 'Open agreement links in place';
$string['settings_sdk_openagreementlinksiniframe_desc'] = 'Opens links in the proctoring agreement inside the exam page instead of a new browser tab. Leave off if a new tab is preferred, since a link opened in place replaces what the learner was reading.';

$string['settings_sdk_audionotification'] = 'Play a sound for chat messages';
$string['settings_sdk_audionotification_desc'] = 'Plays a sound when a chat message arrives, so the learner notices it without watching the panel.';

$string['settings_sdk_showfinishexambuttonforsessionswithoutproctor'] = 'Show Finish exam button without a proctor';
$string['settings_sdk_showfinishexambuttonforsessionswithoutproctor_desc'] = 'Shows a Finish exam button in the proctoring panel on exams with no live proctor. Learners can always finish through the quiz itself; this adds a second way to end the proctoring session.';

$string['settings_sdk_showsyschecksupportnew'] = 'Show a support link during checks';
$string['settings_sdk_showsyschecksupportnew_desc'] = 'Shows a support link on the pre-exam check pages, so a learner whose camera or microphone fails can reach help without leaving the exam. Set the address in the setting below.';

$string['settings_sdk_syscheckhelpurl'] = 'Support link address';
$string['settings_sdk_syscheckhelpurl_desc'] = 'The address the support link opens. Used only when the support link is switched on above; leave empty to show no link.';

$string['settings_sdk_experimentwatchwindowsize'] = 'Monitor the exam window size (experimental)';
$string['settings_sdk_experimentwatchwindowsize_desc'] = 'Watches the size of the exam window and warns the learner if it becomes too small to monitor reliably. Marked experimental by Constructor, so test it before using it in a real exam.';

$string['settings_sdk_disabledworkers'] = 'Disabled background checks';
$string['settings_sdk_disabledworkers_desc'] = 'This advanced setting changes which monitoring checks run during SDK exams. Contact support@constructor.tech before using it, then copy and paste only the values provided by Constructor Support. Leave it empty otherwise; an incorrect value can leave part of an exam unmonitored.';

// NTG-185: report page entries for the SDK proctoring model.
$string['status_notsubmitted'] = 'Not submitted';
$string['log_reason'] = 'Reason';
$string['log_entry_missing'] = 'This proctoring session no longer exists.';
$string['reason_app_unmounted'] = 'The learner closed the proctoring window';
$string['reason_bind_timeout'] = 'The exam was never started';
$string['reason_new_entry'] = 'Replaced by an instructor with New entry';

// NTG-185: the SDK proctoring model requires a newer Moodle than the plugin as a whole.
$string['error_sdk_unsupported'] = 'Proctoring cannot start because this Moodle version does not support the SDK proctoring model. Moodle 3.11 or later is required. Please contact your site administrator.';
$string['settings_proctormodel_unsupported'] = 'The SDK proctoring model requires Moodle 3.11 or later, so it is not available on this site. Webapp proctoring is unaffected.';

// NTG-185: the SDK section on the preset form.
$string['error_sdk_observeworkspace'] = 'Room Scan duration must be zero or a positive whole number of seconds.';
$string['error_sdk_supporturl'] = 'Support link address must be empty or a valid HTTP(S) URL.';
$string['error_sdk_json'] = 'Enter valid JSON supplied by Constructor Support.';
$string['error_sdk_value'] = 'Select a valid SDK configuration value.';
$string['error_invalid_sdk_configuration'] = 'The SDK configuration is invalid: {$a}';
$string['settings_sdk_identificationbullets'] = 'Identification onboarding items';
$string['settings_sdk_identificationbullets_desc'] = 'This setting customizes the instructions shown during identity verification. Contact support@constructor.tech to request the change, then copy and paste the value provided by Constructor Support. Leave it empty otherwise.';
$string['settings_sdk_translationsoverride'] = 'Translation overrides';
$string['settings_sdk_translationsoverride_desc'] = 'This setting customizes wording in the SDK proctoring interface. Contact support@constructor.tech to request the change, then copy and paste the value provided by Constructor Support. Leave it empty otherwise.';

// Moodle's addHelpButton() looks for '<key>_help', so the preset form's
// help icons need these. Same text as the _desc strings the settings page used --
// deliberately, since the two describe the same parameter and a reader comparing
// them should not have to wonder whether the difference is meaningful.
$string['settings_sdk_allowcameraselection_help'] = 'Lets the learner pick which camera records them during the pre-exam check. It only makes a difference for learners with more than one camera, such as a laptop webcam plus an external one; with a single camera, or when this is disabled, Constructor Proctor selects the camera itself.';
$string['settings_sdk_alwaysshowchat_help'] = 'Keeps the chat available in every proctoring mode, not only in Live mode. A learner who runs into a problem then always has a way to report it.';
$string['settings_sdk_beforeexamlist_help'] = 'Shows the learner the list of steps in the pre-exam check before it starts, so they know what to expect.';
$string['settings_sdk_desktopcheckfirst_help'] = 'Puts the screen-sharing check before the camera check. Useful when screen sharing is the step learners most often fail, since they find out sooner.';
$string['settings_sdk_focusrespectsrules_help'] = 'When on, switching away from the exam window is not reported as a violation if the exam rules permit the use of other applications or websites. Turn off to report every focus change regardless of the rules.';
$string['settings_sdk_fullscreenwatchfocusalert_help'] = 'Shows the learner a full-screen alert when a rule violation is detected. Turning this off hides the alerts from the learner but does not stop detection: focus changes are still recorded and still appear in the report.';
$string['settings_sdk_observeworkspace_help'] = 'How long the Room Scan screen is shown to the learner, in seconds. This is a minimum display time, not a check interval. Set 0 to skip the Room Scan screen entirely.';
$string['settings_sdk_openagreementlinksiniframe_help'] = 'Opens links in the proctoring agreement inside the exam page instead of a new browser tab. Leave off if a new tab is preferred, since a link opened in place replaces what the learner was reading.';
$string['settings_sdk_audionotification_help'] = 'Plays a sound when a chat message arrives, so the learner notices it without watching the panel.';
$string['settings_sdk_showfinishexambuttonforsessionswithoutproctor_help'] = 'Shows a Finish exam button in the proctoring panel on exams with no live proctor. Learners can always finish through the quiz itself; this adds a second way to end the proctoring session.';
$string['settings_sdk_showsyschecksupportnew_help'] = 'Shows a support link on the pre-exam check pages, so a learner whose camera or microphone fails can reach help without leaving the exam. Set the address in the setting below.';
$string['settings_sdk_syscheckhelpurl_help'] = 'The address the support link opens. Used only when the support link is switched on above; leave empty to show no link.';
$string['settings_sdk_experimentwatchwindowsize_help'] = 'Watches the size of the exam window and warns the learner if it becomes too small to monitor reliably. Marked experimental by Constructor, so test it before using it in a real exam.';
$string['settings_sdk_disabledworkers_help'] = 'This advanced setting changes which monitoring checks run during SDK exams. Contact support@constructor.tech before using it, then copy and paste only the values provided by Constructor Support. Leave it empty otherwise; an incorrect value can leave part of an exam unmonitored.';
$string['settings_sdk_identificationbullets_help'] = 'This setting customizes the instructions shown during identity verification. Contact support@constructor.tech to request the change, then copy and paste the value provided by Constructor Support. Leave it empty otherwise.';
$string['settings_sdk_translationsoverride_help'] = 'This setting customizes wording in the SDK proctoring interface. Contact support@constructor.tech to request the change, then copy and paste the value provided by Constructor Support. Leave it empty otherwise.';
