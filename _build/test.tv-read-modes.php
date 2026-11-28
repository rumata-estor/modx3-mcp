<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Режимы чтения TV и независимый отказ штатного рендеринга.
 * EN: TV read modes and fail-closed native rendering authorization.
 */
class ModxMCPClientException extends RuntimeException {}
require __DIR__.'/../core/components/modxmcp/src/Autoloader.php';
\ModxMcp\Autoloader::register(__DIR__.'/../core/components/modxmcp/src');
class TvReadStatement {
    private $m;private $key;
    public function __construct($m){$this->m=$m;}
    public function execute($args){$this->key=$args[0];return !$this->m->dbFailure;}
    public function fetchColumn(){return $this->m->settings[$this->key]??false;}
}
class TvReadFixture {
    public $calls=0;public $raw;public $result;public $fail=false;
    public function __construct($raw,$result){$this->raw=$raw;$this->result=$result;}
    public function get($key){return array('id'=>1,'name'=>'sample','caption'=>'Sample','type'=>'text','value'=>$this->raw)[$key]??null;}
    public function getValue($rid){return $this->raw;}
    public function renderOutput($rid){$this->calls++;if($this->fail)throw new RuntimeException('SECRET_FIXTURE');return $this->result;}
}
class TvReadResource {
    public $tv;public $fallback=false;
    public function __construct($tv){$this->tv=$tv;}
    public function get($k){return $k==='template'?1:2;}
    public function getTemplateVars(){return $this->fallback?array():array($this->tv);}
    public function getTVValue($k){throw new RuntimeException('raw must not render');}
}
class TvReadLink {public function get($k){return 1;}}
class TvReadModx {
    public $resource;public $resourceIdentifier=99;public $target;public $settings;public $dbFailure=false;
    public function __construct($tv){$this->target=new TvReadResource($tv);$this->resource=new stdClass();
        $this->settings=array('modxmcp.external_authorization_required'=>'0','modxmcp.allow_native_tv_rendering'=>'0');}
    public function getVersionData(){return array('version'=>3);}
    public function getTableName($class){return 'modx_system_settings';}
    public function prepare($sql){return new TvReadStatement($this);}
    public function getObject($class,$id,$cache=false){return $class==='resource'?$this->target:$this->target->tv;}
    public function getCollection($class,$criteria){return array(new TvReadLink());}
}
class TvReadContext {
    private $m;
    public function __construct($m){$this->m=$m;}
    public function modx(){return $this->m;}
    public function platform(){return $this;}
    public function className($k){return $k;}
}
$checks=0;
function tvModeCheck($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
$tool=new \ModxMcp\Tools\ResourceTvListTool();
foreach(array('18||19','@INHERIT 0','','0') as $raw){
    $tv=new TvReadFixture($raw,'formatted');
    $m=new TvReadModx($tv);$c=new TvReadContext($m);$prev=$m->resource;
    foreach(array(false,true) as $fallback){
        $m->target->fallback=$fallback;
        $row=$tool->execute($c,array('resource_id'=>2))['tvs'][0];
        tvModeCheck($row['value']===$raw && $row['raw']===$raw && !array_key_exists('rendered',$row) && !$tv->calls,'raw mode preserves exact bytes');
    }
    foreach(array(array('0','0',false),array('1','1',false),array('0','bad',false),array('0','1',true),array(null,'1',false)) as $state){
        $m->settings['modxmcp.external_authorization_required']=$state[0];
        $m->settings['modxmcp.allow_native_tv_rendering']=$state[1];$m->dbFailure=$state[2];
        try{$tool->execute($c,array('resource_id'=>2,'value_mode'=>'both'));throw new LogicException('unexpected render');}
        catch(ModxMCPClientException $e){tvModeCheck(strpos($e->getMessage(),'SAFETY_CONFIRMATION_REQUIRED:')===0 && !$tv->calls,'native render blocked');}
    }
    $m->settings=array('modxmcp.external_authorization_required'=>'0','modxmcp.allow_native_tv_rendering'=>'1');$m->dbFailure=false;
    foreach(array('both','rendered') as $mode){
        $row=$tool->execute($c,array('resource_id'=>2,'value_mode'=>$mode))['tvs'][0];
        tvModeCheck($row['raw']===$raw && $row['value']===$raw && $row['rendered']==='formatted' && $row['render_status']==='rendered','explicit rendering');
        tvModeCheck($m->resource===$prev && $m->resourceIdentifier===99,'resource restored');
    }
    $tv->fail=true;
    $row=$tool->execute($c,array('resource_id'=>2,'value_mode'=>'both'))['tvs'][0];
    tvModeCheck($row['rendered']===null && $row['render_status']==='failed' && strpos(json_encode($row),'SECRET_FIXTURE')===false && $m->resource===$prev,'failed renderer redacted');
    $tv->fail=false;$tv->result=array();
    $row=$tool->execute($c,array('resource_id'=>2,'value_mode'=>'both'))['tvs'][0];
    tvModeCheck($row['rendered']===null && $row['render_status']==='unavailable','nonscalar renderer not misrepresented');
    try{$tool->execute($c,array('resource_id'=>2,'value_mode'=>'invalid'));throw new LogicException('bad mode accepted');}
    catch(ModxMCPClientException $e){tvModeCheck(strpos($e->getMessage(),'VALIDATION_ERROR:')===0,'invalid mode');}
}
echo 'TV_READ_MODES_OK checks='.$checks.PHP_EOL;
