<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Вспомогательная логика «Processor» для инструментов MODX MCP.
 * EN: Shared «Processor» implementation used by MODX MCP tools.
 */
namespace ModxMcp\Tools;

class ProcessorSupport
{
    public static function error($response)
    {
        $error = $response->getMessage();
        if ($response->hasFieldErrors()) {
            foreach ($response->getFieldErrors() as $fieldError) {
                $error .= " | Field '" . $fieldError->field . "': " . $fieldError->message;
            }
        }
        return $error ? $error : 'Unknown error.';
    }

    public static function stripNoiseFields($rows)
    {
        if (!is_array($rows)) { return $rows; }
        $noise = array('password', 'cachepwd', 'salt', 'hash_class', 'remote_data', 'remote_key', 'session_stale', 'sudo');
        foreach ($rows as &$row) {
            if (is_array($row)) {
                foreach ($noise as $key) { unset($row[$key]); }
            }
        }
        unset($row);
        return $rows;
    }

    /**
     * RU: Универсальный процессор может вернуть учётные данные внутри
     * RU: вложенных объектов и списков; не отдаём их вызывающей модели.
     * EN: Arbitrary processor output may contain credentials in nested
     * EN: objects and lists; redact them before returning to the client.
     */
    public static function sanitizeProcessorOutput($value, $depth = 0)
    {
        if (!is_array($value)) { return $value; }
        if ($depth >= 16) {
            return array('redacted' => 'Maximum processor response nesting exceeded.');
        }
        foreach ($value as $key => $item) {
            $normalized = is_string($key) ? strtolower($key) : '';
            // RU: У сторонних процессоров нет единого формата ключей:
            // RU: dbPassword, smtp_password и access-token тоже секреты.
            // EN: Handle snake_case, kebab-case and camelCase secret aliases.
            $canonical = preg_replace('/[^a-z0-9]/', '', $normalized);
            $sensitive = in_array($canonical, array(
                'cachepwd', 'salt', 'hashclass', 'remotekey', 'remotedata',
                'sessionstale', 'sudo', 'cookie', 'sessionid'
            ), true) || (bool)preg_match(
                '/(?:password(?:hash|digest)?|passwd|token|secret|apikey|privatekey|credentials|authorization(?:header)?|authheader|cookie)$/D',
                $canonical
            );
            // RU: Произвольные строки сообщений и ошибок могут содержать
            // RU: временный пароль; не копируем их из чужого процессора.
            // EN: Free-form messages/errors may contain generated passwords;
            // EN: do not echo arbitrary text from third-party processors.
            if (in_array($normalized, array('message', 'error', 'errors'), true)) {
                $value[$key] = '[REDACTED]';
            } elseif ($sensitive) {
                $value[$key] = '[REDACTED]';
            } else {
                $value[$key] = self::sanitizeProcessorOutput($item, $depth + 1);
            }
        }
        return $value;
    }

    public static function unwrap($decoded)
    {
        if (is_array($decoded) && array_key_exists('object', $decoded) && array_key_exists('success', $decoded)) {
            $object = $decoded['object'];
            if (!empty($object)) { return $object; }
            return array('success' => !empty($decoded['success']));
        }
        return $decoded;
    }

    /**
     * RU: MODX 2/3 возвращает ответ процессора как JSON либо готовый массив.
     * RU: Никогда не передавайте массив непосредственно в json_decode().
     * EN: MODX 2/3 processors may return a JSON string or decoded array.
     * EN: Never pass an array directly to json_decode().
     */
    public static function decodedResponse($response)
    {
        $raw = $response->getResponse();
        if (is_array($raw)) { return $raw; }
        if (!is_string($raw)) { return array(); }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array();
    }

    public static function normalize($response)
    {
        $decoded = self::decodedResponse($response);
        if (!empty($decoded)) { return self::unwrap($decoded); }
        $object = $response->getObject();
        if (!empty($object)) { return $object; }
        return array('success' => true);
    }

    public static function run($context, $processor, array $data, $isList, array $lexicons)
    {
        $props = $data;
        unset($props['action'], $props['elementType']);
        if ($isList && !isset($props['limit'])) { $props['limit'] = 0; }
        if (!empty($lexicons)) {
            call_user_func_array(array($context->modx()->lexicon, 'load'), $lexicons);
        }

        $response = $context->platform()->runProcessor($context->modx(), $processor, $props);
        if (!$response) {
            throw new \ModxMCPClientException('Processor not found or returned nothing: ' . $processor);
        }
        if ($response->isError()) {
            throw new \ModxMCPClientException(self::error($response));
        }

        if ($isList) {
            $decoded = self::decodedResponse($response);
            return array(
                'total' => isset($decoded['total']) ? (int) $decoded['total'] : 0,
                'results' => self::stripNoiseFields(isset($decoded['results']) ? $decoded['results'] : array()),
            );
        }
        return self::normalize($response);
    }
}
