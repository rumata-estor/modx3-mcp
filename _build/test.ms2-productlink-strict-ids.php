<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка границы входных аргументов miniShop2 product links, без внешней БД.
 * EN: Reject unsafe miniShop2 product-link IDs before any addon processor call.
 */
class ModxMCPClientException extends Exception {}

$base = dirname(__DIR__) . '/core/components/modxmcp/src/Tools/';
require_once $base . 'ToolInterface.php';
require_once $base . 'MiniShop2Support.php';
require_once $base . 'Ms2ProductLinkCreateTool.php';
require_once $base . 'Ms2ProductLinkDeleteTool.php';

class BlockAddonContext
{
    public function modx()
    {
        throw new RuntimeException('Addon/DB touched before strict validation');
    }
}
function ms2GuardAssert($ok, $label)
{
    if (!$ok) {
        throw new RuntimeException('MS2_PRODUCTLINK_VALIDATION_FAILED ' . $label);
    }
}
$checks = 0;
$tools = array(
    new \ModxMcp\Tools\Ms2ProductLinkCreateTool(),
    new \ModxMcp\Tools\Ms2ProductLinkDeleteTool(),
);
$valid = array('link' => 1, 'master' => 2, 'slave' => 3);
$badValues = array(
    null, '', 0, '0', -1, '-1', 1.0, true, false,
    array(1), '01', '+1', '1.0', '1e0', '1 OR 1=1',
    '1 suffix', ' 1', "1\n", "1\0", '999999999999999999999999999999',
);
foreach ($tools as $tool) {
    foreach (array('link', 'master', 'slave') as $field) {
        foreach ($badValues as $bad) {
            $payload = $valid;
            $payload[$field] = $bad;
            $rejected = false;
            try {
                $tool->execute(new BlockAddonContext(), $payload);
            } catch (ModxMCPClientException $e) {
                $rejected = strpos($e->getMessage(), $field) !== false;
            }
            ms2GuardAssert($rejected, $tool->name() . ':' . $field . ':invalid');
            ++$checks;
        }
        $missing = $valid;
        unset($missing[$field]);
        $rejected = false;
        try {
            $tool->execute(new BlockAddonContext(), $missing);
        } catch (ModxMCPClientException $e) {
            $rejected = strpos($e->getMessage(), $field) !== false;
        }
        ms2GuardAssert($rejected, $tool->name() . ':' . $field . ':missing');
        ++$checks;
    }
}
$input = array(
    'link' => '5', 'master' => 6, 'slave' => '7',
    'action' => 'unexpected',
    '_runtime_preconditions' => array('untrusted' => true),
    'id' => "1' OR 1=1",
);
$normalized = \ModxMcp\Tools\MiniShop2Support::productLinkPayload($input);
ms2GuardAssert($normalized === array('link' => 5, 'master' => 6, 'slave' => 7),
    'valid IDs normalized and unrelated parameters removed');
++$checks;
// RU: Прямой обход через run_processor с кастомным путём запрещён.
// EN: Exact allowlisting cannot authorize user-supplied add-on processor roots.
require_once $base . '../Security/ProcessorAccessPolicy.php';
foreach (array('allowlist', 'privileged') as $mode) {
    $denied = false;
    try {
        \ModxMcp\Security\ProcessorAccessPolicy::authorize(
            array(
                'processor' => 'mgr/product/productlink/create',
                'processors_path' => '/tmp/addon/processors/',
                'properties' => array(
                    'link' => 1, 'master' => '1 OR 1=1', 'slave' => 3
                ),
            ),
            $mode,
            'mgr/product/productlink/create',
            'mgr/product/productlink/create'
        );
    } catch (RuntimeException $e) {
        $denied = strpos($e->getMessage(), 'AUTH_DENIED:') === 0;
    }
    ms2GuardAssert($denied, 'generic processor custom-path bypass denied: ' . $mode);
    ++$checks;
}
echo 'MS2_PRODUCTLINK_STRICT_IDS_BEFORE_SQL_OK checks=' . $checks . PHP_EOL;
