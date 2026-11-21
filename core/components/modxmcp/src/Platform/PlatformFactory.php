<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Совместимость платформ MODX для компонента PlatformFactory.
 * EN: MODX platform compatibility adapter: PlatformFactory.
 */
namespace ModxMcp\Platform;

class PlatformFactory
{
    public static function detect($modx)
    {
        $platforms = array(new Modx3Platform(), new Modx2Platform());
        foreach ($platforms as $platform) {
            if ($platform->supports($modx)) {
                return $platform;
            }
        }
        throw new \RuntimeException('Unsupported MODX major version.');
    }
}
