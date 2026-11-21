<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Исходный модуль MODX MCP: ToolInterface.
 * EN: MODX MCP source module: ToolInterface.
 */
namespace ModxMcp\Tools;

interface ToolInterface
{
    public function name();
    public function group();
    public function isMutation();
    public function supports($context);
    public function execute($context, array $arguments);
}
