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

$string['pluginname'] = 'Constructor Proctor';
$string['description'] = 'Allows students to use Constructor Proctor';
$string['title'] = 'Constructor Proctor';

$string['error_no_entry_found'] = 'No exam entry found by accesscode';
$string['error_not_in_range'] = 'This value should be between %d and %d';
$string['error_setduration'] = 'Duration must be a multiple of 30';
$string['error_preset_cannot_delete'] = 'This preset cannot be deleted (it is the system preset, the last remaining preset, or is in use by an exam).';
$string['error_preset_not_found'] = 'Preset not found.';
$string['preset_default_name'] = 'Default';
$string['preset_seed_high_stakes'] = 'High-stakes exam';
$string['preset_seed_low_stakes'] = 'Low-stakes exam';
$string['preset_seed_open_book'] = 'Open-book exam';
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
$string['proctoring_mode_help'] = 'Live — Live proctoring mode in which the exam is monitored by a human proctor in real-time (in addition to AI monitoring).
Review — Post-exam review mode in which examinees take the exam at any time under AI supervision. The session is recorded and later reviewed by a human proctor to assess for violations.
AI review — Fully automated mode without human review. The session is monitored by AI only; violations are flagged based on the configured scoring thresholds.';
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
No Video — disables video recording. It\'s typically used for on-campus exam rooms where the Secure Browser ensures learners cannot leave the exam page, making video recording unnecessary in a controlled physical environment.';
$string['enable_secure_browser_help'] = 'Requires learners to install the Constructor Secure Browser, a dedicated application that enforces machine-level restrictions during the exam.';
$string['secure_browser_level_help'] = 'Basic: detects virtual machines and USB devices.
Medium: adds restrictions while allowing limited flexibility (recommended when allowing custom processes).
High: full-screen always-on-top mode, blocks background applications.';
$string['allowtouseadditionalresources_help'] = 'Allows the learner to open additional PDF or web resources during the exam.';
$string['allowed_processes_help'] = 'Process names (one per line) that are allowed to run under Secure Browser.';
$string['forbidden_processes_help'] = 'Extra process names (one per line) to block under Secure Browser.';
$string['allowvirtualenvironment_help'] = 'Allows taking the exam from within a virtual machine. Detection requires Secure Browser. Typically used for computer-science exams.';
$string['calculator_help'] = 'Embeds an in-exam calculator widget available to the learner.';
$string['user_agreement_url_help'] = 'Optional URL displayed at the start of the pre-exam process so learners can review your exam terms.';
$string['is_trial_help'] = 'Runs this exam in demo mode — no video is uploaded to the proctoring dashboard. For testing equipment and flow without generating recordings.';
$string['custom_rules_help'] = 'Additional rules specific to your organization, shown to learners on the rules page in addition to the preset allow/forbid rules.';
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
$string['warnings'] = 'Alert shown to student';
$string['warnings_help'] = 'Pop-up shown to the learner in-exam when this violation is detected. Detection still happens regardless of this setting — only learner visibility changes.';
$string['scoring'] = 'Scoring weight';
$string['scoring_help'] = 'Weight applied to this violation type when computing the learner\'s cheating score. Leave blank to use the default.';
// Per-warning hints — only for warnings that can be auto-disabled by an "Allow" rule.
$string['warning_change_active_window_on_computer_help'] = 'Detected when the learner switches windows during the exam. Automatically disabled when "Browsing the Internet" is allowed.';
$string['warning_voice_detected_help'] = 'Detected when voice is heard during the exam. Automatically disabled when "Talking" is allowed.';
$string['warning_avert_eyes_help'] = 'Detected when the learner looks away from the screen for a prolonged time. Automatically disabled when "Prolonged looking away from screen" is allowed.';
$string['warning_no_user_in_frame_help'] = 'Detected when no user is visible in the webcam. Automatically disabled when "Leaving webcam frame" is allowed.';

$string['scoring_cheater_level'] = 'Cheater score threshold';
$string['scoring_cheater_level_help'] = 'Overall threshold (0–100). Sessions scoring above this value are auto-flagged as cheaters. Default 80.';
$string['load_preset'] = '(change)';
$string['save_personal_preset'] = 'Save as personal preset';
$string['save_personal_preset_prompt'] = 'Enter a name for your personal preset.';
$string['save_personal_preset_hint'] = 'Personal presets are saved to your account and can be reused in any of your exams. They are not visible to other users. Global presets are managed by site administrators in Site administration > Plugins.';
$string['loaded_preset'] = 'Current preset: {$a}';
$string['loaded_preset_none'] = 'None';
$string['global_presets'] = 'Global presets';
$string['personal_presets'] = 'Personal presets';
$string['no_presets'] = 'No presets';
$string['delete'] = 'Delete';
$string['error_preset_name_required'] = 'Preset name is required.';
$string['error_useragreementurl'] = 'Please enter a valid URL starting with http:// or https:// (for example: https://example.com/terms).';
$string['error_invalid_payload'] = 'Invalid preset payload.';
$string['error_invalid_action'] = 'Invalid action.';

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
$string['settings_user_emails_desc'] = '';
$string['settings_seamless_auth'] = 'Seemless authorization';
$string['settings_seamless_auth_desc'] = 'Proctoring will store authorization token for users';

$string['description_proctor'] = 'You will be redirected to {$a}';
$string['description_no_webservices'] = 'Can not be accessed via Moodle mobile app';

$string['settings'] = 'Integration settings';
$string['log_section'] = '{$a} log';
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
$string['streamspreset_no_ai_detection'] = 'No AI detection';
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
$string['fader_awaiting_proctoring'] = 'Proctoring is launching.';
$string['fader_instructions'] = '<p>Please wait and do not close the page</p>';
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
$string['privacy:metadata:availability_proctor_entries:timescheduled'] = '';
$string['privacy:metadata:availability_proctor_entries:score'] = 'Proctoring score';
$string['privacy:metadata:availability_proctor_entries:comment'] = 'Proctor\'s comment';
$string['privacy:metadata:availability_proctor_entries:threshold'] = 'Score threshold';
$string['privacy:metadata:availability_proctor_entries:warnings'] = 'List of warnings received during proctoring session';
$string['privacy:metadata:availability_proctor_entries:sessionstart'] = 'Time when proctoring session started';
$string['privacy:metadata:availability_proctor_entries:sessionend'] = 'Time when proctoring session ended';
