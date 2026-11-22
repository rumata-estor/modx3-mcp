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

        // RU: Даты ресурса должны оставаться сырыми значениями БД: процессор
        // RU: resource/get без skipFormatDates переформатирует pub_date,
        // RU: unpub_date и publishedon через manager_date_format +
        // RU: manager_time_format и timezone PHP, из-за чего CAS-отпечаток
        // RU: (StateSupport::fingerprintReadItem → elementState) зависит от
        // RU: настроек Manager и времени чтения, а не от состояния цели.
        // EN: Resource dates must stay raw DB values: resource/get without
        // EN: skipFormatDates reformats pub_date, unpub_date and publishedon
        // EN: via manager_date_format + manager_time_format and the PHP
        // EN: timezone, so the CAS fingerprint
        // EN: (StateSupport::fingerprintReadItem → elementState) depends on
        // EN: Manager settings and read time instead of the target state.
        $response = $context->platform()->runProcessor(
            $context->modx(),
            ElementSupport::processorBase($type) . 'get',
            array('id' => $id, 'skipFormatDates' => true)
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
