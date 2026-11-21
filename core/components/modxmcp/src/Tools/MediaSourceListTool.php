<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Media Source List»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Media Source List»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class MediaSourceListTool implements ToolInterface
{
    public function name() { return 'list_media_sources'; }
    public function group() { return 'media'; }
    public function isMutation() { return false; }

    public function supports($context)
    {
        return $context && $context->modx() && $context->platform();
    }

    public function execute($context, array $arguments)
    {
        $modx = $context->modx();
        $class = $context->platform()->className('media_source');
        $result = array();

        foreach ($modx->getCollection($class) as $source) {
            $result[] = MediaSourceSupport::normalize($context, $source, false);
        }

        return $result;
    }
}
