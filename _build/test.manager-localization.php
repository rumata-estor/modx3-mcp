<?php
/**
 * Dependency graph / capability manager localization regression test.
 * Run: php _build/test.manager-localization.php
 * Requires only PHP, not a bootstrapped MODX site.
 */
$root = dirname(__DIR__);

function checkLocalization($ok, $message) {
    if (!$ok) {
        fwrite(STDERR, "Localization check failed: " . $message . "\n");
        exit(1);
    }
}

function loadManagerLexicon($file) {
    $_lang = array();
    require $file;
    return $_lang;
}

$ru = loadManagerLexicon($root . '/core/components/modxmcp/lexicon/ru/default.inc.php');
$en = loadManagerLexicon($root . '/core/components/modxmcp/lexicon/en/default.inc.php');

$ruKeys = array_keys($ru);
$enKeys = array_keys($en);
sort($ruKeys);
sort($enKeys);
checkLocalization($ruKeys === $enKeys, 'RU and EN lexicon keys differ');

foreach ($en as $key => $value) {
    checkLocalization(isset($ru[$key]) && is_string($value) && $value !== '' && $ru[$key] !== '', 'empty value: ' . $key);
}

$template = file_get_contents($root . '/core/components/modxmcp/templates/graph.tpl');
$graphJs = file_get_contents($root . '/assets/components/modxmcp/js/graph.js');
checkLocalization(!preg_match('/[А-Яа-яЁё]/u', $template), 'Russian text embedded in graph template');

preg_match_all('/\{\$g\.([a-z_]+)\}/', $template, $templateMatches);
preg_match_all('/\bTXT\.([a-z_]+)\b/', $graphJs, $jsMatches);
$used = array_unique(array_merge($templateMatches[1], $jsMatches[1]));
foreach ($used as $name) {
    checkLocalization(isset($en['modxmcp_graph_' . $name]), 'undefined graph label: ' . $name);
}
checkLocalization(count($used) >= 80, 'graph localization keys unexpectedly missing');

foreach (explode("\n", $graphJs) as $line) {
    if (preg_match('/[А-Яа-яЁё]/u', $line)) {
        checkLocalization(strpos(ltrim($line), '//') === 0, 'Russian UI text embedded in graph JavaScript');
    }
}

$capabilityKeys = array_filter($enKeys, function ($key) {
    return strpos($key, 'modxmcp_cmp_cap_') === 0;
});
checkLocalization(count($capabilityKeys) === 10, 'unexpected capability label count');

$variants = array(
    'core/components/modxmcp/controllers/',
    '_build/platform/modx2/overlay/core/components/modxmcp/controllers/',
);
foreach ($variants as $prefix) {
    $index = file_get_contents($root . '/' . $prefix . 'index.class.php');
    $graph = file_get_contents($root . '/' . $prefix . 'graph.class.php');
    foreach ($capabilityKeys as $key) {
        checkLocalization(strpos($index, "lexicon('" . $key . "')") !== false, 'missing capability label: ' . $prefix . $key);
    }
    checkLocalization(strpos($graph, "'ui'            => \$this->graphLabels()") !== false, 'missing JavaScript labels in ' . $prefix);
    checkLocalization(strpos($graph, "'g'            => \$this->graphLabels()") !== false, 'missing template labels in ' . $prefix);
    foreach ($used as $name) {
        checkLocalization(strpos($graph, "'" . $name . "'") !== false, 'missing label in graph controller: ' . $prefix . $name);
    }
}

echo 'OK: ' . count($enKeys) . ' lexicon keys, ' . count($used)
    . ' graph labels, ' . count($capabilityKeys) . " capability labels, MODX 2/3 manager overlays.\n";
