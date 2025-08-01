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

$string['proctor:logaccess'] = 'Доступ к отчету Proctor';
$string['proctor:logaccess_course'] = 'Доступ к отчету Proctor (определенный курс)';
$string['proctor:logaccess_all'] = 'Доступ к отчету Proctor (все курсы)';

$string['description'] = 'Позволяет студентам использовать сервис "Proctor by Constructor"';
$string['pluginname'] = 'Прокторинг "Proctor by Constructor"';
$string['title'] = 'Proctor by Constructor';

$string['error_no_entry_found'] = 'No exam entry found by accesscode';
$string['error_not_in_range'] = 'Значение должно быть в диапазоне от %d до %d';
$string['error_setduration'] = 'Длительность в минутах должна быть кратна 30 (30, 60, 90)';

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
$string['settings_user_emails'] = 'Отправлять email пользователей в Proctor by Constructor';
$string['settings_user_emails_desc'] = '';
$string['settings_seamless_auth'] = 'Автоматическая авторизация пользователя';
$string['settings_seamless_auth_desc'] = 'Активация данной опции означает что прокторинг будет хранить авторизационный токен';

$string['description_proctor'] = 'Вы будете перенаправлены на Proctor by Constructor';
$string['description_no_webservices'] = 'Недоступно через мобильное приложение Moodle';

$string['settings'] = 'Настройки интеграции';
$string['log_section'] = 'Журнал Proctor';
$string['status'] = 'Статус';
$string['module'] = 'Модуль';
$string['new_entry'] = 'Новая запись';
$string['new_entry_force'] = 'Новая запись';
$string['duration'] = 'Длительность в минутах, кратная 30';
$string['log_review'] = 'Результат';
$string['log_archive_link'] = 'Архив';
$string['log_report_link'] = 'Отчет';
$string['log_attempt'] = 'Попытка';
$string['log_attempt_missing'] = 'удалена';

$string['new_entry_created'] = 'Новая запись успешно создана';
$string['entry_exist'] = 'Новая запись уже существует';
$string['date_modified'] = 'Дата последнего изменения';

$string['proctoring_mode'] = 'Режим прокторинга';
$string['online_mode'] = 'Синхронный';
$string['offline_mode'] = 'Асинхронный';
$string['auto_mode'] = 'Автоматический';
$string['identification_mode'] = 'Идентификация';

$string['identification'] = 'Режим фотографирования';
$string['face_passport_identification'] = 'Лицо и паспорт';
$string['passport_identification'] = 'Паспорт';
$string['face_identification'] = 'Лицо';
$string['skip_identification'] = 'Пропустить';

$string['web_camera_main_view'] = 'Положение основной камеры';
$string['web_camera_main_view_front'] = 'Фронтальная';
$string['web_camera_main_view_side'] = 'Боковая';

$string['calculator'] = 'Калькулятор';
$string['calculator_off'] = 'Выключен';
$string['calculator_simple'] = 'Простой';
$string['calculator_scientific'] = 'Научный';

$string['streamspreset'] = 'Настройки потоков';
$string['streamspreset_default'] = 'По умолчанию';
$string['streamspreset_no_video'] = 'Без видео';
$string['streamspreset_no_ai_detection'] = 'Без AI';
$string['streamspreset_auxcam_and_desktop'] = 'Дополнительная камера и десктоп';
$string['streamspreset_auxcam_only'] = 'Только дополнительная камера';

$string['select_groups'] = 'Использовать прокторинг только для выбраных групп';

$string['is_trial'] = 'Пробный экзамен';

$string['auxiliary_camera'] = 'Вторая камера (смартфон)';
$string['auxiliary_camera_mode'] = 'Режим второй камеры';
$string['auxiliary_camera_mode_photo'] = 'Фото';
$string['auxiliary_camera_mode_video'] = 'Видео';

$string['enable_secure_browser'] = 'Использовать защищенный браузер';
$string['secure_browser_level'] = 'Режим защищенного браузера';
$string['secure_browser_level_basic'] = 'Базовый';
$string['secure_browser_level_medium'] = 'Средний';
$string['secure_browser_level_high'] = 'Высокий';
$string['allowed_processes'] = 'Разрешенные процессы';
$string['forbidden_processes'] = 'Запрещенные процессы';
$string['processes_list_hint'] = 'По одному процессу на строку';

$string['allowmultipledisplays'] = 'Разрешить использование второго монитора';
$string['allowvirtualenvironment'] = 'Разрешить использовать виртуальные машины';
$string['checkidphotoquality'] = 'Проверять качество фото паспорта';
$string['sendmanualwarningstolearner'] = 'Уведомлять пользователя о нарушениях, найденных проктором';
$string['allowroomscanauxcamera'] = 'Разрешить сканирование комнаты второй камерой';

$string['rules'] = 'Правила';
$string['custom_rules'] = "Нестандартные правила";

$string['user_agreement_url'] = "URL пользовательского соглашения";

$string['preliminary_check'] = 'Предварительная проверка личности студента';

$string['time_scheduled'] = 'Время записи в календаре';
$string['time_finish'] = 'Время попытки';

$string['auto_rescheduling'] = 'Автоматический сброс при пропуске экзамена';
$string['enable'] = 'Включить';

$string['allow_to_use_websites'] = 'Разрешить веб-сайты';
$string['allow_to_use_books'] = 'Разрешить использование книг';
$string['allow_to_use_paper'] = 'Разрешить черновики';
$string['allow_to_use_messengers'] = 'Разрешить мессенджеры';
$string['allow_to_use_calculator'] = 'Разрешить калькулятор';
$string['allow_to_use_excel'] = 'Разрешить использование Excel';
$string['allow_to_use_human_assistant'] = 'Разрешить помощь людей';
$string['allow_absence_in_frame'] = 'Разрешить выход из комнаты';
$string['allow_voices'] = 'Разрешить голоса';
$string['allow_wrong_gaze_direction'] = 'Разрешить взгляд в сторону';

$string['scoring_params_header'] = 'Параметры расчета скоринга';
$string['scoring_cheater_level'] = 'Порог нарушителя';
$string['scoring_extra_user'] = 'Наличие еще одного человека в кадре';
$string['scoring_user_replaced'] = 'Подмена тестируемого';
$string['scoring_absent_user'] = 'Отсутствие тестируемого';
$string['scoring_look_away'] = 'Увод взгляда с экрана';
$string['scoring_active_window_changed'] = 'Смена активного окна на компьютере';
$string['scoring_forbidden_device'] = 'Запрещенное оборудование';
$string['scoring_voice'] = 'Звуки голосов в трансляции';
$string['scoring_phone'] = 'Использование телефона';

$string['status_new'] = 'Попытка не начата';
$string['status_started'] = 'Попытка начата';
$string['status_unknown'] = 'Не проверено';
$string['status_accepted'] = 'Не нарушитель';
$string['status_rejected'] = 'Нарушитель';
$string['status_force_reset'] = 'Попытка сброшена';
$string['status_finished'] = 'Завершено, ожидается статус';
$string['status_scheduled'] = 'Запланировано';

$string['scheduling_required'] = 'Обязательна запись в календаре';
$string['apply_filter'] = 'Применить фильтры';
$string['allcourses'] = 'Все курсы';
$string['allstatuses'] = 'Все статусы';
$string['userquery'] = 'Username или Email пользователя начинается с';
$string['fromdate'] = 'С:';
$string['todate'] = 'По:';

$string['score'] = 'Скоринг';
$string['threshold_attention'] = 'Порог подозрительности';
$string['threshold_rejected'] = 'Порог отклонения';
$string['session_start'] = 'Начало сессии';
$string['session_end'] = 'Окончание сессии';
$string['warnings'] = 'Нарушения';
$string['comment'] = 'Комментарий';

$string['details'] = 'Подробности';

// Fader screen.
$string['fader_awaiting_proctoring'] = 'Запуск прокторинга.';
$string['fader_instructions'] = '<p>Пожалуйста, ожидайте</p>';
$string['fader_reset'] = 'Перезагрузите страницу, чтобы продолжить тестирование';

// Dafault settings
$string['defaults'] = 'Настройки по умолчанию';
$string['defaults_proctoring_settings'] = 'Настройки прокторинга';

$string['log_details_warnings'] = 'Нарушения';
$string['log_details_warning_type'] = 'Тип';
$string['log_details_warning_title'] = 'Описание';
$string['log_details_warning_start'] = 'Начало';
$string['log_details_warning_end'] = 'Конец';

$string['visible_warnings'] = 'Видимые пользователю уведомления';

$string['warning_extra_user_in_frame'] = 'Наличие еще одного человека в кадре';
$string['warning_substitution_user'] = 'Подмена тестируемого';
$string['warning_no_user_in_frame'] = 'Отсутствие тестируемого';
$string['warning_avert_eyes'] = 'Увод взгляда с экрана';
$string['warning_timeout'] = 'Таймаут, соединение отсутствует';
$string['warning_change_active_window_on_computer'] = 'Смена активного окна на компьютере';
$string['warning_talk'] = 'Разговор во время экзамена';
$string['warning_forbidden_software'] = 'Используются запрещенные сайты/ПО';
$string['warning_forbidden_device'] = 'Используются запрещенные тех. средства';
$string['warning_voice_detected'] = 'Звуки голосов в трансляции';
$string['warning_extra_display'] = 'Используется второй монитор';
$string['warning_books'] = 'Использование книг/конспекта';
$string['warning_cheater'] = 'Нарушитель';
$string['warning_mic_muted'] = 'Микрофон отключен';
$string['warning_mic_no_sound'] = 'Нет звука';
$string['warning_mic_no_device_connected'] = 'Микрофон не подключен';
$string['warning_camera_no_picture'] = 'Нет изображения с камеры';
$string['warning_camera_no_device_connected'] = 'Камера не подключена';
$string['warning_nonverbal'] = 'Невербальное общение';
$string['warning_phone'] = 'Используется телефон';
$string['warning_phone_screen'] = 'Демонстрируется экран телефона';
$string['warning_no_ping'] = 'Приложение студента потеряло связь с сервером';
$string['warning_desktop_request_pending'] = 'Отсутствует доступ к рабочему столу';

$string['privacy:path'] = 'Записи прокторинга';
$string['privacy:metadata:availability_proctor_entries'] = 'Список записей прокторинга';
$string['privacy:metadata:availability_proctor_entries:courseid'] = 'Course ID';
$string['privacy:metadata:availability_proctor_entries:cmid'] = 'Course Module ID';
$string['privacy:metadata:availability_proctor_entries:attemptid'] = 'ID попытки';
$string['privacy:metadata:availability_proctor_entries:userid'] = 'ID пользователя, к которому относится попытка';
$string['privacy:metadata:availability_proctor_entries:accesscode'] = 'Accesscode';
$string['privacy:metadata:availability_proctor_entries:status'] = 'Статуст прокторинга';
$string['privacy:metadata:availability_proctor_entries:review_link'] = 'URL Результата';
$string['privacy:metadata:availability_proctor_entries:archiveurl'] = 'URL Архива';
$string['privacy:metadata:availability_proctor_entries:timecreated'] = 'Время создания записи';
$string['privacy:metadata:availability_proctor_entries:timemodified'] = 'Время модификации записи';
$string['privacy:metadata:availability_proctor_entries:timescheduled'] = 'Время, на которое запланирована запись';
$string['privacy:metadata:availability_proctor_entries:score'] = 'Скоринг прокторинга';
$string['privacy:metadata:availability_proctor_entries:comment'] = 'Комментарий проктора';
$string['privacy:metadata:availability_proctor_entries:threshold'] = 'Порог нарушения';
$string['privacy:metadata:availability_proctor_entries:warnings'] = 'Список предупреждений прокторинга';
$string['privacy:metadata:availability_proctor_entries:sessionstart'] = 'Время начала сессии';
$string['privacy:metadata:availability_proctor_entries:sessionend'] = 'Время окончания сессии';
