<?php
// Run without bootstrapping MODX: exercise the modular static_file boundary with fake paths.
class ModxMCPClientException extends Exception {}

require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ToolInterface.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/FilesystemSupport.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/ElementMutationSupport.php';
require_once __DIR__ . '/../core/components/modxmcp/src/Tools/SystemSettingCreateTool.php';

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
    echo "SECURITY_HARDENING_PHP_OK\n";
} finally {
    deleteSecurityDirectory($base);
}
