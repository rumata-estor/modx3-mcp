<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Element Get»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Element Get»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ElementGetTool implements ToolInterface
{
    public function name() { return 'get_element'; }
    public function group() { return 'elements'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $type = ElementSupport::type($data);
        $id = ElementSupport::resolveId($context, $type, $data);
        if (!$id) {
            throw new \ModxMCPClientException($type . ' not found by name or ID is missing.');
        }

        // RU: Отпечатки CAS ресурса не должны зависеть от формата дат Manager.
        // RU: Замечание @AgelxNash, PR #57; читаем исходные даты из MODX.
        // EN: Resource CAS dates must be independent of Manager formatting.
        // EN: Reported by @AgelxNash in PR #57; request raw processor dates.
        $properties = array('id' => $id);
        if ($type === 'resource') {
            $properties['skipFormatDates'] = true;
        }
        $response = $context->platform()->runProcessor(
            $context->modx(),
            ElementSupport::processorBase($type) . 'get',
            $properties
        );
        if ($response->isError()) {
            throw new \ModxMCPClientException(ElementSupport::processorError($response));
        }

        $result = $response->getObject();
        if ($type === 'tv') {
            $result['templates'] = ElementSupport::tvTemplates($context, $id);
            $result['field_type'] = $result['type'];
        }
        if ($type === 'plugin') {
            $result['events'] = ElementSupport::pluginEvents($context, $id);
        }
        return $result;
    }
}
