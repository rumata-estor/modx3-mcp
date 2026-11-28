<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: После полного удаления снимаем устаревшую отметку установки предыдущих версий.
 * EN: Retire superseded installation markers only after complete component removal.
 *
 * This resolver runs near the end of the reverse-order uninstall, after the
 * assets/settings and core file vehicles. It never removes package records,
 * archives, or unrelated data; native Package Management can remove retired
 * records without replaying their obsolete uninstall vehicles.
 */
if (!isset($transport) || !is_object($transport) || !isset($transport->xpdo)) {
    return false;
}
$modx = $transport->xpdo;
$transportClass = class_exists('xPDO\\Transport\\xPDOTransport')
    ? 'xPDO\\Transport\\xPDOTransport' : 'xPDOTransport';
$actionKey = constant($transportClass . '::PACKAGE_ACTION');
if (!isset($options[$actionKey]) || (int)$options[$actionKey] !== constant($transportClass . '::ACTION_UNINSTALL')) {
    return true;
}
$modeKey = constant($transportClass . '::PREEXISTING_MODE');
if (isset($options[$modeKey]) && (int)$options[$modeKey] === constant($transportClass . '::RESTORE_PREEXISTING')) {
    return true;
}
$logError = 3;
$version = $modx->getVersionData();
$is3 = isset($version['version']) && (int)$version['version'] >= 3;
$prefix = $is3 ? 'modx3mcp' : 'modx2mcp';
$ns = $is3 ? 'MODX\\Revolution\\' : '';
$packageClass = $is3 ? $ns . 'Transport\\modTransportPackage' : 'transport.modTransportPackage';
$signature = isset($transport->signature) ? $transport->signature : '';
$pattern = '/\A' . $prefix . '-[0-9]+\.[0-9]+\.[0-9]+-(?:pl|beta|rc|alpha)[0-9]*\z/D';
if (!is_string($signature) || !preg_match($pattern, $signature)) {
    return false;
}
$current = $modx->getObject($packageClass, array('signature' => $signature), false);
if (!$current || $current->get('signature') !== $signature
    || strtolower((string)$current->get('package_name')) !== $prefix
    || !$current->get('installed') || $current->get('installed') === '0000-00-00 00:00:00') {
    return false;
}
// Do not infer that uninstall succeeded merely because a resolver was called.
foreach (array(MODX_CORE_PATH, MODX_ASSETS_PATH) as $root) {
    $path = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'modxmcp';
    clearstatcache(true, $path);
    if (file_exists($path) || is_link($path)) {
        $modx->log($logError, '[modxMCP] Package history unchanged: component files still exist.');
        return false;
    }
}
// xPDO getCount() can return zero on a failed query. Require a successful,
// uncached query before concluding that the component records are absent.
$absent = function ($class, $field, array $criteria) use ($modx) {
    try {
        $query = $modx->newQuery($class, $criteria);
        if (!$query) return false;
        $query->select($modx->escape($field));
        $query->limit(1);
        $stmt = $query->prepare();
        if (!$stmt || !$stmt->execute()) return false;
        $value = $stmt->fetchColumn();
        $ok = $value === false && $stmt->errorCode() === '00000';
        $stmt->closeCursor();
        return $ok;
    } catch (\Throwable $error) {
        return false;
    }
};
if (!$absent($ns . 'modSystemSetting', 'key', array('key:LIKE' => 'modxmcp.%'))
    || !$absent($ns . 'modSystemSetting', 'key', array('namespace' => 'modxmcp'))
    || !$absent($ns . 'modNamespace', 'name', array('name' => 'modxmcp'))
    || !$absent($ns . 'modMenu', 'text', array('text:IN' => array('modxmcp', 'modxmcp_graph')))) {
    $modx->log($logError, '[modxMCP] Package history unchanged: component records still exist or could not be verified.');
    return false;
}
$records = $modx->getCollection($packageClass, array('package_name' => $current->get('package_name')), false);
if (!is_array($records)) return false;
$retire = array();
$foundCurrent = false;
foreach ($records as $record) {
    $old = $record->get('signature');
    $installed = $record->get('installed');
    if ($old === $signature) { $foundCurrent = true; continue; }
    if (!$installed || $installed === '0000-00-00 00:00:00') continue;
    if (strtolower((string)$record->get('package_name')) !== $prefix
        || !is_string($old) || !preg_match($pattern, $old)
        || call_user_func(array($transportClass, 'compareSignature'), $old, $signature) !== -1) {
        // A newer or unfamiliar active record makes the ownership ambiguous.
        $modx->log($logError, '[modxMCP] Package history unchanged: unexpected active version.');
        return false;
    }
    $retire[] = $record;
}
if (!$foundCurrent) return false;
foreach ($retire as $record) {
    $record->set('installed', null);
    if (!$record->save()) {
        $modx->log($logError, '[modxMCP] Could not retire a superseded package installation marker.');
        return false;
    }
}
if ($retire) {
    $modx->log(1, '[modxMCP] Retired ' . count($retire) . ' superseded package installation marker(s); archives retained.');
}
return true;
