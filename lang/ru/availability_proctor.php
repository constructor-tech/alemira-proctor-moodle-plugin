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

// Brand-specific product name comes from the brand class (not translatable —
// same string in every locale). The base lang file stays brand-neutral so it
// doesn't need rewriting per build.
require_once(__DIR__ . '/../../classes/brand.php');
$pluginnamebrand = \availability_proctor\brand::DISPLAY_NAME;

$string['proctor:logaccess'] = 'Доступ к отчету Proctor';
$string['proctor:logaccess_course'] = 'Доступ к отчету Proctor (определенный курс)';
$string['proctor:logaccess_all'] = 'Доступ к отчету Proctor (все курсы)';

$string['description'] = 'Позволяет студентам использовать сервис ' . $pluginnamebrand;
$string['pluginname'] = $pluginnamebrand;
$string['title'] = $pluginnamebrand;

$string['error_no_entry_found'] = 'No exam entry found by accesscode';
$string['error_not_in_range'] = 'Значение должно быть в диапазоне от %d до %d';
$string['error_setduration'] = 'Длительность в минутах должна быть кратна 30 (30, 60, 90)';

$string['settings_proctor_url'] = $pluginnamebrand . ' URL';
$string['settings_proctor_url_desc'] = '';
$string['settings_integration_name'] = 'Integration Name';
$string['settings_integration_name_desc'] = '';
$string['settings_jwt_secret'] = 'JWT Secret';
$string['settings_jwt_secret_desc'] = '';
$string['settings_account_name'] = 'Account Name';
$string['settings_account_name_desc'] = '';
$string['settings_account_id'] = 'Account ID';
$string['settings_account_id_desc'] = '';
$string['settings_user_emails'] = 'Отправлять email пользователей в ' . $pluginnamebrand;
$string['settings_user_emails_desc'] = 'Если включено, Moodle будет отправлять email-адреса экзаменуемых в панель Proctor. ' .
    'Если выключено, ' . $pluginnamebrand . ' автоматически сгенерирует фиктивный email ' .
    'для каждого экзаменуемого в качестве уникального идентификатора в панели.';
$string['settings_seamless_auth'] = 'Автоматическая авторизация пользователя';
$string['settings_seamless_auth_desc'] = 'Передаёт в Proctor одноразовый краткосрочный токен аутентификации Moodle, чтобы учащиеся входили в систему автоматически при возврате. ' .
    'Рекомендуется при использовании Secure Browser. ' .
    'Примечание по безопасности: токены действительны в течение 8 часов и привязаны к конкретной записи экзамена. ' .
    'На общих экзаменационных устройствах учащимся следует выходить из системы после завершения экзамена.';

$string['description_proctor'] = 'Вы будете перенаправлены на ' . $pluginnamebrand;
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
$string['online_mode'] = 'Прямой';
$string['offline_mode'] = 'Обзор';
$string['auto_mode'] = 'Автоматический';
$string['identification_mode'] = 'Идентификация';

$string['identification'] = 'Режим подтверждения личности';
$string['face_passport_identification'] = 'Лицо и ID';
$string['passport_identification'] = 'Только ID';
$string['face_identification'] = 'Только лицо';
$string['skip_identification'] = 'Пропустить';

$string['web_camera_main_view'] = 'Положение основной камеры';
$string['web_camera_main_view_front'] = 'Фронтальная';
$string['web_camera_main_view_side'] = 'Дополнительная (боковой вид)';

$string['calculator'] = 'Калькулятор';
$string['calculator_off'] = 'Выключен';
$string['calculator_simple'] = 'Простой';
$string['calculator_scientific'] = 'Научный';

$string['streamspreset'] = 'Режим записи';
$string['streamspreset_default'] = 'По умолчанию';
$string['streamspreset_no_video'] = 'Без видео';
$string['streamspreset_no_ai_detection'] = 'Без AI';
$string['streamspreset_no_webcam'] = 'Без веб-камеры';
$string['streamspreset_auxcam_and_desktop'] = 'Дополнительная камера и десктоп';
$string['streamspreset_auxcam_only'] = 'Только дополнительная камера';

$string['select_groups'] = 'Использовать прокторинг только для выбраных групп';

$string['is_trial'] = 'Пробный экзамен';

$string['auxiliary_camera'] = 'Вторая камера (смартфон)';
 

$string['enable_secure_browser'] = 'Использовать защищенный браузер';
$string['secure_browser_level'] = 'Режим защищенного браузера';
$string['secure_browser_level_basic'] = 'Базовый';
$string['secure_browser_level_medium'] = 'Средний';
$string['secure_browser_level_high'] = 'Высокий';
$string['allowtouseadditionalresources'] = 'Разрешить использование дополнительных ресурсов (PDF/веб)';
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
$string['proctor_emails'] = "Назначить прокторов";
$string['proctor_emails_placeholder'] = "proctor1@example.com\nproctor2@example.com";

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
$string['scoring_mic_muted'] = 'Микрофон отключён';
$string['scoring_mic_no_device'] = 'Микрофон не подключён';
$string['scoring_mic_no_sound'] = 'Тишина в микрофоне';
$string['scoring_camera_no_device'] = 'Камера не подключена';
$string['scoring_camera_no_picture'] = 'Нет изображения с камеры';
$string['scoring_no_aux_camera_photo'] = 'Нет фото со вспомогательной камеры';
$string['scoring_no_ping'] = 'Приложение тестируемого потеряло связь с сервером';
$string['scoring_desktop_request_pending'] = 'Ожидание запроса рабочего стола';
$string['scoring_account_collision'] = 'Использование нескольких аккаунтов в течение одного экзамена';

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

// ---- Presets (admin and exam-form additions) ----
$string['proctor:managepresets'] = 'Управление пресетами прокторинга';
$string['presets'] = 'Глобальные пресеты прокторинга';
$string['preset_create'] = 'Создать пресет';
$string['preset_edit'] = 'Редактировать пресет';
$string['preset_name'] = 'Название пресета';
$string['preset_is_default'] = 'По умолчанию';
$string['preset_is_system'] = 'Системный';
$string['preset_duplicate'] = 'Дублировать';
$string['preset_set_default'] = 'Установить по умолчанию';
$string['preset_default_changed'] = 'Пресет по умолчанию изменён.';
$string['preset_deleted'] = 'Пресет удалён.';
$string['preset_saved'] = 'Пресет сохранён.';
$string['preset_delete_confirm'] = 'Удалить этот пресет?';
$string['preset_settings_summary'] = 'Настройки';
$string['preset_section_identity_meta'] = 'Пресет';
$string['preset_section_mode'] = 'Режим прокторинга';
$string['preset_section_identity'] = 'Подтверждение личности';
$string['preset_section_camera'] = 'Камера и наблюдение';
$string['preset_section_securebrowser'] = 'Secure Browser';
$string['preset_section_resources'] = 'Среда экзамена';
$string['preset_section_rules'] = 'Разрешено во время экзамена';
$string['preset_section_warnings'] = 'Уведомления учащемуся';
$string['preset_section_scoring'] = 'Параметры скоринга';
$string['preset_section_exam'] = 'Настройки экзамена';

$string['load_preset'] = '(изменить)';
$string['save_personal_preset'] = 'Сохранить как личный пресет';
$string['save_personal_preset_prompt'] = 'Введите имя для личного пресета.';
$string['save_personal_preset_hint'] = 'Личные пресеты сохраняются в вашей учётной записи ' .
    'и могут использоваться в любых ваших экзаменах. Они не видны другим пользователям. ' .
    'Глобальные пресеты управляются администраторами сайта в Site administration > Plugins.';
$string['global_presets'] = 'Глобальные пресеты';
$string['personal_presets'] = 'Личные пресеты';
$string['no_presets'] = 'Нет пресетов';
$string['loaded_preset'] = 'Текущий пресет: {$a}';
$string['loaded_preset_none'] = 'Нет';

$string['auxiliary_camera_off'] = 'Выкл';
$string['auxiliary_camera_on'] = 'Вкл';
$string['delete'] = 'Удалить';

$string['error_preset_cannot_delete'] = 'Этот пресет нельзя удалить ' .
    '(он системный, последний оставшийся или используется экзаменом).';
$string['error_preset_not_found'] = 'Пресет не найден.';
$string['error_invalid_payload'] = 'Неверные данные пресета.';
$string['error_invalid_action'] = 'Неверное действие.';
$string['error_internal'] = 'Произошла внутренняя ошибка. Повторите попытку или обратитесь к администратору.';
$string['error_preset_name_taken'] = 'Пресет с таким названием уже существует.';
$string['error_preset_user_quota'] = 'Достигнут лимит личных пресетов ({$a}). Удалите ненужный, чтобы сохранить новый.';
$string['error_preset_default_missing'] = 'Не найден пресет прокторинга по умолчанию, а стартовый набор не удалось создать автоматически. Обратитесь к администратору.';
$string['preset_display_high_stakes'] = 'Экзамен с высокой ставкой';
$string['preset_display_low_stakes'] = 'Экзамен с низкой ставкой';
$string['preset_display_open_book'] = 'Экзамен с открытыми материалами';
$string['redirecting_to_proctor'] = 'Перенаправление в {$a}';
$string['proctor_go_to_system'] = 'Перейти в систему прокторинга';
$string['secure_browser_enabled'] = 'Включён';
$string['secure_browser_disabled'] = 'Выключен';
$string['selectauser'] = 'Выберите пользователя';
$string['error_preset_name_required'] = 'Название пресета обязательно.';
$string['error_useragreementurl'] = 'Введите корректный URL, начинающийся с http:// или https:// ' .
    '(например: https://example.com/terms).';

// ---- Field hints (admin help buttons + exam-form popovers) ----
$string['proctoring_mode_help'] = 'Live — Прямая трансляция, где экзамен проводится ' .
    'под наблюдением проктора в реальном времени (в дополнение к ИИ).<br>' .
    'Review — Постэкзаменационная проверка: учащийся сдаёт экзамен в любое время под контролем ИИ. ' .
    'Сессия записывается и затем проверяется проктором на нарушения.<br>' .
    'AI review — Полностью автоматизированный режим без участия проктора. ' .
    'Сессия отслеживается ИИ; нарушения отмечаются по настроенным порогам.';
$string['sendmanualwarningstolearner_help'] = 'Позволяет проктору отправлять учащемуся ручные предупреждения ' .
    'во время экзамена дополнительно к автоматическим уведомлениям ИИ.';
$string['identification_help'] = 'Способ проверки личности учащегося перед началом экзамена.';
$string['checkidphotoquality_help'] = 'Проверяет читаемость фото документа и предлагает переснять его при низком качестве.';
$string['preliminary_check_help'] = 'Сравнивает фото, сделанное перед экзаменом, с фотографией в профиле Moodle.';
$string['web_camera_main_view_help'] = 'Front view (по умолчанию) — встроенная веб-камера.
Auxiliary (side view) — для специализированных установок с боковой камерой; используются другие ИИ-модели.';
$string['auxiliary_camera_help'] = 'Дополнительная камера, доступная по QR-коду через смартфон учащегося. ' .
    'Рекомендуется для экзаменов с высокой ставкой — снимает клавиатуру и занимает телефон.';
$string['allowroomscanauxcamera_help'] = 'Позволяет учащемуся выполнить 360-градусное сканирование комнаты дополнительной камерой.';
$string['allowmultipledisplays_help'] = 'Если выключено — учащиеся с несколькими подключенными мониторами ' .
    'не смогут продолжить (проверяется при настройке демонстрации экрана).';
$string['streamspreset_help'] = 'Default — стандартная видеозапись (рекомендуется для онлайн-экзаменов).
<br>No Video — отключает запись видео. Обычно используется для экзаменов на территории учебного заведения ' .
    'с защищённым браузером, где видео не требуется.';
$string['enable_secure_browser_help'] = 'Требует от учащихся установить ' . $pluginnamebrand . ' Secure Browser — ' .
    'отдельное приложение, обеспечивающее ограничения на уровне ОС.';
$string['secure_browser_level_help'] = 'Basic: обнаружение виртуальных машин и USB-устройств.
<br>Medium: добавляет ограничения, сохраняя гибкость (рекомендуется при разрешении пользовательских процессов).
<br>High: режим во весь экран всегда сверху, блокирует фоновые приложения.';
$string['allowtouseadditionalresources_help'] = 'Разрешает учащемуся открывать дополнительные ' .
    'PDF-файлы или веб-ресурсы во время экзамена.';
$string['allowed_processes_help'] = 'Имена процессов (по одному в строке), разрешённые в Secure Browser.';
$string['forbidden_processes_help'] = 'Дополнительные имена процессов (по одному в строке) для блокировки в Secure Browser.';
$string['allowvirtualenvironment_help'] = 'Разрешает сдавать экзамен из виртуальной машины. ' .
    'Обнаружение требует Secure Browser. Обычно для экзаменов по информатике.';
$string['calculator_help'] = 'Встраивает в интерфейс экзамена калькулятор для учащегося.';
$string['user_agreement_url_help'] = 'Необязательная ссылка, отображаемая в начале процесса перед экзаменом, ' .
    'чтобы учащиеся могли ознакомиться с условиями.';
$string['is_trial_help'] = 'Запускает экзамен в демо-режиме — видео не загружается на дашборд проктора. ' .
    'Для тестирования оборудования и хода без записей.';
$string['custom_rules_help'] = 'Дополнительные правила, специфичные для вашей организации, ' .
    'отображаемые учащимся на странице правил вместе с настроенными правилами.';
$string['proctor_emails_help'] = 'Укажите в этом поле электронные адреса прокторов. ' .
    'Если ничего не указано, будет автоматически назначен любой доступный проктор.';
$string['select_groups_help'] = 'Применять прокторинг только к выбранным группам курса. ' .
    'Учащиеся из других групп смогут сдавать экзамен без прокторинга.';

// Per-rule hints.
$string['allow_to_use_websites_help'] = 'Разрешить просмотр Интернета. Также отключает уведомление «Смена активного окна».';
$string['allow_to_use_books_help'] = 'Разрешить пользоваться печатными книгами или справочными материалами.';
$string['allow_to_use_paper_help'] = 'Разрешить вести записи на бумаге во время экзамена.';
$string['allow_to_use_messengers_help'] = 'Разрешить использование мессенджеров.';
$string['allow_to_use_excel_help'] = 'Разрешить использование Microsoft Excel.';
$string['allow_to_use_human_assistant_help'] = 'Разрешить присутствие другого человека рядом с учащимся.';
$string['allow_absence_in_frame_help'] = 'Разрешить кратковременно покидать зону видимости камеры. ' .
    'Отключает уведомление «Отсутствие учащегося в кадре».';
$string['allow_voices_help'] = 'Разрешить разговаривать во время экзамена. Отключает уведомление «Звуки голосов».';
$string['allow_wrong_gaze_direction_help'] = 'Полезно для экзаменов с открытыми материалами. Отключает уведомление «Увод взгляда».';

// Shared title + hint for sections of uniform items.
// `warnings` already exists above as 'Нарушения' for the log report — keep it
// for that context. Use a separate key for the alert-visibility help title:
$string['warnings_help'] = 'Всплывающее окно, показываемое учащемуся при обнаружении этого нарушения. ' .
    'Обнаружение происходит независимо от этой настройки — меняется только видимость для учащегося.';
$string['scoring'] = 'Вес скоринга';
$string['scoring_help'] = 'Вес, применяемый к этому типу нарушений при расчёте балла.';
$string['scoring_section_hint'] = 'Вес, применяемый к этому типу нарушений при расчёте балла. ' .
    'Оставьте пустым, чтобы использовать значение по умолчанию.';
$string['scoring_cheater_level_help'] = 'Общий порог (0–100). Сессии с более высоким баллом ' .
    'автоматически помечаются как нарушители. По умолчанию 80.';

// Per-warning hints — only for warnings that can be auto-disabled by an "Allow" rule.
$string['warning_change_active_window_on_computer_help'] = 'Срабатывает при переключении окон во время экзамена. ' .
    'Автоматически отключается при разрешении «Просмотр Интернета».';
$string['warning_voice_detected_help'] = 'Срабатывает при обнаружении голоса во время экзамена. ' .
    'Автоматически отключается при разрешении «Разговоры».';
$string['warning_avert_eyes_help'] = 'Срабатывает при длительном уводе взгляда от экрана. ' .
    'Автоматически отключается при разрешении «Длительный увод взгляда от экрана».';
$string['warning_no_user_in_frame_help'] = 'Срабатывает, когда учащийся не виден в кадре. ' .
    'Автоматически отключается при разрешении «Выход из кадра».';
$string['warning_extra_user_in_frame_help'] = 'Срабатывает, когда в кадре виден ещё один человек помимо учащегося.';
$string['warning_substitution_user_help'] = 'Срабатывает, когда человек в кадре не совпадает с прошедшим идентификацию учащимся.';
$string['warning_forbidden_device_help'] = 'Срабатывает при обнаружении в кадре внешнего устройства (телефон, планшет, второй монитор).';
$string['warning_phone_help'] = 'Срабатывает, когда в кадре виден телефон.';
