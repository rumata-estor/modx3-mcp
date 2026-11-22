<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Секреты не выходят через системные, контекстные и ClientConfig настройки.
 * EN: Settings readbacks never expose system, context or addon credentials.
 */
namespace ModxMcp\Tools {
    interface ToolInterface {
        public function name(); public function group(); public function isMutation();
        public function supports($context); public function execute($context,array $data);
    }
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/SettingSecretSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/SystemSettingListTool.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ClientConfigSupport.php';
}
namespace {
class DummySecretSetting {
    private $values; private $children;
    public function __construct(array $values,array $children=array()){
        $this->values=$values;$this->children=$children;
    }
    public function get($key){return isset($this->values[$key])?$this->values[$key]:null;}
    public function toArray(){return $this->values;}
    public function getMany($name){return $this->children;}
}
function secretCheck($truth,$message){
    global $checks;
    if(!$truth)throw new \RuntimeException('SETTING_SECRET_BOUNDARY_FAIL: '.$message);
    $checks++;
}
$checks=0;
$cases=array(
    array('modxmcp.api_token','[REDACTED]'),
    array('smtp_password','[REDACTED]'),
    array('vendorAuthToken','[REDACTED]'),
    array('my_clientSecret','[REDACTED]'),
    array('payment.credentials','[REDACTED]'),
    array('dbPrivateKey','[REDACTED]'),
    array('modxmcp.permit_public_key','CAN_READ_PUBLIC_KEY'),
    array('site_name','CAN_READ_PUBLIC_KEY'),
    array('modxmcp.processor_access_mode','CAN_READ_PUBLIC_KEY'),
    array('tokenizer_version','CAN_READ_PUBLIC_KEY')
);
foreach($cases as $test){
    $obj=new DummySecretSetting(array(
        'key'=>$test[0],'value'=>'CAN_READ_PUBLIC_KEY','default'=>'CAN_READ_PUBLIC_KEY'
    ));
    $row=\ModxMcp\Tools\SystemSettingListTool::normalize($obj);
    secretCheck($row['value']===$test[1],'system key '.$test[0]);
    $children=array(new DummySecretSetting(array(
        'context'=>'web','value'=>'CAN_READ_PUBLIC_KEY'
    )));
    $addon=new DummySecretSetting(array(
        'key'=>$test[0],'value'=>'CAN_READ_PUBLIC_KEY','default'=>'CAN_READ_PUBLIC_KEY'
    ),$children);
    $context=new \stdClass();
    $result=\ModxMcp\Tools\ClientConfigSupport::normalize($context,$addon);
    secretCheck($result['value']===$test[1],'addon current '.$test[0]);
    secretCheck($result['default']===$test[1],'addon default '.$test[0]);
    secretCheck($result['context_values']['web']===$test[1],'addon override '.$test[0]);
}
$nested=array('total'=>3,'results'=>array(
    array('key'=>'smtp_password','value'=>'SECRET_A','context_key'=>'web'),
    array('key'=>'site_name','value'=>'NORMAL_SITE'),
    array('key'=>'modxmcp.permit_public_key','value'=>'PUBLIC_KEY'),
    array('key'=>'authToken','value'=>'SECRET_B','default'=>'SECRET_C'),
));
$filtered=\ModxMcp\Tools\SettingSecretSupport::contextResponse($nested);
secretCheck($filtered['results'][0]['value']==='[REDACTED]','context result redaction');
secretCheck($filtered['results'][1]['value']==='NORMAL_SITE','context ordinary preserved');
secretCheck($filtered['results'][2]['value']==='PUBLIC_KEY','context public key preserved');
secretCheck($filtered['results'][3]['default']==='[REDACTED]','context default redaction');
secretCheck($filtered['total']===3,'list shape preserved');
$single=\ModxMcp\Tools\SettingSecretSupport::contextResponse(array(
    'object'=>array('key'=>'api_token','value'=>'SECRET_D'),
    'success'=>true
));
secretCheck($single['object']['value']==='[REDACTED]','single nested object');
echo 'SETTING_SECRET_BOUNDARY_OK checks='.$checks.PHP_EOL;
}
