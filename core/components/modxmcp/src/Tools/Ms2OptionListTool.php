<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Ms2 Option List»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Ms2 Option List»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class Ms2OptionListTool implements ToolInterface
{
    public function name() { return 'ms2_list_options'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $payload = array();
        foreach (array('query', 'category', 'modcategory', 'limit', 'start') as $field) {
            if (array_key_exists($field, $data)) { $payload[$field] = $data[$field]; }
        }
        if (empty($payload['limit'])) { $payload['limit'] = 100; }
        return MiniShop2Support::runProcessor($context, 'mgr/settings/option/getlist', $payload);
    }
}
