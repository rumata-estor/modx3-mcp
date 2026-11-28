<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Сохранение старого файла при смене ссылки и восстановление настройки.
 * EN: Preserve former static references and restore request-local automation.
 */
require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementMutationSupport.php';
class ModxMCPClientException extends RuntimeException {}
use ModxMcp\Tools\ElementMutationSupport as StaticSupport;
class RenameModx {
    public $opts;
    public function __construct($root){$this->opts=array('core_path'=>$root.'/core/','base_path'=>$root.'/','assets_path'=>$root.'/assets/');}
    public function getOption($key,$options=null,$default=null){return $this->opts[$key]??$default;}
    public function setOption($key,$value){$this->opts[$key]=$value;}
}
class RenameContext {
    private $m;
    public function __construct($root){$this->m=new RenameModx($root);}
    public function modx(){return $this->m;}
}
$root=sys_get_temp_dir().'/modxmcp-rename-'.bin2hex(random_bytes(6));
mkdir($root.'/core/elements/chunks',0700,true);
$old=$root.'/core/elements/chunks/old.tpl';
file_put_contents($old,'ORIGINAL');
$c=new RenameContext($root);$m=$c->modx();$checks=0;
function renameCheck($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
try {
    foreach(array('chunk'=>'chunks','snippet'=>'snippets','plugin'=>'plugins','template'=>'templates','tv'=>'tvs') as $type=>$suffix){
        $key='static_elements_automate_'.$suffix;
        foreach(array(true,'1',false,'0') as $value){
            $m->setOption($key,$value);
            $r=StaticSupport::withoutNativeCleanup($c,$type,function()use($m,$key,$old){
                if($m->getOption($key))unlink($old);
                return 'ok';
            });
            renameCheck($r==='ok' && $m->getOption($key)===$value && file_get_contents($old)==='ORIGINAL','old bytes preserved and exact option restored');
            try{
                StaticSupport::withoutNativeCleanup($c,$type,function()use($m,$key){
                    renameCheck(!$m->getOption($key),'cleanup inactive during failing save');
                    throw new RuntimeException('fault');
                });
                throw new LogicException('fault was swallowed');
            }catch(RuntimeException $e){renameCheck($e->getMessage()==='fault' && $m->getOption($key)===$value,'exception restores original option');}
        }
    }
    $before=array('static'=>true,'static_file'=>'core/elements/chunks/old.tpl','source'=>1,'name'=>'old');
    $after=$before;$after['name']='new';
    renameCheck(strpos(StaticSupport::staticReferenceWarning($c,$before,$after),'retained after rename')!==false,'rename retains file');
    $after['static_file']='core/elements/chunks/new.tpl';
    $warning=StaticSupport::staticReferenceWarning($c,$before,$after);
    renameCheck(strpos($warning,'may be orphaned')!==false && strpos($warning,$before['static_file'])!==false,'changed link reports candidate');
    $after=$before;$after['static']=false;
    renameCheck(strpos(StaticSupport::staticReferenceWarning($c,$before,$after),'may be orphaned')!==false,'detach reports candidate');
    $after=$before;$after['source']=2;
    renameCheck(strpos(StaticSupport::staticReferenceWarning($c,$before,$after),'may be orphaned')!==false,'changed source reports candidate');
    renameCheck(StaticSupport::staticReferenceWarning($c,$before,$before)==='','no warning for unchanged file');
    $unsafe=$before;$unsafe['static_file']='/private/secret';
    renameCheck(strpos(StaticSupport::staticReferenceWarning($c,$unsafe,$after),'/private/secret')===false,'unsafe old path not disclosed');
    renameCheck(file_get_contents($old)==='ORIGINAL','diagnostic does not delete or rewrite');
    echo 'STATIC_RENAME_PRESERVATION_OK checks='.$checks.PHP_EOL;
} finally {
    if(is_file($old))unlink($old);
    rmdir(dirname($old));rmdir($root.'/core/elements');rmdir($root.'/core');rmdir($root);
}
