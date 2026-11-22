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

    public function execute($context, array $data)
    {
        $modx = $context->modx();
        // RU: В защищённом режиме галочка читается из БД, а не кеша PHP.
        // EN: Protected mode reads the master switch from DB, not PHP cache.
        $protected = \ModxMcp\Security\ProtectedAuthorization::required($modx);
        $masterEnabled = $protected
            ? \ModxMcp\Security\ProtectedAuthorization::processorMasterEnabled($modx)
            : (bool)$modx->getOption('modxmcp.allow_run_processor', null, false);
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
        $processor = isset($data['processor'])
            ? (string)$data['processor']
            : '';
        if ($processor === '') {
            throw new \ModxMCPClientException(
                'run_processor: "processor" path is required.'
            );
        }
        $properties = isset($data['properties'])
            && is_array($data['properties'])
            ? $data['properties']
            : array();
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
        if ($response->isError()) {
            // RU: Не раскрываем строку ошибки стороннего процессора: она
            // RU: может содержать пароли. Результат мутации неизвестен.
            // EN: Third-party error text may contain credentials, and the
            // EN: mutation may already have occurred. Require readback.
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: processor reported an error; '
                . 'verify actual state via MCP before any retry.'
            );
        }

        AuditSupport::log(
            $context,
            $this->name(),
            'system',
            array('processor' => $auditProcessor)
        );
        // RU: Возвращаем полный статус штатного процессора; MODX 3 может
        // RU: вернуть готовый массив вместо JSON-строки.
        // EN: Preserve the native processor response; MODX 3 may return an
        // EN: already decoded array instead of JSON text.
        $decoded = ProcessorSupport::decodedResponse($response);
        $result = !empty($decoded)
            ? $decoded
            : ProcessorSupport::normalize($response);
        return ProcessorSupport::sanitizeProcessorOutput($result);
    }
}
