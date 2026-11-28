<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Запуск разрешённого MODX-процессора; главный выключатель и политика обязательны.
 * EN: Executes an approved MODX processor; master switch and policy are mandatory.
 */
namespace ModxMcp\Tools;

class RunProcessorTool implements ToolInterface
{
    public function name() { return 'run_processor'; }
    public function group() { return 'ops'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    // RU: Вызов до CAS-резервирования и повторно в execute() — одна политика.
    // EN: Shared guard before CAS and at processor execution (defense in depth).
    public static function assertDispatchAllowed($context, array $data)
    {
        $modx = $context->modx();
        // RU: В защищённом режиме галочка читается из БД, а не кеша PHP.
        // EN: Protected mode reads the master switch from DB, not PHP cache.
        $protected = \ModxMcp\Security\ProtectedAuthorization::required($modx);
        // RU: Строгая проверка галочки: непустая строка 'false' не означает Yes.
        // EN: PHP bool casts nonempty 'false' to true; require explicit Yes.
        $configuredMaster = $protected ? null
            : $modx->getOption('modxmcp.allow_run_processor', null, false);
        $masterEnabled = $protected
            ? \ModxMcp\Security\ProtectedAuthorization::processorMasterEnabled($modx)
            : ($configuredMaster === '1' || $configuredMaster === 1
                || $configuredMaster === true);
        if (!$masterEnabled) {
            throw new \ModxMCPClientException(
                'run_processor is disabled. Set modxmcp.allow_run_processor = Yes to enable it.'
            );
        }

        if (!$protected) {
            $mode = (string)$modx->getOption('modxmcp.processor_access_mode', null, 'disabled');
            // RU: Административный режим требует отдельного разрешения Ed25519.
            // RU: Галочка и API-токен сами по себе его не открывают.
            // EN: Privileged mode requires independent Ed25519 authorization.
            // EN: The checkbox and API token never grant it on their own.
            if ($mode === 'privileged') {
                throw new \ModxMCPClientException(
                    'AUTH_DENIED: privileged processor mode requires independent authorization.'
                );
            }
            \ModxMcp\Security\ProcessorAccessPolicy::authorize(
                $data,
                $mode,
                (string)$modx->getOption('modxmcp.allowed_processors', null, ''),
                (string)$modx->getOption('modxmcp.privileged_processors', null, '')
            );
        }
        return true;
    }

    public function execute($context, array $data)
    {
        self::assertDispatchAllowed($context, $data);
        $modx = $context->modx();
        $processor = isset($data['processor'])
            ? (string)$data['processor']
            : '';
        if ($processor === '') {
            throw new \ModxMCPClientException(
                'run_processor: "processor" path is required.'
            );
        }
        // RU: Некорректные свойства нельзя молча заменять пустыми:
        // RU: процессор с default-параметрами может произвести другую запись.
        // EN: Never silently drop malformed processor properties before dispatch.
        $properties = array_key_exists('properties', $data)
            ? $data['properties'] : array();
        if (!is_array($properties)) {
            throw new \ModxMCPClientException(
                'AUTH_DENIED: processor properties must be an object/map.'
            );
        }
        $options = array();
        if (!empty($data['processors_path'])) {
            $options['processors_path'] = (string)$data['processors_path'];
        }

        try {
        if (
            empty($options['processors_path'])
            && preg_match(
                '#^(context|element|resource|security|source|system|workspace)/#',
                $processor
            )
        ) {
            $response = $context->platform()->runProcessor(
                $modx,
                $processor,
                $properties
            );
            $auditProcessor = $context->platform()->processorTarget($processor);
        } else {
            $response = $modx->runProcessor(
                $processor,
                $properties,
                $options
            );
            $auditProcessor = $processor;
        }
        } catch (\Throwable $e) {
            // RU: Сторонний процессор мог частично изменить сайт прежде,
            // RU: чем выбросить исключение. Его текст может содержать секреты.
            // EN: External processor may mutate before throwing; never
            // EN: expose raw exception text, and do not allow blind retry.
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: processor execution failed; '
                . 'verify actual state via MCP before any retry.'
            );
        }

        if (!$response) {
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: processor returned no response; '
                . 'verify actual state via MCP before any retry.'
            );
        }
        try {
            if ($response->isError()) {
                // RU: Внешние эффекты могли выполниться до ошибки.
                // EN: Side effects may precede a reported processor error.
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: processor reported an error; '
                    . 'verify actual state via MCP before any retry.'
                );
            }
            // RU: Даже чтение ответа и аудит могут выбросить исключение
            // RU: после выполнения опасного процессора. Не раскрывать его текст.
            // EN: Response decoding and audit can fail AFTER side effects;
            // EN: never expose raw third-party exception text to the caller.
            $decoded = ProcessorSupport::decodedResponse($response);
            // Native isError() accepts nonempty string responses, even when
            // their JSON says success:false. Never manufacture acknowledgment
            // from an opaque response. Preserve native list envelopes.
            $hasSuccess = array_key_exists('success', $decoded);
            $acknowledged = $hasSuccess
                ? in_array($decoded['success'], array(true, 1, '1'), true)
                : isset($decoded['results'], $decoded['total'])
                    && is_array($decoded['results']) && is_numeric($decoded['total']);
            if (!$acknowledged || !empty($decoded['errors']) || !empty($decoded['error'])) {
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: processor acknowledgment absent or contradictory; inspect actual state.'
                );
            }
            $result = $decoded;
            $safeResult = ProcessorSupport::sanitizeProcessorOutput($result);
            AuditSupport::log(
                $context,
                $this->name(),
                'system',
                array('processor' => $auditProcessor)
            );
            return $safeResult;
        } catch (\Throwable $e) {
            if ($e instanceof \ModxMCPClientException
                && strpos($e->getMessage(), 'MUTATION_OUTCOME_UNCERTAIN:') === 0) {
                throw $e;
            }
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: processor post-execution '
                . 'verification failed; inspect actual state before retry.'
            );
        }
    }
}
