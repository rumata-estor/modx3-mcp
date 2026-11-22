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
            // RU: MODX User/Create и User/Update принимают скрытые поля:
            // RU: groups меняет членство/роли, class_key подменяет класс
            // RU: процессора, sudo даёт superuser, а newpassword меняет
            // RU: пароль. Это НЕ полномочия обычного create/update_user.
            // EN: User create/update accept hidden processor properties:
            // EN: groups mutates role memberships, class_key selects a
            // EN: custom processor, sudo elevates privileges, and newpassword
            // EN: changes credentials. Keep those behind dedicated workflows.
            if (in_array($this->toolName, array('create_user', 'update_user'), true)) {
                $deniedFields = array(
                    'sudo', 'is_sudo', 'groups', 'primary_group', 'primarygroup',
                    'class_key', 'classkey', 'remotedata', 'remote_data',
                );
                if ($this->toolName === 'update_user') {
                    $deniedFields = array_merge($deniedFields, array(
                        'password', 'newpassword', 'specifiedpassword',
                        'confirmpassword', 'passwordgenmethod',
                        'passwordnotifymethod',
                    ));
                }
                foreach (array_keys($props) as $field) {
                    if (!is_string($field)) { continue; }
                    if (in_array(strtolower(trim($field)), $deniedFields, true)) {
                        throw new \ModxMCPClientException(
                            'AUTH_DENIED: privileged user field requires a dedicated admin workflow.'
                        );
                    }
                }
            }
            if ($this->toolName === 'create_resource_group') {
                // RU: MODX автоматически создаёт ACL/группы при указании
                // RU: access_admin/access_anon/access_parallel и смежных полей.
                // RU: Такие побочные полномочия требуют отдельных MCP-действий.
                // EN: Core create_resource_group can auto-grant policies and
                // EN: create user groups via hidden access_* properties.
                // EN: Require separately authorized ACL actions instead.
                $hiddenGrantFields = array(
                    'automatic_access', 'access_admin', 'access_anon',
                    'access_parallel', 'access_contexts', 'access_usergroups',
                );
                foreach (array_keys($props) as $field) {
                    if (is_string($field)
                        && in_array(strtolower(trim($field)), $hiddenGrantFields, true)) {
                        throw new \ModxMCPClientException(
                            'AUTH_DENIED: resource-group auto-access requires dedicated ACL actions.'
                        );
                    }
                }
            }
            if ($this->toolName === 'update_user') {
                $this->preserveUserFlags($context, $props);
            }
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
            if (in_array($this->toolName, array('create_context', 'update_context'), true)) {
                \ModxMcp\Security\ProtectedSettingsGuard::assertNoOpaqueContextSettings(
                    $props
                );
            }
            if ($this->toolName === 'create_context') {
                // RU: MODX context/create по умолчанию создаёт ACL для
                // RU: анонимных пользователей (Load Only). MCP не должен
                // RU: выдавать этот доступ без отдельного grant_context_access.
                // EN: Core context/create defaults to anonymous Load Only
                // EN: access. Use explicit independent ACL grant instead.
                foreach (array_keys($props) as $field) {
                    if (!is_string($field)
                        || strtolower(trim($field)) !== 'enableanonymous') { continue; }
                    if ($field !== 'enableAnonymous'
                        || !in_array($props[$field], array(false, 0, '0'), true)) {
                        throw new \ModxMCPClientException(
                            'AUTH_DENIED: anonymous context access requires explicit ACL grant.'
                        );
                    }
                }
                $props['enableAnonymous'] = false;
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

    /**
     * RU: security/user/update вызывает setCheckbox(active/blocked/sudo).
     * RU: Пропущенное поле там превращается в false — значит, простое
     * RU: изменение ФИО могло отключить пользователя или снять sudo.
     * EN: Core user/update defaults omitted active/blocked/sudo checkboxes
     * EN: to false. Preserve persisted flags during profile-only changes.
     */
    private function preserveUserFlags($context, array &$props)
    {
        $id = isset($props['id']) ? (int)$props['id'] : 0;
        if ($id <= 0) {
            throw new \ModxMCPClientException('update_user: valid id is required.');
        }
        $user = $context->modx()->getObject(
            $context->platform()->className('user'), $id
        );
        if (!$user) {
            throw new \ModxMCPClientException('update_user: user not found.');
        }
        $profile = $user->getOne('Profile');
        // RU: Штатная валидация MODX требует username/email даже когда
        // RU: редактируется только fullname. Берём их из текущей записи.
        // EN: Core user/update validates username and email on every call,
        // EN: including profile-only updates. Supply their existing values.
        $username = $user->get('username');
        $email = $profile ? $profile->get('email') : null;
        if (!array_key_exists('username', $props)) {
            if (!is_string($username) || $username === '') {
                throw new \ModxMCPClientException(
                    'AUTH_DENIED: cannot safely load existing username.'
                );
            }
            $props['username'] = $username;
        }
        if (!array_key_exists('email', $props)) {
            if (!is_string($email) || $email === '') {
                throw new \ModxMCPClientException(
                    'AUTH_DENIED: cannot safely load existing user email.'
                );
            }
            $props['email'] = $email;
        }
        $active = $user->get('active');
        $sudo = $user->get('sudo');
        if ($active === null || $sudo === null) {
            throw new \ModxMCPClientException(
                'AUTH_DENIED: user flags unavailable; refusing unsafe partial update.'
            );
        }
        if (!array_key_exists('active', $props)) {
            $props['active'] = (int)(bool)$active;
        }
        // RU: sudo не разрешено менять через MCP, но штатный процессор
        // RU: требует значение, иначе обнулит флаг даже при смене ФИО.
        // EN: User input may not change sudo, but core processor needs the
        // EN: persisted value or it would clear it on a profile update.
        $props['sudo'] = (int)(bool)$sudo;
        if (!array_key_exists('blocked', $props)) {
            $blocked = $profile ? $profile->get('blocked') : 0;
            if ($blocked === null) {
                throw new \ModxMCPClientException(
                    'AUTH_DENIED: user profile flags unavailable.'
                );
            }
            $props['blocked'] = (int)(bool)$blocked;
        }
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
