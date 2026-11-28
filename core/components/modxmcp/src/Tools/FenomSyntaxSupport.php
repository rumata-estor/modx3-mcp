<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Ограниченная проверка синтаксиса Fenom без загрузки pdoTools и его событий.
 * EN: Restricted Fenom compilation without loading pdoTools or site callbacks.
 */
namespace ModxMcp\Tools;

/**
 * Inspired by dampilov94/mcp-component task/32-fenom (FenomCheck):
 * use the real source() compiler, not a regex pretending to validate syntax.
 * Unlike the site instance, use no MODX providers, filters, plugins or render.
 * The audited Fenom 2.12.0 source is already supplied by matching pdoTools
 * installations; do not install dependencies or execute a Composer bootstrap.
 */
final class FenomSyntaxSupport
{
    const MAX_BYTES = 65536;
    const MAX_DELIMITERS = 1024;
    // Exact upstream f0cb251843de469b453b15c158cf0d1589d4de6d, from the
    // composer.lock of pdoTools v2.14.0 and the inspected 3.x revision.
    private const SOURCES = array(
        'Fenom\\Accessor' => '3d1b9554451cd72eeed40845c1ae3b6b5792cf8936e73d55988474f5f125ec78',
        'Fenom\\Compiler' => '93a7b2daa084808707584155184e4dc94dc71d4d7ae99e680e68048318a9f4a9',
        'Fenom\\Error\\CompileException' => '49ca0ad15d70b22ffabe461e6dea786095fc2cdccc43a8b8b0bf83a166a5f391',
        'Fenom\\Error\\InvalidUsageException' => 'd9d955b6e40e6e4a1010cdb2c9b8940e501d4f5a3e8975849ec9066d9d0498f9',
        'Fenom\\Error\\SecurityException' => 'faac0962039de3345a629367af8b3230d278435d54d92b008a2715a98172a3f1',
        'Fenom\\Error\\TokenizeException' => '206cd820dc6148e635ebd84e678125d59c7a52ea13dd0b586db3c40f71396ae5',
        'Fenom\\Error\\UnexpectedTokenException' => 'c4437f7a951bf441a0f049e22e618aeca541e81f1a8d96f3c3731f4a3236fc62',
        'Fenom\\Modifier' => '718d765ab658a551ecac9edf2f88bb7d35c28d495e46ba1efd612fca21b0291b',
        'Fenom\\Provider' => '34022a95413bcf06157ea680a3b005815f1339af1f536b13a7f359e6f46a2790',
        'Fenom\\ProviderInterface' => 'a3ad753a92ff342cec4ff3ae72032326056cf7ffeeba4fdbd014dae703ed30a4',
        'Fenom\\RangeIterator' => '0834a5452ed1cacd70fb5d69a43c9b83d23c05835e02455f9a8883d72e74453d',
        'Fenom\\Render' => '3db2246a12d209cdd4c655a3f53777b94c0b6f9cfad1224e5a48cbebd10c9ffd',
        'Fenom\\Tag' => 'a39f520feba44a0806b453313f22a6c3d12f51573252451e1a723754c7a40517',
        'Fenom\\Template' => '15e04c242f929171e9a2f52d76b5356c3a7d2e4f4371241d736e02765986f928',
        'Fenom\\Tokenizer' => '154389aded7fe5a32cde26f056bacbc2d24504cb5ae98b041f4ad992ee14ed69',
        'Fenom' => '973c2279cd6fa9669d938fbfb62a15e3ddc5a8871b41b42bda4dd511d78eb0b3',
    );

    private static function result($status, $reason, $line = null)
    {
        return array('status'=>$status, 'scope'=>'isolated_fenom_2.12',
            'reason'=>$reason, 'line'=>$line, 'rendered'=>false,
            'dependencies_checked'=>false, 'site_extensions_checked'=>false);
    }

    public static function check($modx, $content)
    {
        if (!is_string($content) || strlen($content)>self::MAX_BYTES
            || strpbrk($content, "\0")!==false
            || strpbrk($content, "\x01\x02")!==false
            || substr_count($content,'{')+substr_count($content,'(')
                +substr_count($content,'[')>self::MAX_DELIMITERS) {
            throw new \ModxMCPClientException(
                'VALIDATION_ERROR: content must be bounded Fenom text (65536 bytes, 1024 opening delimiters).'
            );
        }
        if (!function_exists('token_get_all')) return self::result('unavailable','TOKENIZER_UNAVAILABLE');
        // Only the fixed installed vendor path, never a client-supplied path,
        // setting-based custom core path or Composer autoload.php.
        $core=defined('MODX_CORE_PATH') ? realpath(MODX_CORE_PATH) : false;
        if ($core===false) return self::result('unavailable','FENOM_NOT_AVAILABLE');
        $root=rtrim($core,'/\\').'/components/pdotools/vendor/fenom/fenom/src';
        if (realpath($root)!==$root) return self::result('unavailable','FENOM_NOT_AVAILABLE');
        foreach (self::SOURCES as $class=>$digest) {
            $file=$root.'/'.str_replace('\\','/',$class).'.php';
            if (realpath($file)!==$file || !is_file($file)
                || @hash_file('sha256',$file)!==$digest) {
                return self::result('unavailable','UNVERIFIED_FENOM_SOURCE');
            }
            if (class_exists($class,false)||interface_exists($class,false)) {
                $existing=new \ReflectionClass($class);
                if ($existing->getFileName()!==$file) {
                    return self::result('unavailable','OTHER_FENOM_ALREADY_LOADED');
                }
            }
        }
        $loader=static function($class)use($root){
            if (isset(self::SOURCES[$class])) {
                require_once $root.'/'.str_replace('\\','/',$class).'.php';
            } elseif ($class==='Fenom'||strpos($class,'Fenom\\')===0) {
                throw new \LogicException('Unverified Fenom class.');
            }
        };
        $providerUsed=false;
        $level=ob_get_level();
        ob_start();
        set_error_handler(static function($severity,$message,$file,$line){
            // Older optional Fenom releases may emit PHP deprecations.
            if ($severity===E_DEPRECATED||$severity===E_USER_DEPRECATED) return true;
            throw new \ErrorException('Fenom compiler diagnostic.',0,$severity);
        });
        spl_autoload_register($loader,true,true);
        try {
            // Require only the reviewed library. The anonymous provider
            // acknowledges syntax-only includes without reading any template.
            $provider=new class($providerUsed) implements \Fenom\ProviderInterface {
                private $used;
                public function __construct(&$used){$this->used=&$used;}
                public function templateExists($tpl){return true;}
                public function getSource($tpl,&$time){$this->used=true;throw new \LogicException('Dependency compilation unavailable.');}
                public function getLastModified($tpl){$this->used=true;throw new \LogicException('Dependency metadata unavailable.');}
                public function verify(array $templates){$this->used=true;return false;}
                public function getList(){return array();}
            };
            $fenom=new \Fenom($provider);
            $fenom->setOptions(\Fenom::DISABLE_CACHE|\Fenom::DENY_NATIVE_FUNCS|\Fenom::DENY_PHP_CALLS|\Fenom::DENY_ACCESSOR);
            // Remove every stock accessor: no compile-time accessor handlers.
            foreach (array('get','env','tag','post','request','cookie','session','server','files','globals','const','php','tpl','version','fetch','block','call') as $accessor) {
                $fenom->removeAccessor($accessor);
            }
            foreach (array('chunk','template','file') as $scheme) $fenom->addProvider($scheme,$provider);
            // Inert aliases for pdoTools modifiers, never invoked/rendered.
            foreach (array('chunk','snippet','url','lexicon','config','resource','placeholder','tv','htmlent','html_entity_decode','date','strtotime','number','len','ellipsis','lcase','ucase','isloggedin','haspermission') as $name) {
                $fenom->addModifier($name,static function(){return '';});
            }
            $raw=$fenom->getRawTemplate();
            $raw->source('mcp_syntax_check',$content,true);
            return self::result('syntax_ok','RESTRICTED_COMPILER_ACCEPTED');
        } catch (\Throwable $e) {
            if ($providerUsed || $e instanceof \Fenom\Error\SecurityException) {
                return self::result('inconclusive','DEPENDENCY_OR_POLICY');
            }
            if ($e instanceof \Fenom\Error\CompileException) {
                $message=$e->getMessage();
                $line=max(1,min(substr_count($content,"\n")+1,(int)$e->getLine()));
                // Unknown site extensions are not syntax errors. Never
                // return the compiler's message, token, source or stack.
                if ($e->getPrevious() instanceof \Fenom\Error\UnexpectedTokenException
                    || preg_match('/\A(?:Unclosed tag|Unclosed comment block|Unexpected closing of the tag|Unexpected token )/',$message)) {
                    return self::result('syntax_error','STRUCTURAL_SYNTAX_ERROR',$line);
                }
                return self::result('inconclusive','UNSUPPORTED_OR_UNVERIFIED_SYNTAX',$line);
            }
            return self::result('inconclusive','COMPILER_UNAVAILABLE_OR_FAILED');
        } finally {
            spl_autoload_unregister($loader);
            restore_error_handler();
            while(ob_get_level()>$level){if(!@ob_end_clean())break;}
        }
    }
}
