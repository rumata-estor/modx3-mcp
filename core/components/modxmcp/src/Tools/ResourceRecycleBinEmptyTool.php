<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Resource Recycle Bin Empty»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Resource Recycle Bin Empty»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ResourceRecycleBinEmptyTool implements ToolInterface
{
    public function name() { return 'empty_recycle_bin'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $response = $context->platform()->runProcessor(
            $context->modx(),
            'resource/emptyrecyclebin',
            array()
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                $response
                    ? ProcessorSupport::error($response)
                    : 'empty_recycle_bin: no response.'
            );
        }
        ElementMutationSupport::refreshCache($context);
        AuditSupport::log($context, $this->name(), 'resource', array());
        return ProcessorSupport::normalize($response);
    }
}
