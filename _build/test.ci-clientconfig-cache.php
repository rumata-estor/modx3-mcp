<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Реальный кеш MODX 2/3: адресная очистка ClientConfig только в disposable CI.
 * EN: Test real MODX 2/3 ClientConfig cache key invalidation in disposable CI.
 */
if (PHP_SAPI !== 'cli' || getenv('MODXMCP_DISPOSABLE_CI') !== '1'
    || strpos((string)getenv('MODX_CONFIG_CORE'), '/home/runner/') !== 0
    || !is_file((string)getenv('MODX_CONFIG_CORE'))) {
    fwrite(STDERR, "Refusing real ClientConfig cache test outside disposable CI.\n");
    exit(3);
}
require_once getenv('MODX_CONFIG_CORE');
$is2=!is_file(MODX_CORE_PATH.'src/Revolution/modX.php');
if ($is2) {
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
    $modx=new modX();
} else {
    require_once MODX_CORE_PATH.'vendor/autoload.php';
    $modx=\MODX\Revolution\modX::getInstance();
}
$modx->initialize('mgr');
require_once __DIR__.'/../core/components/modxmcp/src/Tools/ClientConfigSupport.php';
class DisposableClientConfigCacheContext {
    private $modx;
    public function __construct($modx) { $this->modx=$modx; }
    public function modx() { return $this->modx; }
}
$manager=$modx->getCacheManager();
if (!$manager)throw new RuntimeException('MODX cache manager missing.');
$options=array('cache_key'=>'system_settings');
$fixture=array('global'=>array('ci_disposable_clientconfig'=>'stale'),
    'contexts'=>array('web'=>array('ci_disposable_clientconfig'=>'stale_web')));
if (!$manager->set('clientconfig',$fixture,0,$options)) {
    throw new RuntimeException('Disposable cache seed failed.');
}
if ($manager->get('clientconfig',$options)!==$fixture) {
    throw new RuntimeException('Disposable ClientConfig cache not readable.');
}
$modx->setOption('clientconfig.clear_cache',false);
\ModxMcp\Tools\ClientConfigSupport::refresh(
    new DisposableClientConfigCacheContext($modx)
);
if ($manager->get('clientconfig',$options)!==null) {
    throw new RuntimeException('CLIENTCONFIG_CACHE_REMAINS_STALE_AFTER_MCP_REFRESH');
}
echo 'CLIENTCONFIG_REAL_MODX_CACHE_INVALIDATED_OK platform='
    .($is2?'modx2':'modx3').PHP_EOL;
