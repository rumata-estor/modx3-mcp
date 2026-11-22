<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Ms2 Option Assign Category»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Ms2 Option Assign Category»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class Ms2OptionAssignCategoryTool implements ToolInterface
{
    public function name() { return 'ms2_assign_option_to_category'; }
    public function group() { return 'minishop2'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $payload = array(
            'option_id' => MiniShop2Support::positiveInt($data, 'option_id'),
            'category_id' => MiniShop2Support::positiveInt($data, 'category_id'),
            // Native msCategoryOption.value is NOT NULL; default is empty.
            'value' => '',
        );
        $result = MiniShop2Support::runProcessor(
            $context,
            'mgr/settings/option/assign',
            $payload
        );
        MiniShop2MutationSupport::auditProcessor(
            $context,
            $this->name(),
            $payload,
            array('option_id', 'category_id'),
            true
        );
        return $result;
    }
}
