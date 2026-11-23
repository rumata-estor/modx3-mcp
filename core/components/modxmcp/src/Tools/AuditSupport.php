<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Вспомогательная логика «Audit» для инструментов MODX MCP.
 * EN: Shared «Audit» implementation used by MODX MCP tools.
 */
namespace ModxMcp\Tools;

class AuditSupport
{
    // RU: Журналируем только безопасные метаданные, не содержание объектов.
    // EN: Whitelist structural metadata instead of persisting arbitrary payload text.
    public static function safeLabel($label)
    {
        return is_string($label) && preg_match('/\A[a-z][a-z0-9_]{0,79}\z/D', $label)
            ? $label : '[OMITTED]';
    }

    public static function safePayload(array $payload, $depth = 0)
    {
        if ($depth >= 4) return array();
        $result = array();
        $ids = array('id', 'resource_id', 'tv_id', 'source', 'source_id',
            'parent', 'parent_id', 'user_id', 'group_id', 'role_id',
            'order_id', 'category_id', 'product_id', 'count', 'total', 'revision');
        $states = array('success', 'failed', 'error', 'expected', 'pending',
            'denied', 'completed', 'not_applied', 'rolled_back', 'partial',
            'unknown', 'cancelled', 'skipped', 'created', 'updated', 'deleted');
        foreach ($payload as $key => $value) {
            if (count($result) >= 32) break;
            if (!is_string($key)
                || !preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $key)) {
                continue;
            }
            if (\ModxMcp\Security\SecretRedactor::isSecretKey($key)) {
                $result[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                // RU: TV-имена, списки файлов и другие массивы не пишем.
                // EN: TV names, file lists and other free-form arrays are omitted.
                $result[$key] = $key === 'metadata'
                    ? self::safePayload($value, $depth + 1) : '[OMITTED]';
            } elseif (in_array($key, $ids, true)
                && (is_int($value) || (is_string($value)
                    && preg_match('/\A[0-9]{1,18}\z/D', $value)))) {
                $result[$key] = (int)$value;
            } elseif (in_array($key, array('state', 'status', 'outcome'), true)
                && is_string($value) && in_array($value, $states, true)) {
                $result[$key] = $value;
            } else {
                // RU: Скрываем произвольный текст без анализа его содержимого.
                // EN: Text is withheld even when it lacks recognizable secret markers.
                $result[$key] = '[OMITTED]';
            }
        }
        return $result;
    }

    public static function safeEntry(array $entry)
    {
        $ts = isset($entry['ts']) && is_string($entry['ts'])
            && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9:]+[+-][0-9:]+\z/D',
                $entry['ts']) ? $entry['ts'] : null;
        $safe = array(
            'ts' => $ts,
            'action' => self::safeLabel(isset($entry['action']) ? $entry['action'] : null),
            'type' => self::safeLabel(isset($entry['type']) ? $entry['type'] : null),
            'user' => isset($entry['user']) && is_numeric($entry['user'])
                ? (int)$entry['user'] : null,
            'payload' => self::safePayload(isset($entry['payload'])
                && is_array($entry['payload']) ? $entry['payload'] : array()),
        );
        // RU: Сохраняем старый числовой id, но не произвольный текстовый.
        // EN: Preserve numeric legacy entry IDs without exposing free-form values.
        if (isset($entry['id']) && (is_int($entry['id'])
            || (is_string($entry['id']) && preg_match('/\\A[0-9]{1,18}\\z/D', $entry['id'])))) {
            $safe['id'] = (int)$entry['id'];
        }
        return $safe;
    }

    public static function log($context, $action, $type, array $payload = array())
    {
        $payload = self::safePayload($payload);
        $action = self::safeLabel($action);
        $type = self::safeLabel($type);
        if (StateSupport::inRevisionTransaction($context)) {
            StateSupport::afterRevisionCommit($context, function () use (
                $context, $action, $type, $payload
            ) {
                self::log($context, $action, $type, $payload);
            });
            return;
        }
        $modx = $context->modx();
        if (!(bool)$modx->getOption('modxmcp.audit_log', null, true)) { return; }

        $userId = $modx->user ? (int)$modx->user->get('id') : null;
        $modx->log(
            defined('modX::LOG_LEVEL_INFO') ? \modX::LOG_LEVEL_INFO : 1,
            sprintf(
                '[modxmcp] action=%s type=%s service_user=%s payload=%s',
                $action,
                $type,
                $userId === null ? 'unknown' : $userId,
                json_encode($payload, JSON_UNESCAPED_UNICODE)
            )
        );

        // RU: Приватный журнал нельзя оставлять world-readable на общем хостинге.
        // EN: Secure audit file and directory permissions before appending.
        $previousUmask = umask(0077);
        try {
            $dir = rtrim($modx->getOption('core_path'), '/') . '/components/modxmcp/logs';
            if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
            if (!is_dir($dir) || is_link($dir) || !@chmod($dir, 0700)) return;
            $file = $dir . '/audit.log';
            if (is_link($file)) return;
            if (!is_file($file)) {
                // RU: Новый файл создаётся эксклюзивно с umask 077.
                // EN: Exclusive creation under umask 077, no open-write race.
                $created = @fopen($file, 'xb');
                if (is_resource($created)) fclose($created);
            }
            if (!is_file($file) || is_link($file) || !@chmod($file, 0600)) return;
            $entry = array(
                'ts' => date('c'),
                'action' => $action,
                'type' => $type,
                'user' => $userId,
                'payload' => $payload,
            );
            @file_put_contents(
                $file,
                json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n",
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable $e) {
            // RU: Журналирование не должно превращать мутацию в новую ошибку.
            // EN: An audit failure must never rewrite the mutation outcome.
        } finally {
            umask($previousUmask);
        }
    }
}
