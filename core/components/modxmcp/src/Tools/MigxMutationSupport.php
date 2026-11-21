<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Вспомогательная логика «Migx Mutation» для инструментов MODX MCP.
 * EN: Shared «Migx Mutation» implementation used by MODX MCP tools.
 */
namespace ModxMcp\Tools;

class MigxMutationSupport
{
    public static function fields()
    {
        return array(
            'name', 'formtabs', 'contextmenus', 'actionbuttons',
            'columnbuttons', 'filters', 'extended', 'permissions',
            'fieldpermissions', 'columns', 'category', 'published',
        );
    }

    public static function refresh($context)
    {
        $manager = $context->modx()->getCacheManager();
        if ($manager) { $manager->refresh(); }
    }
}
