<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка безопасного снятия отметки установки старых пакетов.
 * EN: Fail-closed tests for retiring superseded package installation markers.
 */
class xPDOTransport {
    const PACKAGE_ACTION='package_action';
    const ACTION_UNINSTALL=0;
    const ACTION_INSTALL=1;
    const ACTION_UPGRADE=2;
    const PREEXISTING_MODE='preexisting_mode';
    const RESTORE_PREEXISTING=2;
    public static function compareSignature($a,$b) {
        return version_compare(substr($a,strpos($a,'-')+1),substr($b,strpos($b,'-')+1));
    }
}
class HistoryPackage {
    public $data;
    public $saves=0;
    public $fail=false;
    public function __construct($signature,$name,$installed='2026-01-01 00:00:00'){
        $this->data=array('signature'=>$signature,'package_name'=>$name,'installed'=>$installed);
    }
    public function get($k){return $this->data[$k];}
    public function set($k,$v){$this->data[$k]=$v;}
    public function save(){$this->saves++;return !$this->fail;}
}
class HistoryQuery {
    private $value;private $fail;
    public function __construct($value,$fail){$this->value=$value;$this->fail=$fail;}
    public function select($v){}
    public function limit($v){}
    public function prepare(){return $this;}
    public function execute(){return !$this->fail;}
    public function fetchColumn(){return $this->value;}
    public function errorCode(){return '00000';}
    public function closeCursor(){}
}
class HistoryModx {
    public $major;public $records;public $queryFail=false;public $present=false;public $missingCurrent=false;
    public function __construct($major,$records){$this->major=$major;$this->records=$records;}
    public function getVersionData(){return array('version'=>$this->major);}
    public function getObject($class,$criteria,$cache=false){
        if($this->missingCurrent)return null;
        foreach($this->records as $r)if($r->get('signature')===$criteria['signature'])return $r;
        return null;
    }
    public function getCollection($class,$criteria,$cache=false){return $this->records;}
    public function newQuery($class,$criteria){return new HistoryQuery($this->present?'existing':false,$this->queryFail);}
    public function escape($v){return $v;}
    public function log($level,$message){}
}
$root=sys_get_temp_dir().'/modxmcp-history-'.bin2hex(random_bytes(8));
mkdir($root.'/core/components',0700,true);
mkdir($root.'/assets/components',0700,true);
define('MODX_CORE_PATH',$root.'/core/');
define('MODX_ASSETS_PATH',$root.'/assets/');
function historyRun($modx,$signature,$action=0,$restore=false){
    $transport=(object)array('xpdo'=>$modx,'signature'=>$signature);
    $options=array('package_action'=>$action,'preexisting_mode'=>$restore?2:0);
    return include __DIR__.'/resolvers/resolve.package-history.php';
}
function historyCheck($condition,$label){global $checks;if(!$condition)throw new RuntimeException($label);$checks++;}
$checks=0;
try {
    foreach(array(2,3) as $major){
        $prefix='modx'.$major.'mcp';
        $cur=$prefix.'-1.2.8-beta5';
        foreach(array('normal','install','upgrade','restore','files','symlink','records','query_fail','newer','unknown','missing_current','failed_save','alien','not_installed') as $case){
            $current=new HistoryPackage($cur,strtoupper($prefix));
            $old=new HistoryPackage($prefix.'-1.2.7-pl',strtoupper($prefix));
            $older=new HistoryPackage($prefix.'-1.2.4-pl',strtoupper($prefix));
            $inactive=new HistoryPackage($prefix.'-1.2.3-pl',strtoupper($prefix),null);
            $m=new HistoryModx($major,array($current,$old,$older,$inactive));
            $action=0;$restore=false;
            if($case==='install')$action=1;
            if($case==='upgrade')$action=2;
            if($case==='restore')$restore=true;
            if($case==='files')mkdir(MODX_CORE_PATH.'components/modxmcp');
            if($case==='symlink')symlink($root.'/missing',MODX_ASSETS_PATH.'components/modxmcp');
            if($case==='records')$m->present=true;
            if($case==='query_fail')$m->queryFail=true;
            if($case==='newer')$m->records[]=new HistoryPackage($prefix.'-1.2.9-pl',strtoupper($prefix));
            if($case==='unknown')$m->records[]=new HistoryPackage($prefix.'-unknown',strtoupper($prefix));
            if($case==='alien')$m->records[]=new HistoryPackage('other-1.0.0-pl','other');
            if($case==='missing_current')$m->missingCurrent=true;
            if($case==='failed_save')$old->fail=true;
            if($case==='not_installed')$current->data['installed']=null;
            $result=historyRun($m,$cur,$action,$restore);
            historyCheck($current->saves===0 && $inactive->saves===0,'current or inactive mutated: '.$case);
            if($case==='normal'){
                historyCheck($result===true && $old->saves===1 && $older->saves===1
                    && $old->get('installed')===null && $older->get('installed')===null,'normal retirement');
            }elseif($case==='failed_save'){
                historyCheck($result===false && $old->saves===1 && $older->saves===0,'save failure stops');
            }else{
                $expected=in_array($case,array('install','upgrade','restore'),true);
                historyCheck($result===$expected && $old->saves===0 && $older->saves===0,'unexpected retirement: '.$case);
            }
            if($case==='files')rmdir(MODX_CORE_PATH.'components/modxmcp');
            if($case==='symlink')unlink(MODX_ASSETS_PATH.'components/modxmcp');
        }
    }
    echo 'PACKAGE_HISTORY_RESOLVER_OK checks='.$checks.PHP_EOL;
} finally {
    if(is_dir(MODX_CORE_PATH.'components/modxmcp'))rmdir(MODX_CORE_PATH.'components/modxmcp');
    if(is_link(MODX_ASSETS_PATH.'components/modxmcp'))unlink(MODX_ASSETS_PATH.'components/modxmcp');
    foreach(array('core','assets') as $part){rmdir($root.'/'.$part.'/components');rmdir($root.'/'.$part);}
    rmdir($root);
}
