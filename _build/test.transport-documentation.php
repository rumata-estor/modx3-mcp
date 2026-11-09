<?php
/** Validate transport README/CHANGELOG links without bootstrapping MODX. */
define('PKG_VERSION', '1.2.5');
require_once __DIR__ . '/transport.documentation.php';

function checkTransportDocs($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "TRANSPORT_DOCS_FAIL: " . $message . "\n");
        exit(1);
    }
}

$root = dirname(__DIR__) . '/';
foreach (array('README.md', 'CHANGELOG.md') as $name) {
    $original = file_get_contents($root . $name);
    $converted = modxmcpTransportDocumentation($original, $name);
    checkTransportDocs($original !== $converted, $name . ': not converted');
    // Every markdown target must now be an absolute URL.
    preg_match_all('~\\]\\(([^)\\s]+)(?:\\s+"[^"]*")?\\)~u', $converted, $found);
    foreach ($found[1] as $target) {
        checkTransportDocs(
            preg_match('~^https?://~i', $target) === 1,
            $name . ': non-absolute link ' . $target
        );
    }
    checkTransportDocs(
        strpos($converted, 'https://github.com/rumata-estor/modx3-mcp/blob/v1.2.5/') !== false,
        $name . ': missing GitHub docs URL'
    );
    checkTransportDocs(
        strpos(file_get_contents($root . $name), 'https://github.com/rumata-estor/modx3-mcp/blob/v1.2.5/README.ru.md') === false,
        $name . ': original Markdown was unexpectedly rewritten'
    );
    echo "TRANSPORT_DOCS_OK " . $name . " links=" . count($found[1]) . "\n";
}

$sample = "[Русский](README.ru.md)\n"
    . "[История](CHANGELOG.ru.md#release)\n"
    . "![graph](docs/images/modxmcp-graph-en.png)\n"
    . "[local](#section) [external](https://example.com/)\n";
$out = modxmcpTransportDocumentation($sample, 'README.md');
checkTransportDocs(
    strpos($out, 'https://github.com/rumata-estor/modx3-mcp/blob/v1.2.5/README.ru.md') !== false,
    'language-switch link'
);
checkTransportDocs(
    strpos($out, 'https://github.com/rumata-estor/modx3-mcp/blob/v1.2.5/CHANGELOG.ru.md#release') !== false,
    'changelog and fragment link'
);
checkTransportDocs(
    strpos($out, 'https://raw.githubusercontent.com/rumata-estor/modx3-mcp/v1.2.5/docs/images/modxmcp-graph-en.png') !== false,
    'image link'
);
checkTransportDocs(
    strpos($out, 'https://github.com/rumata-estor/modx3-mcp/blob/v1.2.5/README.md#section') !== false,
    'internal section link'
);
checkTransportDocs(strpos($out, '[external](https://example.com/)') !== false, 'external URL unchanged');

try {
    modxmcpTransportDocumentation('[escape](../secret.txt)', 'README.md');
    checkTransportDocs(false, 'directory traversal was not rejected');
} catch (RuntimeException $expected) {
    // expected
}
echo "TRANSPORT_DOCS_TEST_OK\n";
