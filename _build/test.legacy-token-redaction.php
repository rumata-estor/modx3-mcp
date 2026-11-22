<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Исполняем реальный normalizeSystemSetting обеих монолитных реализаций.
 * EN: Execute actual legacy normalizeSystemSetting for MODX 2 and 3.
 */
namespace MODX\Revolution {
    class modSystemSetting {
        private $properties;
        public function __construct(array $properties){$this->properties=$properties;}
        public function get($key){return $this->properties[$key]??null;}
    }
}
namespace {
    class modSystemSetting {
        private $properties;
        public function __construct(array $properties){$this->properties=$properties;}
        public function get($key){return $this->properties[$key]??null;}
    }
    $variant = $argv[1]??'';
    if (!in_array($variant,array('modx2','modx3'),true))
        throw new \RuntimeException('specify modx2 or modx3');
    require __DIR__.'/../core/components/modxmcp/'
        .($variant==='modx2'?'legacy/modx2/modxmcp.class.php':'model/modxmcp.class.php');
    $class=new \ReflectionClass('modxMCP');
    $instance=$class->newInstanceWithoutConstructor();
    $normalizer=$class->getMethod('normalizeSystemSetting');
    $normalizer->setAccessible(true);
    $cases=array(
        array('modxmcp.api_token','a','[REDACTED]'),
        array('modxmcp.api_token','1234567890123456','[REDACTED]'),
        array('modxmcp.api_token','123456789012345678901234','[REDACTED]'),
        array('MODXMCP.API_TOKEN','short','[REDACTED]'),
        array('smtp_password','secret','[REDACTED]'),
        array('provider.client_secret','secret','[REDACTED]'),
        array('modxmcp.permit_public_key','public-ed25519','public-ed25519'),
        array('modxmcp.processor_access_mode','disabled','disabled'),
    );
    $settingClass=$variant==='modx2'
        ? 'modSystemSetting':'MODX\\Revolution\\modSystemSetting';
    foreach($cases as $case){
        $row=$normalizer->invoke($instance,new $settingClass(array(
            'key'=>$case[0],'value'=>$case[1]
        )));
        if (!is_array($row) || ($row['key']??null)!==$case[0]
            || ($row['value']??null)!==$case[2])
            throw new \RuntimeException(
                'LEGACY_TOKEN_REDACTION_FAILED: '.$variant.' '.$case[0]
            );
    }
    echo 'LEGACY_TOKEN_REDACTION_OK platform='.$variant
        .' checks='.count($cases).PHP_EOL;
}
