<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка нормализации массива и JSON-строки ответов процессоров.
 * EN: Regression for decoded-array and JSON-string processor responses.
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ProcessorSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/PackageSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class TestProcessorResponse {
    private $raw;
    public function __construct($raw) {$this->raw=$raw;}
    public function getResponse() {return $this->raw;}
    public function getObject() {return array();}
}
$cases=array(
    array('success'=>true,'message'=>'ok','errors'=>array(),
        'object'=>array('id'=>77),'total'=>1),
    array('total'=>2,'results'=>array(array('id'=>1),array('id'=>2))),
);
$checks=0;
foreach($cases as $case) {
    foreach(array($case,json_encode($case)) as $raw) {
        $response=new TestProcessorResponse($raw);
        $decoded=\ModxMcp\Tools\ProcessorSupport::decodedResponse($response);
        if($decoded!==$case)throw new \RuntimeException('PROCESSOR_RESPONSE_DECODE_FAILED');
        $normalized=\ModxMcp\Tools\ProcessorSupport::normalize($response);
        if(!is_array($normalized))throw new \RuntimeException('PROCESSOR_NORMALIZE_FAILED');
        if(isset($case['results'])) {
            $list=\ModxMcp\Tools\PackageSupport::listResult($response);
            if($list['total']!==2 || count($list['results'])!==2)
                throw new \RuntimeException('PACKAGE_LIST_DECODE_FAILED');
        } else {
            if((int)$normalized['id']!==77)
                throw new \RuntimeException('PROCESSOR_OBJECT_UNWRAP_FAILED');
        }
        $checks++;
    }
}
echo 'PROCESSOR_ARRAY_STRING_RESPONSE_OK checks='.$checks.PHP_EOL;
}
