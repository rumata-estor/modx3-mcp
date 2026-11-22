<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Маршрутизирует изменения к процессорам MODX через защищённый диспетчер.
 * EN: Routes mutations to MODX processors through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ProcessorMutationTool implements ToolInterface
{
    private $toolName;
    private $spec;

    public function __construct($toolName, array $spec)
    {
        $this->toolName = (string)$toolName;
        $this->spec = $spec;
    }

    public function name() { return $this->toolName; }
    public function group() { return $this->spec['group']; }
    public function isMutation() { return true; }
    public function supports($context)
    {
        return $context && $context->modx() && $context->platform();
    }

    public function execute($context, array $data)
    {
        $route = isset($this->spec['route']) ? $this->spec['route'] : '';
        $props = $data;
        unset($props['action'], $props['elementType']);

        if ($route === 'acl') {
            unset($props['type']);
            $this->prepareAcl($context, $props);
            $this->loadLexicons(
                $context,
                array('core:default', 'core:user', 'core:access', 'core:policy', 'core:role')
            );
        } elseif ($route === 'context') {
            // RU: Не пропускаем защищённые ключи через контекстные процессоры.
            // EN: Deny protected keys in context-setting processor mutations.
            if (in_array($this->toolName, array(
                'create_context_setting', 'update_context_setting',
                'delete_context_setting'
            ), true)) {
                foreach (array('key', 'name') as $settingField) {
                    if (isset($props[$settingField])) {
                        \ModxMcp\Security\ProtectedSettingsGuard::assertMutableKey(
                            (string)$props[$settingField]
                        );
                    }
                }
            }
            if ($this->toolName === 'update_context') {
                \ModxMcp\Security\ProtectedSettingsGuard::assertNoOpaqueContextSettings(
                    $props
                );
            }
            $this->prepareContext($props);
            $this->loadLexicons(
                $context,
                array('core:default', 'core:context', 'core:setting')
            );
        } elseif ($route === 'workspace') {
            $this->loadLexicons($context, array('core:default', 'core:workspaces'));
        }

        // RU: MODX modError хранит ошибки и сообщения между вызовами в одном
        // RU: PHP-процессе. Не позволяем старой ошибке влиять на новый ACL.
        // RU: Сбрасываем перед вызовом, но НИКОГДА не после проверки результата.
        // EN: MODX modError retains messages/errors across processor calls.
        // EN: Clear prior call state before dispatch, never after validation.
        $modx = $context->modx();
        if (isset($modx->error) && is_object($modx->error)
            && method_exists($modx->error, 'reset')) {
            $modx->error->reset();
        }
        $response = $context->platform()->runProcessor(
            $modx,
            $this->spec['processor'],
            $props
        );
        if (!$response) {
            throw new \ModxMCPClientException($this->noResponseMessage());
        }
        if ($response->isError()) {
            // RU: Некоторые версии MODX возвращают сообщение о созданном
            // RU: пользователе вместе с временным паролем и флагом ошибки.
            // RU: Не раскрываем пароль и НЕ считаем операцию безопасно отменённой.
            // EN: Some MODX user processors may return a creation message
            // EN: containing a temporary password while reporting an error.
            // EN: Never expose the password or assume the mutation was undone.
            if ($this->toolName === 'create_user') {
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: create_user returned an error status; '
                    . 'check the user via MCP before any retry.'
                );
            }
            if ($route === 'acl') {
                // RU: Общие ошибки ACL могут содержать пароль из предшествующей
                // RU: операции MODX. Не выдаём текст процессора и не обещаем откат.
                // EN: ACL processor errors may contain credentials left by a
                // EN: previous MODX action. Hide raw text; do not promise rollback.
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: ACL processor returned an error '
                    . 'status; verify state via MCP before retry.'
                );
            }
            throw new \ModxMCPClientException(ProcessorSupport::error($response));
        }

        if ($route === 'acl' && $context->modx()->getCacheManager()) {
            $context->modx()->getCacheManager()->flushPermissions();
        }

        AuditSupport::log(
            $context,
            $this->toolName,
            $route === '' ? $this->group() : $route,
            $this->auditPayload($props)
        );
        $result = ProcessorSupport::normalize($response);
        if ($this->toolName === 'create_user') {
            // RU: Никогда не отдаём объект пользователя или сообщение
            // RU: процессора целиком: там могут содержаться пароль и соль.
            // EN: Never return the raw user processor payload: it may
            // EN: contain a generated password, hash or other credentials.
            $id = is_array($result) && isset($result['id'])
                ? (int)$result['id'] : 0;
            if ($id <= 0) {
                throw new \ModxMCPClientException(
                    'MUTATION_OUTCOME_UNCERTAIN: create_user did not return '
                    . 'a confirmed user ID; verify via MCP before retry.'
                );
            }
            return array('id' => $id);
        }
        // RU: Другие ACL-процессоры (включая update_user) тоже могут
        // RU: возвращать учётные данные во вложенном объекте/сообщении.
        // EN: Other ACL processors (including update_user) can return
        // EN: credentials in nested objects or processor messages.
        if ($route === 'acl') {
            return ProcessorSupport::sanitizeProcessorOutput($result);
        }
        return $result;
    }

    private function prepareContext(array &$props)
    {
        if (in_array(
            $this->toolName,
            array('create_context_setting', 'update_context_setting'),
            true
        )) {
            if (!isset($props['fk']) && isset($props['context_key'])) {
                $props['fk'] = $props['context_key'];
            }
            if (!isset($props['namespace'])) { $props['namespace'] = 'core'; }
        }
    }

    private function prepareAcl($context, array &$props)
    {
        if ($this->toolName === 'assign_resource_to_group') {
            if (isset($props['resource']) && ctype_digit((string)$props['resource'])) {
                $props['resource'] = 'n_' . $props['resource'];
            }
            if (isset($props['resourceGroup']) && ctype_digit((string)$props['resourceGroup'])) {
                $props['resourceGroup'] = 'n_' . $props['resourceGroup'];
            }
        }

        if ($this->toolName === 'create_user') {
            if (!empty($props['password'])) {
                if (empty($props['passwordgenmethod'])) { $props['passwordgenmethod'] = 'spec'; }
                if (!isset($props['specifiedpassword'])) { $props['specifiedpassword'] = $props['password']; }
                if (!isset($props['confirmpassword'])) { $props['confirmpassword'] = $props['password']; }
                unset($props['password']);
            } elseif (empty($props['specifiedpassword'])) {
                if (empty($props['passwordgenmethod'])) { $props['passwordgenmethod'] = 'g'; }
            }
            if (empty($props['passwordnotifymethod'])) {
                $props['passwordnotifymethod'] = 's';
            }
        }

        if (in_array(
            $this->toolName,
            array(
                'grant_context_access',
                'update_context_access',
                'grant_resourcegroup_access',
                'update_resourcegroup_access',
            ),
            true
        ) && empty($props['principal_class'])) {
            $props['principal_class'] = $context->platform()->className('user_group');
        }
    }

    private function loadLexicons($context, array $lexicons)
    {
        if (!empty($lexicons)) {
            call_user_func_array(array($context->modx()->lexicon, 'load'), $lexicons);
        }
    }

    private function auditPayload(array $props)
    {
        $keys = array(
            'id', 'key', 'context_key', 'name', 'namespace', 'topic', 'language',
            'usergroup', 'user', 'target', 'principal', 'resource', 'resourceGroup'
        );
        return array_intersect_key($props, array_flip($keys));
    }

    private function noResponseMessage()
    {
        $route = isset($this->spec['route']) ? $this->spec['route'] : '';
        if ($route === 'context') {
            return 'Context processor not found or returned nothing: ' . $this->spec['processor'];
        }
        if ($route === 'acl') {
            // RU: Отсутствие ответа после запуска ACL не доказывает откат.
            // RU: Повтор без проверки состояния может дублировать выдачу прав.
            // EN: A missing ACL processor response does not prove rollback;
            // EN: blindly retrying may duplicate access grants.
            return 'MUTATION_OUTCOME_UNCERTAIN: ACL processor returned no response; '
                . 'verify state via MCP before retry.';
        }
        if ($route === 'workspace') {
            return 'Workspace processor not found: ' . $this->spec['processor'];
        }
        return 'Processor not found or returned nothing: ' . $this->spec['processor'];
    }
}
