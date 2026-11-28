<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Общая точка входа MODX 2/3; только модульная обработка.
 * EN: Shared MODX 2/3 entry point; modular dispatch only.
 */
namespace ModxMcp\Core;
use \ModxMCPClientException;
use ModxMcp\Registry\CapabilityService;

trait Connector
{
    public $modx;
    public $config = array();
    private $modularRuntime = null;

    private function initializeConnector(&$modx, array $config =[]) {
        $this->modx =& $modx;
        $corePath = $this->modx->getOption('modxmcp.core_path', $config, $this->modx->getOption('core_path') . 'components/modxmcp/');
        $corePath = str_replace(
            array('{core_path}', '[[++core_path]]'),
            rtrim((string) $this->modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR,
            (string) $corePath
        );
        $this->config = array_merge(['corePath' => $corePath], $config);

        // Staged modular core. In CAS-required 1.2.8, processRequest()
        // fails closed if modular initialization fails; no legacy bypass.
        $autoload = rtrim($corePath, '/\\') . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Autoloader.php';
        if (is_file($autoload)) {
            try {
                require_once $autoload;
                \ModxMcp\Autoloader::register(rtrim($corePath, '/\\') . DIRECTORY_SEPARATOR . 'src');
                $this->modularRuntime = new \ModxMcp\Core\Runtime($this->modx, $this);
            } catch (\Exception $e) {
                $this->modularRuntime = null;
            } catch (\Throwable $e) {
                $this->modularRuntime = null;
            }
        }
    }

    private function resolveServiceUser() {
        $platform = self::VARIANT === 'modx2'
            ? new \ModxMcp\Platform\Modx2Platform()
            : new \ModxMcp\Platform\Modx3Platform();
        $userClass = $platform->className('user');
        $configuredId = (int)$this->modx->getOption('modxmcp.service_user_id', null, 0);

        if ($configuredId > 0) {
            $user = $this->modx->getObject($userClass, ['id' => $configuredId]);
            if (!$user) {
                throw new ModxMCPClientException("Service user not found: {$configuredId}.");
            }
            if (!$user->get('active')) {
                throw new ModxMCPClientException("Service user is inactive: {$configuredId}.");
            }
            if (!$user->get('sudo')) {
                throw new ModxMCPClientException("Service user is not sudo: {$configuredId}.");
            }
            return $user;
        }

        $query = $this->modx->newQuery($userClass);
        $query->where(['active' => 1, 'sudo' => 1]);
        $query->sortby('id', 'ASC');
        $query->limit(1);
        $user = $this->modx->getObject($userClass, $query);

        if (!$user) {
            throw new ModxMCPClientException(
                'No active sudo MODX user found. Set modxmcp.service_user_id to an active manager user ID.'
            );
        }

        return $user;
    }

    public function processRequest($action, $elementType, $data =[], $externalAuthorization = null) {
        $serviceUser = $this->resolveServiceUser();
        $this->modx->user = $serviceUser;

        $this->assertCapabilityEnabled($action);

        // Mandatory CAS requires the central modular dispatcher. In 1.2.8
        // there is NO silent fallback to the legacy mutation processor:
        // such a fallback would bypass the lock and strict CAS check.
        if ($this->modularRuntime === null) {
            throw new ModxMCPClientException(
                'CAS_RUNTIME_REQUIRED: modular MODX MCP runtime is unavailable.'
            );
        }
        $registeredTool = $this->modularRuntime->registry()->get($action);
        if ($registeredTool === null
            || !$registeredTool->supports($this->modularRuntime->context())) {
            throw new ModxMCPClientException(
                'CAS_RUNTIME_REQUIRED: action is not available in the guarded tool registry: '
                . (string)$action
            );
        }

        // The modular registry is now authoritative for every request.
        // Unregistered routes are rejected; no legacy execution path exists.
        if ($this->modularRuntime !== null) {
            $tool = $this->modularRuntime->registry()->get($action);
            if ($tool !== null && $tool->supports($this->modularRuntime->context())) {
                $toolData = is_array($data) ? $data : array();
                $elementBridgeActions = array(
                    'list_elements',
                    'get_element',
                    'create_element',
                    'update_element',
                    'update_element_property',
                    'delete_element',
                );
                if (in_array($action, $elementBridgeActions, true) && !isset($toolData['type']) && $elementType !== '') {
                    $toolData['type'] = $elementType;
                }
                $runtimePreconditions = null;
                if (
                    isset($toolData['_runtime_preconditions'])
                    && is_array($toolData['_runtime_preconditions'])
                ) {
                    $runtimePreconditions = $toolData['_runtime_preconditions'];
                    unset($toolData['_runtime_preconditions']);
                }
                // A global CAS revision is checked by the dispatcher under
                // the site lock BEFORE revision reservation. Never pass that
                // guard again to resource/element tools: they would recheck
                // it AFTER the reserved revision increased and incorrectly
                // throw STALE_STATE. The TV tool alone needs the separate
                // expected_tvs map for raw-value comparisons BEFORE commit.
                if ($action === 'update_resource_tvs'
                    && is_array($runtimePreconditions)) {
                    $toolData['_runtime_preconditions'] = $runtimePreconditions;
                }
                if ($tool->isMutation()) {
                    // RU: Отдельный подписанный ID нужен только добровольному журналу.
                    // EN: Opt-in signed ID is never passed to MODX processors.
                    $operationId = array_key_exists('_operation_id', $toolData)
                        ? $toolData['_operation_id'] : null;
                    unset($toolData['_operation_id']);
                    $runtimePreconditions = \ModxMcp\Tools\StateSupport::requireMutationPreconditions(
                        $action, $runtimePreconditions
                    );
                    $runtimeContext = $this->modularRuntime->context();
                    $originalRequestData = is_array($data) ? $data : array();
                    $dryRunActions = array(
                        'delete_element',
                        'delete_category',
                        'bulk_resources',
                        'replace_across',
                    );
                    $isDryRun = (
                        !empty($toolData['dry_run'])
                        && in_array($action, $dryRunActions, true)
                    );
                    return \ModxMcp\Tools\StateSupport::withMutationLock(
                        $runtimeContext,
                        function () use (
                            $tool,
                            $runtimeContext,
                            $toolData,
                            $runtimePreconditions,
                            $isDryRun,
                            $action,
                            $elementType,
                            $originalRequestData,
                            $externalAuthorization,
                            $operationId
                        ) {
                            // Independently signed authorization is enforced
                            // INSIDE the common site mutation lock for every
                            // mutating tool on both MODX 2 and 3. It is not
                            // possible to bypass this gate via direct PHP
                            // processRequest() or a legacy dispatcher route.
                            try {
                                $protected = \ModxMcp\Security\ProtectedAuthorization::required(
                                    $runtimeContext->modx()
                                );
                                if ($protected) {
                                    \ModxMcp\Tools\StateSupport::requireProtectedTargetReadSet(
                                        $action,
                                        $toolData,
                                        $runtimePreconditions
                                    );
                                    \ModxMcp\Security\ProtectedAuthorization::authorizeMutation(
                                        $runtimeContext->modx(),
                                        $action,
                                        $elementType,
                                        $originalRequestData,
                                        $externalAuthorization
                                    );
                                }
                            } catch (\Throwable $e) {
                                throw new \ModxMCPClientException(
                                    'AUTH_DENIED: independent authorization refused.'
                                );
                            }
                            // The TV rows and their site revision share one
                            // database commit; before this CAS update they
                            // were committed separately, allowing a false
                            // success on revision persistence failure.
                            // RU: Запрещённые запросы отклоняются ДО CAS-резерва.
                            // EN: Deterministic security denials precede CAS reservation.
                            \ModxMcp\Tools\MutationPreflight::assertAllowed(
                                $runtimeContext, $action, $toolData,
                                $runtimePreconditions
                            );
                            // Exact stale-CAS semantics take priority over
                            // deterministic static-file conflicts. Optional
                            // signed replay IDs must still reach the journal
                            // so a duplicate request is reported as replay.
                            if ($action === 'make_static' && $operationId === null
                                && !(isset($toolData['items'])
                                    && is_array($toolData['items']))) {
                                \ModxMcp\Tools\StateSupport::assertPreconditions(
                                    $runtimeContext, $runtimePreconditions
                                );
                                \ModxMcp\Tools\ElementMutationSupport::preflightMakeStatic(
                                    $runtimeContext,
                                    isset($toolData['type']) ? (string)$toolData['type'] : '',
                                    isset($toolData['id']) ? (int)$toolData['id'] : 0
                                );
                            }
                            $atomicTv = $tool instanceof
                                \ModxMcp\Tools\ResourceTvUpdateTool;
                            $atomicDatabase = \ModxMcp\Tools\StateSupport::supportsAtomicRevision(
                                $runtimeContext, $tool->name(), $toolData
                            );
                            $apply = function ($managed) use (
                                $tool,
                                $runtimeContext,
                                $toolData,
                                $runtimePreconditions,
                                $isDryRun,
                                $atomicTv
                            ) {
                                if (is_array($runtimePreconditions)) {
                                    \ModxMcp\Tools\StateSupport::assertPreconditions(
                                        $runtimeContext,
                                        $runtimePreconditions
                                    );
                                }
                                $result = ($managed && $atomicTv)
                                    ? $tool->executeWithinRevisionTransaction(
                                        $runtimeContext,
                                        $toolData
                                    )
                                    : $tool->execute($runtimeContext, $toolData);
                                $revision = $isDryRun
                                    ? \ModxMcp\Tools\StateSupport::revision($runtimeContext)
                                    : \ModxMcp\Tools\StateSupport::incrementRevision(
                                        $runtimeContext
                                    );
                                if (is_array($result)) {
                                    $result['_site_revision'] = $revision;
                                }
                                return $result;
                            };
                            // Journal gate runs AFTER independent Ed25519 authorization
                            // but BEFORE CAS reservation and every side effect.
                            $executeMutation = function () use (
                                $atomicDatabase, $isDryRun, $runtimeContext,
                                $action, $toolData, $atomicTv, $tool,
                                $runtimePreconditions, $apply
                            ) {
                            // RU: Нет optional addon -> без внешних эффектов и CAS-сдвига.
                            // EN: Missing addon must fail before reserving site revision.
                            \ModxMcp\Tools\StateSupport::requireOptionalAddonPath(
                                $runtimeContext, $action
                            );
                            if ($atomicDatabase && !$isDryRun) {
                                $result = \ModxMcp\Tools\StateSupport::withRevisionTransaction(
                                    $runtimeContext,
                                    function () use ($apply, $runtimeContext, $action, $toolData) {
                                        if (in_array($action, array(
                                                'update_element','delete_element'
                                            ), true)
                                            && isset($toolData['type'])
                                            && $toolData['type']==='resource'
                                            && isset($toolData['id'])) {
                                            \ModxMcp\Tools\StateSupport::lockResourceRowForCas(
                                                $runtimeContext,
                                                $toolData['id']
                                            );
                                        }
                                        // One DB lock boundary for every group/membership mutation.
                                        // Missing/unsafe group selection fails before native processor.
                                        if (in_array($action, array(
                                            'update_user_group', 'delete_user_group',
                                            'add_user_to_group', 'update_group_member',
                                            'remove_user_from_group'
                                        ), true)) {
                                            $groupId = in_array($action, array(
                                                'update_user_group', 'delete_user_group'
                                            ), true)
                                                ? (isset($toolData['id']) ? $toolData['id'] : null)
                                                : (isset($toolData['usergroup']) ? $toolData['usergroup'] : null);
                                            \ModxMcp\Tools\StateSupport::lockGroupMembershipForCas(
                                                $runtimeContext, $groupId
                                            );
                                        }
                                        return $apply(true);
                                    }
                                );
                                if ($atomicTv) {
                                    // DB commit succeeded; a post-commit failure
                                    // cannot be presented as an unapplied mutation.
                                    \ModxMcp\Security\MutationOutcomeGuard::run(
                                        function () use ($tool, $runtimeContext, $toolData) {
                                            return $tool->afterCommittedRevision(
                                                $runtimeContext,
                                                $toolData
                                            );
                                        }
                                    );
                                }
                                return $result;
                            }
                            if (!$isDryRun) {
                                // An external side effect (file, cache,
                                // package, plugin, custom processor) is not
                                // rollback-able through the database.
                                // Reserve the revision BEFORE starting it:
                                // a failed revision write cannot leave an
                                // untracked filesystem or external mutation.
                                if (is_array($runtimePreconditions)) {
                                    \ModxMcp\Tools\StateSupport::assertPreconditions(
                                        $runtimeContext,
                                        $runtimePreconditions
                                    );
                                }
                                $reservedRevision = \ModxMcp\Tools\StateSupport::incrementRevision(
                                    $runtimeContext
                                );
                                // Revision was already reserved. A native processor may
                                // have written before throwing; never report not_applied.
                                $result = \ModxMcp\Security\MutationOutcomeGuard::run(
                                    function () use ($tool, $runtimeContext, $toolData) {
                                        return $tool->execute($runtimeContext, $toolData);
                                    }
                                );
                                if (is_array($result)) {
                                    $result['_site_revision'] = $reservedRevision;
                                }
                                // On a later tool failure the conservative
                                // revision increment remains: it is a
                                // conflict marker, not proof of success.
                                return $result;
                            }
                            return $apply(false);
                            };
                            return \ModxMcp\Security\MutationOperationGate::dispatch(
                                $runtimeContext->modx(), $action, $elementType,
                                $originalRequestData, $operationId, $protected,
                                $executeMutation
                            );
                        }
                    );
                }
                return $tool->execute(
                    $this->modularRuntime->context(),
                    $toolData
                );
            }
        }

        // RU: При любом изменении доступности инструмента отказываем без fallback.
        // EN: No legacy fallback even if tool availability changes during dispatch.
        throw new ModxMCPClientException(
            'CAS_RUNTIME_REQUIRED: action is not available in the guarded tool registry: '
            . (string)$action
        );
    }

    public function buildDependencyGraph(array $data = array()) {
        // Manager keeps its own authenticated MODX user. Do not route through
        // processRequest(), which selects the MCP service account.
        // Both Manager and MCP use the same guarded read-only graph builder.
        if ($this->modularRuntime === null) {
            throw new ModxMCPClientException(
                'CAS_RUNTIME_REQUIRED: modular MODX MCP runtime is unavailable.'
            );
        }
        $context = $this->modularRuntime->context();
        $tool = $this->modularRuntime->registry()->get('dependency_graph');
        if ($tool === null || $tool->isMutation() || !$tool->supports($context)) {
            throw new ModxMCPClientException(
                'CAS_RUNTIME_REQUIRED: dependency graph is unavailable.'
            );
        }
        return $tool->execute($context, $data);
    }

    public function getIntegrationsReport() {
        if ($this->modularRuntime === null) {
            throw new ModxMCPClientException('CAS_RUNTIME_REQUIRED: modular MODX MCP runtime is unavailable.');
        }
        $context = $this->modularRuntime->context();
        return $context->extras()->report($context);
    }

    public function regenerateToken() {
        // RU: Только административная точка входа Manager; MCP API использует CAS.
        // EN: Manager-only entry point; the MCP API uses the guarded CAS dispatcher.
        if (!$this->modx->user || !$this->modx->user->isAuthenticated('mgr')
            || !$this->modx->hasPermission('settings')) {
            throw new ModxMCPClientException('AUTH_DENIED: Manager settings permission is required.');
        }
        if ($this->modularRuntime === null) {
            throw new ModxMCPClientException(
                'CAS_RUNTIME_REQUIRED: modular MODX MCP runtime is unavailable.'
            );
        }
        $context = $this->modularRuntime->context();
        $tool = $this->modularRuntime->registry()->get('regenerate_token');
        if ($tool === null || !$tool->isMutation() || !$tool->supports($context)) {
            throw new ModxMCPClientException(
                'CAS_RUNTIME_REQUIRED: token rotation tool is unavailable.'
            );
        }
        return $tool->execute($context, array());
    }

    public function getSupportedActions() {
        return CapabilityService::groups();
    }

    private function assertCapabilityEnabled($action) {
        CapabilityService::assertEnabled($this->modx, $action);
    }

    public function getSiteState() {
        return CapabilityService::state($this->modx, $this->modularRuntime !== null);
    }

    public function getCapabilities() {
        return CapabilityService::report($this->modx, $this->modularRuntime !== null);
    }

    public function capabilitiesFingerprint() {
        return CapabilityService::fingerprint($this->modx);
    }
}
