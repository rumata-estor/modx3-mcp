<?php
namespace ModxMcp\Tools;

class SiteStateTool implements ToolInterface
{
    public function name() { return 'get_site_state'; }
    public function group() { return 'ops'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $arguments)
    {
        return StateSupport::siteState($context);
    }
}
