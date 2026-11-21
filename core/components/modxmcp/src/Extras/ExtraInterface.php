<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Исходный модуль MODX MCP: ExtraInterface.
 * EN: MODX MCP source module: ExtraInterface.
 */
namespace ModxMcp\Extras;

interface ExtraInterface
{
    public function key();
    public function supports($context);
    public function register($registry, $context);
}
