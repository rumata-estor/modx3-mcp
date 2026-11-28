<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Локализация интерфейса MODX MCP (ru): только текстовые строки.
 * EN: MODX MCP interface localization (ru): text strings only.
 */
$_lang['setting_modxmcp.enabled'] = 'Включить MODX MCP';
$_lang['setting_modxmcp.enabled_desc'] = 'Глобально включает или отключает API-компонент MODX MCP. Если настройка выключена, все запросы к API будут отклоняться.';

$_lang['setting_modxmcp.api_token'] = 'API-токен MODX MCP';
$_lang['setting_modxmcp.api_token_desc'] = 'Секретный токен, который передаётся в заголовке X-MCP-Token для авторизации всех запросов к MCP API.';

$_lang['setting_modxmcp.service_user_id'] = 'ID сервисного пользователя';
$_lang['setting_modxmcp.service_user_id_desc'] = 'ID sudo-пользователя MODX, от имени которого MCP выполняет административные действия. Значение 0 автоматически выбирает первого активного sudo-пользователя; положительный ID использует явно указанного активного sudo-пользователя.';

$_lang['setting_modxmcp.debug'] = 'Режим отладки MCP';
$_lang['setting_modxmcp.debug_desc'] = 'Если включено, API будет возвращать подробности внутренних ошибок в ответе. В продакшене рекомендуется держать выключенным.';

$_lang['setting_modxmcp.audit_log'] = 'Аудит-лог MCP';
$_lang['setting_modxmcp.audit_log_desc'] = 'Если включено, операции create/update/delete и обновления TV будут записываться в лог MODX для аудита и диагностики.';

$_lang['setting_modxmcp.max_payload_bytes'] = 'Максимальный размер payload';
$_lang['setting_modxmcp.max_payload_bytes_desc'] = 'Максимально допустимый размер JSON-запроса к API в байтах. Защищает компонент от слишком больших или ошибочных запросов.';

$_lang['setting_modxmcp.require_https'] = 'Требовать HTTPS';
$_lang['setting_modxmcp.require_https_desc'] = 'Отклоняет MCP API-запросы без HTTPS. Для новых установок включено по умолчанию.';
$_lang['setting_modxmcp.trust_proxy_https'] = 'Доверять HTTPS-заголовку reverse proxy';
$_lang['setting_modxmcp.trust_proxy_https_desc'] = 'Если включено, X-Forwarded-Proto: https считается подтверждением HTTPS. Включайте только за доверенным reverse proxy, который сам формирует этот заголовок.';
$_lang['setting_modxmcp.allowed_ips'] = 'Разрешённые IP MCP';
$_lang['setting_modxmcp.allowed_ips_desc'] = 'Необязательный список IP/CIDR через запятую. Пустое значение разрешает любой IP при наличии корректного токена; проверяется реальный REMOTE_ADDR, X-Forwarded-For не используется.';

$_lang['setting_modxmcp.allow_native_tv_rendering'] = 'Разрешить штатный рендеринг TV';
$_lang['setting_modxmcp.allow_native_tv_rendering_desc'] = 'По умолчанию выключено. Рендеринг может выполнять привязки, плагины и сторонний PHP с правами служебного пользователя. Только для доверенных сайтов в обычном режиме; в защищённом режиме недоступно.';
$_lang['setting_modxmcp.allow_root_filesystem_read'] = 'Разрешить чтение корневого Filesystem';
$_lang['setting_modxmcp.allow_root_filesystem_read_desc'] = 'Если включено, MCP сможет просматривать и читать файлы через корневой media source Filesystem. Лучше держать выключенной, если нужно только безопасное изучение кода компонентов.';

$_lang['setting_modxmcp.component_code_roots'] = 'Корни кода компонентов';
$_lang['setting_modxmcp.component_code_roots_desc'] = 'Список каталогов через запятую, которые MCP может сканировать для изучения кода компонентов, например core/components,assets/components.';

$_lang['setting_modxmcp.core_path'] = 'Путь к ядру';
$_lang['setting_modxmcp.core_path_desc'] = 'Путь в файловой системе к директории ядра компонента modxMCP. По умолчанию {core_path}components/modxmcp/.';

$_lang['area_modxmcp:main'] = 'modxMCP: Основное';
$_lang['area_modxmcp:limits'] = 'modxMCP: Лимиты';
$_lang['area_modxmcp:security'] = 'modxMCP: Безопасность';
$_lang['area_modxmcp:paths'] = 'modxMCP: Пути';

$_lang['setting_modxmcp.auto_static'] = 'Авто-статика элементов';
$_lang['setting_modxmcp.auto_static_desc'] = 'Если включено, создание/обновление чанка/сниппета/шаблона/плагина через MCP автоматически переводит его в статический файл в core/elements/ (источник — Filesystem). Дальше правьте эти файлы напрямую.';


$_lang['setting_modxmcp.allow_run_processor'] = 'Разрешить run_processor';
$_lang['setting_modxmcp.allow_run_processor_desc'] = 'Главный выключатель run_processor. Дополнительно нужны режим доступа и точный список процессоров. В защищённом контуре обязательны CAS, одноразовое подписанное разрешение и политика сайта.';

$_lang['setting_modxmcp.processor_access_mode'] = 'Режим доступа к процессорам';
$_lang['setting_modxmcp.processor_access_mode_desc'] = 'disabled (по умолчанию), allowlist или privileged. Ни один режим не разрешает произвольные процессоры; одной галочки недостаточно.';
$_lang['setting_modxmcp.allowed_processors'] = 'Разрешённые процессоры';
$_lang['setting_modxmcp.allowed_processors_desc'] = 'Список точных имён процессоров через запятую, например system/clearcache. Маски, обход путей и пользовательские каталоги запрещены. Процессоры безопасности, пакетов и пространств имён требуют режима privileged и отдельного подписанного разрешения.';
$_lang['setting_modxmcp.privileged_processors'] = 'Административные процессоры';
$_lang['setting_modxmcp.privileged_processors_desc'] = 'Дополнительный список точных имён, доступный только в режиме privileged. Не отменяет CAS, авторизацию и ограничения защищённых настроек.';
$_lang['setting_modxmcp.trusted_proxy_ips'] = 'Доверенные IP HTTPS-прокси';
$_lang['setting_modxmcp.trusted_proxy_ips_desc'] = 'Точные адреса IPv4/IPv6 через запятую. Только эти соединения могут передавать X-Forwarded-Proto при включённом доверии к прокси.';
