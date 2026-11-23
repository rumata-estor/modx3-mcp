<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «media-path-boundaries»; тестовый код не запускается на production.
 * EN: «media-path-boundaries» regression test; not executed in production.
 */
/**
 * Pure PHP path-validation regression, no MODX installation and no I/O.
 */
class ModxMCPClientException extends Exception {}
require_once __DIR__.'/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
require_once __DIR__.'/../core/components/modxmcp/src/Tools/MediaSourceMutationSupport.php';
use ModxMcp\Tools\MediaSourceMutationSupport as M;
$invalidPaths=array(
    '../escape','folder/../../escape','/../escape',
    'C:\\Windows\\system.ini','\\\\server\\share','../',
    "nul\0byte","bad\nfile",
);
$invalidNames=array(
    '../escape','folder/name','folder\\name',
    '..','.','',"nul\0byte","bad\nfile",
);
$check=0;
foreach($invalidPaths as $path) {
    $denied=false;
    try {M::relativePath($path,true);} catch(ModxMCPClientException $e){$denied=true;}
    if(!$denied)throw new RuntimeException('Unsafe media path allowed: '.bin2hex($path));
    $check++;
}
foreach($invalidNames as $name) {
    $denied=false;
    try {M::leafName($name);} catch(ModxMCPClientException $e){$denied=true;}
    if(!$denied)throw new RuntimeException('Unsafe media name allowed: '.bin2hex($name));
    $check++;
}
// Malformed JSON values cannot be stringified to "Array", "Object", "1".
$invalidTypes=array(
    array('folder'),array('name'=>'secret'),(object)array('path'=>'x'),
    true,false,null,42
);
foreach($invalidTypes as $value) {
    foreach(array('relativePath','leafName') as $method) {
        $denied=false;
        try { M::$method($value); }
        catch(ModxMCPClientException $e) {$denied=true;}
        if(!$denied) {
            throw new RuntimeException('Malformed media path/name accepted: '.gettype($value));
        }
        $check++;
    }
}
if(M::relativePath('/folder/sub/')!=='folder/sub'
    || M::relativePath('/',true)!==''
    || M::relativePath('folder\\sub')!=='folder/sub'
    || M::leafName('valid.txt')!=='valid.txt')
    throw new RuntimeException('Valid media paths incorrectly changed');
$check+=4;
try{M::relativePath('/');throw new RuntimeException('Media root delete allowed');}
catch(ModxMCPClientException $e){$check++;}
echo "MEDIA_PATH_GUARD_UNIT_OK checks=".$check.PHP_EOL;
