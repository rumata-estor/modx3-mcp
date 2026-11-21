<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Исходный модуль MODX MCP: PlatformInterface.
 * EN: MODX MCP source module: PlatformInterface.
 */
namespace ModxMcp\Platform;

interface PlatformInterface
{
    public function key();
    public function majorVersion();
    public function supports($modx);
    public function className($logicalName);
    public function processorTarget($processor);
    public function runProcessor($modx, $processor, array $properties = array(), array $options = array());
}
