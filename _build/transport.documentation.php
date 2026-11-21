<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Служебный сценарий сборки/проверки MODX MCP: transport.documentation.
 * EN: MODX MCP build/verification helper: transport.documentation.
 */
/**
 * Convert repository-relative Markdown links to absolute GitHub URLs ONLY when
 * embedding README/CHANGELOG in MODX transport package metadata.
 *
 * GitHub .md files remain untouched. This is necessary because the MODX
 * package installer renders package metadata under the manager's own URL:
 * e.g. (CHANGELOG.ru.md) would otherwise navigate to /manager/CHANGELOG.ru.md.
 *
 * Pin documented links to a release tag when PKG_VERSION is defined by the
 * builder, so a later main-branch change cannot silently alter old releases.
 * Without a release version, local standalone tests use main.
 * Never embed site URLs, tokens or local filesystem paths.
 */
function modxmcpTransportDocumentation($markdown, $documentPath)
{
    $version = defined('PKG_VERSION') ? (string) PKG_VERSION : '';
    $ref = preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $version) ? 'v' . $version : 'main';
    $base = 'https://github.com/rumata-estor/modx3-mcp/blob/' . $ref . '/';
    $raw = 'https://raw.githubusercontent.com/rumata-estor/modx3-mcp/' . $ref . '/';

    return preg_replace_callback(
        '~\\]\\(([^)\\s]+)(\\s+"[^"]*")?\\)~u',
        function ($match) use ($base, $raw, $documentPath) {
            $destination = trim($match[1], '<>');
            // Preserve valid external destinations; don't rewrite email links.
            if (preg_match('~^(?:[a-z][a-z0-9+.-]*:|//)~i', $destination)) {
                return $match[0];
            }

            // Separate fragments / query strings from the repository file path.
            $fragment = '';
            $pos = strpos($destination, '#');
            if ($pos !== false) {
                $fragment = substr($destination, $pos);
                $destination = substr($destination, 0, $pos);
            }
            $query = '';
            $pos = strpos($destination, '?');
            if ($pos !== false) {
                $query = substr($destination, $pos);
                $destination = substr($destination, 0, $pos);
            }
            $isImage = ($match[0] !== '' && preg_match('~\\.(?:png|jpe?g|gif|svg|webp)$~i', $destination));
            $documentDir = dirname(str_replace('\\', '/', (string) $documentPath));
            $parts = array();
            $relative = ($documentDir === '.' ? '' : $documentDir . '/') . $destination;
            foreach (explode('/', ltrim($relative, '/')) as $part) {
                if ($part === '' || $part === '.') { continue; }
                if ($part === '..') {
                    if (count($parts) === 0) {
                        // Reject a link trying to escape repository root.
                        throw new RuntimeException('Transport document link escapes repository root');
                    }
                    array_pop($parts);
                    continue;
                }
                $parts[] = $part;
            }
            $file = implode('/', $parts);
            if ($file === '') {
                $file = ltrim((string) $documentPath, '/');
            }
            $href = ($isImage ? $raw : $base) . $file . $query . $fragment;
            $title = isset($match[2]) ? $match[2] : '';
            return '](' . $href . $title . ')';
        },
        (string) $markdown
    );
}
