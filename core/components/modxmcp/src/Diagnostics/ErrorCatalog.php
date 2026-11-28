<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Безопасные, понятные человеку ошибки и идентификаторы диагностики.
 * EN: Safe user-facing errors and diagnostic identifiers for all HTTP clients.
 */
namespace ModxMcp\Diagnostics;

final class ErrorCatalog
{
    // Never interpolate exception text, SQL, paths, token or client payloads here.
    // RU: Только фиксированные безопасные объяснения; технические подробности — в логах.
    private static function entries()
    {
        return array(
            'AUTH_DENIED' => array('Операция отклонена проверкой доступа.',
                'Не удалось подтвердить разрешение на запрошенное действие.',
                'Попросите администратора проверить права и разрешение операции. Не пытайтесь обходить отказ.',
                'not_applied', 'after_authorization'),
            'MANUAL_REVIEW_REQUIRED' => array(
                'Операция остановлена: необходимо ручное согласование.',
                'Нельзя надёжно проверить, не изменился ли объект вне MODX MCP.',
                'Откройте объект в MODX Manager, сравните состояние и согласуйте изменение. Не повторяйте запись автоматически.',
                'not_applied', 'after_review'),
            'CAS_REQUIRED' => array('Для изменения данных требуется проверка актуального состояния.',
                'Запрос не содержит обязательных CAS-предусловий.',
                'Заново прочитайте состояние сайта и нужного объекта, затем сформируйте запрос с актуальными CAS-предусловиями.',
                'not_applied', 'after_refresh'),
            'STALE_STATE' => array('Данные изменились после предыдущего чтения.',
                'Сохранённый снимок больше не совпадает с текущим состоянием.',
                'Прочитайте объект заново, сравните изменения и согласуйте новый вариант; не перезаписывайте чужие изменения автоматически.',
                'not_applied', 'after_review'),
            'MUTATION_OUTCOME_UNCERTAIN' => array('Результат изменения данных не подтверждён.',
                'Ошибка возникла в процессе операции или после возможного побочного эффекта.',
                'Не повторяйте запись. Сначала безопасно прочитайте затронутые объекты и проверьте, что фактически сохранилось.',
                'unknown', 'verify_before_retry'),
            'SECURE_STATIC_IO_UNAVAILABLE' => array('Безопасная запись в файл недоступна.',
                'Файловая система или права не позволяют использовать требуемый безопасный режим.',
                'Попросите администратора проверить режим static I/O, права файла и каталога. Не отключайте защиту ради повтора.',
                'unknown', 'after_admin_check'),
            'STATIC_READ_FAILED' => array('Не удалось прочитать статический файл.',
                'Сервер не смог безопасно прочитать содержимое файла.',
                'Проверьте существование файла и доступ к нему через администратора сайта.',
                'not_applied', 'after_admin_check'),
            'ACL_INDEX_REQUIRED' => array('Изменение прав доступа остановлено.',
                'Не подготовлен индекс, необходимый для безопасной блокировки ACL.',
                'Администратору нужно проверить миграцию ACL-индекса на копии базы и выполнить согласованную процедуру обслуживания.',
                'not_applied', 'after_admin_check'),
            'PROJECT_BUSY' => array('Сайт уже обрабатывает другую операцию.',
                'Защитная блокировка не позволяет параллельно изменять проект.',
                'Дождитесь завершения текущей операции и затем получите актуальное состояние сайта.',
                'not_applied', 'after_refresh'),
            'BACKUP_FAILED' => array('Не удалось создать защитную копию перед изменением.',
                'Обязательная резервная копия не была подготовлена.',
                'Проверьте доступность каталога резервных копий и права записи. Изменение следует выполнять только после успешной подготовки копии.',
                'not_applied', 'after_admin_check'),
            'HTTPS_REQUIRED' => array('Запрос отклонён: требуется HTTPS.',
                'Сайт не подтвердил защищённое соединение.',
                'Проверьте HTTPS-адрес сайта и настройку доверенного обратного прокси у администратора.',
                'not_applied', 'after_admin_check'),
            'METHOD_NOT_ALLOWED' => array('Этот способ обращения к API не поддерживается.',
                'HTTP-метод не соответствует контракту MODX MCP.',
                'Проверьте настройку клиента и используйте предусмотренный MCP-инструмент.',
                'not_applied', 'after_client_fix'),
            'MCP_DISABLED' => array('MODX MCP отключён на сайте.',
                'Администратор отключил выполнение операций компонента.',
                'Обратитесь к администратору сайта. Не меняйте настройки безопасности без разрешения.',
                'not_applied', 'after_admin_check'),
            'IP_NOT_ALLOWED' => array('Подключение с этого адреса запрещено.',
                'Подключение не прошло проверку разрешённых IP-адресов.',
                'Попросите администратора проверить список разрешённых адресов.',
                'not_applied', 'after_admin_check'),
            'UNAUTHORIZED' => array('Не удалось авторизовать подключение.',
                'Сайт отклонил данные доступа.',
                'Проверьте конфигурацию подключения и действительность токена у администратора. Не отправляйте токен в переписку.',
                'not_applied', 'after_admin_check'),
            'PAYLOAD_TOO_LARGE' => array('Запрос слишком большой.',
                'Размер запроса превышает установленный предел.',
                'Уменьшите объём передаваемых данных или согласуйте изменение лимита с администратором.',
                'not_applied', 'after_client_fix'),
            'MEDIA_TPL_WRITE_REFUSED' => array('Источник файлов отклонил запись файла .tpl.',
                'Возможная причина — allowedFileTypes источника или системная настройка upload_files; причина не установлена.',
                'Проверьте фактическое состояние файла. Попросите администратора проверить разрешённые расширения в Media Source и upload_files. Не отключайте защиту ради повтора.',
                'unknown', 'verify_before_retry'),
            'VALIDATION_ERROR' => array('Некорректные параметры операции.',
                'Проверка структуры переданных данных не пройдена до записи.',
                'Проверьте схему инструмента и исправьте поля. Не повторяйте тот же запрос.',
                'not_applied', 'after_client_fix'),
            'INVALID_REQUEST' => array('Некорректный запрос к MODX MCP.',
                'Сервер не смог разобрать обязательные параметры запроса.',
                'Проверьте формат JSON и параметры операции. Передайте код ошибки разработчику, если проблема повторяется.',
                'not_applied', 'after_client_fix'),
            'INTERNAL_ERROR' => array('На сервере произошла внутренняя ошибка.',
                'MODX MCP не смог завершить обработку запроса. Технические сведения скрыты.',
                'Передайте администратору идентификатор ошибки. Если выполнялась запись, сначала проверьте состояние объекта и не повторяйте её вслепую.',
                'unknown', 'verify_before_retry'),
            'OPERATION_REFUSED' => array('MODX MCP не смог выполнить запрошенную операцию.',
                'Операция остановилась; точную причину требуется уточнить по идентификатору диагностики.',
                'Передайте идентификатор ошибки разработчику. Если это было изменение данных, проверьте состояние объекта перед повтором.',
                'unknown', 'verify_before_retry'),
        );
    }

    public static function codeFromLegacy($message)
    {
        $message = (string)$message;
        if (preg_match('/\A([A-Z][A-Z0-9_]{2,63}):/', $message, $match)) {
            $code = $match[1];
            $entries = self::entries();
            if (isset($entries[$code])) return $code;
            if ($code === 'ACL_MEMBERSHIP_INDEX_REQUIRED') return 'ACL_INDEX_REQUIRED';
            if ($code === 'STALE_REVISION' || $code === 'CAS_CONFLICT') return 'STALE_STATE';
        }
        return 'OPERATION_REFUSED';
    }

    public static function newId()
    {
        return 'modxmcp_' . bin2hex(random_bytes(12));
    }

    public static function fields($legacy, $code = null, $errorId = null)
    {
        $entries = self::entries();
        $code = is_string($code) && isset($entries[$code])
            ? $code : self::codeFromLegacy($legacy);
        $entry = $entries[$code];
        return array(
            'code' => $code,
            'error_id' => $errorId === null ? self::newId() : $errorId,
            'message_ru' => $entry[0],
            'reason_ru' => $entry[1],
            'next_action_ru' => $entry[2],
            'outcome' => $entry[3],
            'retry_policy' => $entry[4],
        );
    }

    // RU: Ошибка журналирования не должна обрывать ответ об исходной ошибке.
    // EN: Logging failure must never mask the original incident or close transport.
    public static function safeLog($modx, $level, $line)
    {
        try {
            if (is_object($modx) && method_exists($modx, 'log')) {
                $modx->log($level, $line);
            }
        } catch (\Throwable $ignored) {
            // The response still carries its incident ID and safe explanation.
        }
    }

    // RU: HTTP-статусы и старое поле error не меняем; анонимным GET диагностику не выдаём.
    // EN: Preserve legacy HTTP error/status while adding safe optional fields.
    public static function send($modx, $httpStatus, $legacy, $code)
    {
        $fields = self::fields($legacy, $code);
        $modxClass = get_class($modx);
        $levelKey = $modxClass . '::LOG_LEVEL_ERROR';
        $level = defined($levelKey) ? constant($levelKey) : 3;
        self::safeLog($modx, $level,
            sprintf('[%s] MCP endpoint refused. code=%s http_status=%d',
                $fields['error_id'], $fields['code'], (int)$httpStatus));
        http_response_code((int)$httpStatus);
        echo json_encode(array_merge(array(
            'success' => false,
            'error' => \ModxMcp\Security\SecretRedactor::text((string)$legacy),
        ), $fields), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
