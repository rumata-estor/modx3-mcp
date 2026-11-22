<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Контракт чтения ресурса за CAS-отпечатком: get_element обязан передавать
 * RU: skipFormatDates=true в resource/get (свойство поддерживается процессором
 * RU: на MODX 2 и MODX 3). Без него pub_date/unpub_date/publishedon
 * RU: переформатируются через manager_date_format + manager_time_format и
 * RU: timezone PHP (дискретизация до минут при дефолте manager_time_format='H:i')
 * RU: — отпечаток зависит от настроек Manager и момента чтения, а не от
 * RU: состояния цели: ложные STALE_STATE и пропуск реальных изменений.
 * EN: Contract of resource reads behind CAS fingerprints: get_element must pass
 * EN: skipFormatDates=true to resource/get (supported by the processor on both
 * EN: MODX 2 and MODX 3). Without it pub_date/unpub_date/publishedon are
 * EN: reformatted via manager_date_format + manager_time_format and the PHP
 * EN: timezone (minute granularity under the default 'H:i'), so the fingerprint
 * EN: depends on Manager settings and read time instead of the target state:
 * EN: false STALE_STATE and missed real changes.
 */
$root = dirname(__DIR__) . '/core/components/modxmcp/src';
require_once $root . '/Autoloader.php';
\ModxMcp\Autoloader::register($root);

class ModxMCPClientException extends \RuntimeException {}

class DateStabilityResponse {
    private $object;
    public function __construct(array $object) { $this->object = $object; }
    public function isError() { return false; }
    public function getObject() { return $this->object; }
}

class DateStabilityPlatform {
    public $calls = array();
    public function runProcessor($modx, $action, array $properties) {
        $this->calls[] = array($action, $properties);
        return new DateStabilityResponse(array(
            'id' => 15,
            'pagetitle' => 'Fixture',
            'pub_date' => '2026-11-22 10:30:00',
            'unpub_date' => '0000-00-00 00:00:00',
            'publishedon' => '2026-11-22 10:30:40',
        ));
    }
}

class DateStabilityModx {
    public $user = null;
    public function getOption($key, $options = null, $default = null) { return $default; }
    public function getObject($class, $criteria) { return null; }
}

class DateStabilityContext {
    private $modx;
    private $platform;
    public function __construct() {
        $this->modx = new DateStabilityModx();
        $this->platform = new DateStabilityPlatform();
    }
    public function modx() { return $this->modx; }
    public function platform() { return $this->platform; }
}

function fail($message) { throw new \RuntimeException($message); }

$context = new DateStabilityContext();
$tool = new \ModxMcp\Tools\ElementGetTool();
if ($tool->isMutation()) { fail('GET_ELEMENT_MUST_BE_READ_ONLY'); }
$result = $tool->execute($context, array('type' => 'resource', 'id' => 15));

if (count($context->platform()->calls) !== 1) { fail('EXPECT_SINGLE_PROCESSOR_CALL'); }
list($action, $properties) = $context->platform()->calls[0];
if ($action !== 'resource/get') { fail('UNEXPECTED_ACTION: ' . $action); }
if (($properties['id'] ?? null) !== 15) { fail('UNEXPECTED_RESOURCE_ID'); }
if (($properties['skipFormatDates'] ?? null) !== true) {
    fail('SKIP_FORMAT_DATES_NOT_PASSED');
}
if (($result['pub_date'] ?? null) !== '2026-11-22 10:30:00') { fail('RAW_PUB_DATE_EXPECTED'); }
if (($result['publishedon'] ?? null) !== '2026-11-22 10:30:40') { fail('RAW_PUBLISHEDON_EXPECTED'); }

echo 'CAS_FINGERPRINT_DATE_STABILITY_OK checks=6' . PHP_EOL;
