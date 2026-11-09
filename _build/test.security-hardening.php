<?php
// Run without bootstrapping MODX: exercise the modular static_file boundary with fake paths.
class ModxMCPClientException extends Exception {}

require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ToolInterface.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ElementSupport.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ElementMutationSupport.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ElementContentSupport.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/SystemSettingCreateTool.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/SystemSettingListTool.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ProcessorMutationTool.php';

class SecurityTestModx {
    private $paths;
    public function __construct($base) {
        $this->paths = array(
            'core_path' => $base . '/core/',
            'base_path' => $base . '/',
            'assets_path' => $base . '/assets/',
        );
    }
    public function getOption($name) {
        return isset($this->paths[$name]) ? $this->paths[$name] : '';
    }
}
class SecurityTestContext {
    private $modx;
    public function __construct($modx) { $this->modx = $modx; }
    public function modx() { return $this->modx; }
}
function mustRejectSecurity($label, $callback) {
    try { call_user_func($callback); }
    catch (ModxMCPClientException $exception) { return; }
    throw new RuntimeException('Expected rejection: ' . $label);
}
function deleteSecurityDirectory($path) {
    if (!is_dir($path)) { return; }
    foreach (new FilesystemIterator($path) as $item) {
        if ($item->isLink() || $item->isFile()) {
            unlink($item->getPathname());
        } elseif ($item->isDir()) {
            deleteSecurityDirectory($item->getPathname());
        }
    }
    rmdir($path);
}

$base = sys_get_temp_dir() . '/modxmcp-security-' . bin2hex(random_bytes(6));
$root = $base . '/core/elements';
$outside = $base . '/private';
if (!mkdir($root, 0700, true) || !mkdir($outside, 0700, true)) {
    throw new RuntimeException('Failed to prepare temporary test directories');
}
file_put_contents($outside . '/secret.php', '<?php echo "secret";');
$context = new SecurityTestContext(new SecurityTestModx($base));

try {
    $valid = '{core_path}elements/my-snippet.php';
    if (\ModxMcp\Tools\ElementMutationSupport::validateStaticFile($context, $valid) !== $valid) {
        throw new RuntimeException('Valid static path was not preserved');
    }
    $nested = '{core_path}elements/new-directory/my-snippet.php';
    if (\ModxMcp\Tools\ElementMutationSupport::validateStaticFile($context, $nested) !== $nested) {
        throw new RuntimeException('Valid new nested path was not preserved');
    }
    foreach (array(
        '../core/elements/hack.php',
        '/etc/passwd',
        '{core_path}config/config.inc.php',
        '{base_path}assets/private.php',
    ) as $invalid) {
        mustRejectSecurity($invalid, function () use ($context, $invalid) {
            \ModxMcp\Tools\ElementMutationSupport::validateStaticFile($context, $invalid);
        });
    }
    if (!symlink($outside, $root . '/escape')
        || !symlink($outside . '/secret.php', $root . '/linked.php')) {
        throw new RuntimeException('Failed to create symlink fixtures');
    }
    foreach (array(
        '{core_path}elements/escape/would-write.php',
        '{core_path}elements/linked.php',
    ) as $invalid) {
        mustRejectSecurity('symlink: ' . $invalid, function () use ($context, $invalid) {
            \ModxMcp\Tools\ElementMutationSupport::validateStaticFile($context, $invalid);
        });
    }
    mustRejectSecurity('reserved key creation', function () use ($context) {
        (new \ModxMcp\Tools\SystemSettingCreateTool())->execute(
            $context, array('key' => 'modxmcp.require_https', 'value' => 0)
        );
    });
    mustRejectSecurity('reserved context setting creation', function () use ($context) {
        (new \ModxMcp\Tools\ProcessorMutationTool(
            'create_context_setting',
            array('group' => 'contexts', 'processor' => 'context/setting/create', 'route' => 'context')
        ))->execute($context, array('context_key' => 'mgr', 'key' => 'modxmcp.enabled', 'value' => 1));
    });
    mustRejectSecurity('reserved context setting update', function () use ($context) {
        (new \ModxMcp\Tools\ProcessorMutationTool(
            'update_context_setting',
            array('group' => 'contexts', 'processor' => 'context/setting/update', 'route' => 'context')
        ))->execute($context, array('context_key' => 'web', 'key' => 'modxmcp.allowed_ips', 'value' => ''));
    });
    mustRejectSecurity('reserved context setting creation whitespace bypass', function () use ($context) {
        (new \ModxMcp\Tools\ProcessorMutationTool(
            'create_context_setting',
            array('group' => 'contexts', 'processor' => 'context/setting/create', 'route' => 'context')
        ))->execute($context, array('context_key' => 'mgr', 'key' => '   modxmcp.enabled  ', 'value' => 1));
    });
    mustRejectSecurity('reserved context setting update case bypass', function () use ($context) {
        (new \ModxMcp\Tools\ProcessorMutationTool(
            'update_context_setting',
            array('group' => 'contexts', 'processor' => 'context/setting/update', 'route' => 'context')
        ))->execute($context, array('context_key' => 'web', 'key' => 'ModxMCP.allowed_ips', 'value' => ''));
    });
    mustRejectSecurity('tv static_file traversal', function () use ($context) {
        \ModxMcp\Tools\ElementMutationSupport::prepare(
            $context, 'tv', array('id' => 1, 'static' => 1, 'static_file' => '/etc/passwd'), 'update_element'
        );
    });

    // Verify token masking in system setting normalization
    class MockSettingObject {
        private $data;
        public function __construct(array $data) { $this->data = $data; }
        public function get($k) { return isset($this->data[$k]) ? $this->data[$k] : null; }
    }
    $norm = \ModxMcp\Tools\SystemSettingListTool::normalize(new MockSettingObject(array(
        'id' => 1, 'key' => 'modxmcp.api_token', 'value' => 'abcdef1234567890abcdef', 'xtype' => 'textfield', 'namespace' => 'modxmcp', 'area' => 'security'
    )));
    if ($norm['value'] !== 'abcdef…cdef') {
        throw new RuntimeException('API token was not properly masked: ' . $norm['value']);
    }

    echo "SECURITY_HARDENING_PHP_OK\n";
} finally {
    deleteSecurityDirectory($base);
}
