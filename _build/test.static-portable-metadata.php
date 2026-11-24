<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: PHP сохраняет inode, ACL и xattrs при записи без внешнего помощника.
 * EN: PHP-only in-place writes retain inode metadata and need no runtime helper.
 */
namespace ModxMcp\Tools {
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/ElementContentSupport.php';
    require_once __DIR__.'/../core/components/modxmcp/src/Tools/StaticFileWriteSupport.php';
}
namespace {
class ModxMCPClientException extends \RuntimeException {}
class PortableMetadataModx {
    private $root;
    public function __construct($root){$this->root=$root;}
    public function getOption($key){
        if($key==='core_path')return $this->root.'/core/';
        if($key==='base_path')return $this->root.'/';
        if($key==='modxmcp.static_io_mode')return 'portable';
        return null;
    }
}
class PortableMetadataContext {
    private $modx;
    public function __construct($root){$this->modx=new PortableMetadataModx($root);}
    public function modx(){return $this->modx;}
}
function metadataCheck($ok,$message) {
    if(!$ok)throw new \RuntimeException('PORTABLE_METADATA_FAIL '.$message);
}
function metadataProcess(array $command) {
    $pipes=array();
    $process=proc_open($command,array(
        0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w'),
    ),$pipes);
    metadataCheck(is_resource($process),'could not start process');
    fclose($pipes[0]);
    $stdout=stream_get_contents($pipes[1]);
    $stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    return array(proc_close($process),$stdout,$stderr);
}
function metadataPython($operation,$path) {
    $script=<<<'PY'
import os,sys
op,path=sys.argv[1:]
attr=b'user.modxmcp_portable_metadata'
if op=='set': os.setxattr(path,attr,b'RETAIN_ME')
elif op=='get': sys.stdout.buffer.write(os.getxattr(path,attr))
elif op=='remove': os.removexattr(path,attr)
else: raise RuntimeError('invalid operation')
PY;
    $result=metadataProcess(array('/usr/bin/python3','-c',$script,$operation,$path));
    metadataCheck($result[0]===0,'python '.$operation.' failed '.$result[2]);
    return $result[1];
}
function metadataUpdate($ctx,$target,$payload,$label) {
    $ino=fileinode($target);
    $owner=fileowner($target);
    $group=filegroup($target);
    $written=\ModxMcp\Tools\StaticFileWriteSupport::replace($ctx,$target,$payload);
    clearstatcache(true,$target);
    metadataCheck($written===strlen($payload),$label.' update failed');
    metadataCheck(file_get_contents($target)===$payload,$label.' bytes mismatch');
    metadataCheck(fileinode($target)===$ino,$label.' changed inode');
    metadataCheck(fileowner($target)===$owner && filegroup($target)===$group,
        $label.' changed owner or group');
}
$root=sys_get_temp_dir().'/modxmcp-portable-meta-'.bin2hex(random_bytes(6));
$folder=$root.'/core/elements/chunks';
mkdir($folder,0700,true);
$target=$folder.'/existing.tpl';
file_put_contents($target,'ORIGINAL');
chmod($target,0640);
$ctx=new PortableMetadataContext($root);
try {
    if(isset($argv[1]) && $argv[1]==='--no-helper') {
        metadataUpdate($ctx,$target,'PHP_ONLY','disabled process functions');
        echo 'PORTABLE_METADATA_NO_HELPER_OK'.PHP_EOL;
    } else {
        metadataUpdate($ctx,$target,'NORMAL','clean file');
        metadataPython('set',$target);
        metadataUpdate($ctx,$target,'KEEP_XATTR','user xattr');
        metadataCheck(metadataPython('get',$target)==='RETAIN_ME',
            'user xattr lost during PHP-only update');
        metadataPython('remove',$target);

        $setfacl=metadataProcess(array('setfacl','-m','u:nobody:r--',$target));
        metadataCheck($setfacl[0]===0,'setfacl fixture failed '.$setfacl[2]);
        $before=metadataProcess(array('getfacl','-cp',$target));
        metadataCheck($before[0]===0 && strpos($before[1],'user:nobody:r--')!==false,
            'named ACL fixture missing');
        metadataUpdate($ctx,$target,'KEEP_ACL','named ACL');
        $after=metadataProcess(array('getfacl','-cp',$target));
        metadataCheck($after[0]===0 && $after[1]===$before[1],'ACL changed');
        metadataCheck(metadataProcess(array('setfacl','-b',$target))[0]===0,
            'ACL cleanup failed');
        metadataCheck(count(glob($folder.'/.modxmcp-*.tmp'))===0,
            'unexpected temp files');

        $noHelper=metadataProcess(array(PHP_BINARY,'-d',
            'disable_functions=proc_open,exec,shell_exec,system',__FILE__,'--no-helper'));
        metadataCheck($noHelper[0]===0,'PHP-only update with disabled process functions failed '.$noHelper[2]);
        echo 'STATIC_PORTABLE_METADATA_OK'.PHP_EOL;
    }
} finally {
    if(is_file($target))unlink($target);
    foreach(glob($folder.'/.modxmcp-*.tmp') as $temp)unlink($temp);
    rmdir($folder);rmdir($root.'/core/elements');rmdir($root.'/core');rmdir($root);
}
}