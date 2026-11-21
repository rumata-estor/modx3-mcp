<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Dependency Graph»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Dependency Graph»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class DependencyGraphTool implements ToolInterface
{
    public function name() { return 'dependency_graph'; }
    public function group() { return 'elements'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        return DependencyGraphService::build($context, $data);
    }
}
