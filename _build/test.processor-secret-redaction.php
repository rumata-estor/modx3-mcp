<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка сокрытия секретов во вложенном ответе произвольного процессора.
 * EN: Regression for nested credential redaction in generic processor output.
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ProcessorSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
$payload=array(
    'success'=>true,'message'=>'Generated password: secret-value',
    'object'=>array(
        'id'=>15,'username'=>'demo',
        'password'=>'secret-hash','token'=>'secret-token',
        'profile'=>array('private_key'=>'secret-key',
            'data'=>array(array('api_key'=>'secret-api','name'=>'visible'))),
    ),
    'results'=>array(array('id'=>20,'salt'=>'secret-salt','title'=>'visible')),
);
$safe=\ModxMcp\Tools\ProcessorSupport::sanitizeProcessorOutput($payload);
if ($safe['object']['username']!=='demo'
    || $safe['object']['id']!==15
    || $safe['results'][0]['title']!=='visible'
    || $safe['object']['profile']['data'][0]['name']!=='visible') {
    throw new \RuntimeException('PROCESSOR_SAFE_FIELDS_LOST');
}
$encoded=json_encode($safe);
foreach (array('secret-value','secret-hash','secret-token','secret-key',
    'secret-api','secret-salt') as $secret) {
    if (strpos($encoded,$secret)!==false)
        throw new \RuntimeException('PROCESSOR_SECRET_LEAK');
}
foreach (array(
    $safe['message'],$safe['object']['password'],$safe['object']['token'],
    $safe['object']['profile']['private_key'],
    $safe['object']['profile']['data'][0]['api_key'],
    $safe['results'][0]['salt']
) as $field) {
    if ($field!=='[REDACTED]')
        throw new \RuntimeException('PROCESSOR_REDACTION_MISSING');
}
echo 'GENERIC_PROCESSOR_SECRET_REDACTION_OK checks=6'.PHP_EOL;
}
