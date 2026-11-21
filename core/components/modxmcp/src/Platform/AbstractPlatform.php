<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Совместимость платформ MODX для компонента AbstractPlatform.
 * EN: MODX platform compatibility adapter: AbstractPlatform.
 */
namespace ModxMcp\Platform;

abstract class AbstractPlatform implements PlatformInterface
{
    protected $classes = array();

    public function className($logicalName)
    {
        if (!isset($this->classes[$logicalName])) {
            throw new \InvalidArgumentException('Unknown MODX class alias: ' . $logicalName);
        }
        return $this->classes[$logicalName];
    }

    protected function normalizeProcessor($processor)
    {
        return trim(str_replace('\\', '/', (string) $processor), '/');
    }
}
