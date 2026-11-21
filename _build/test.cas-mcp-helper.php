<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Проверка «cas-mcp-helper»; тестовый код не запускается на production.
 * EN: «cas-mcp-helper» regression test; not executed in production.
 */
/**
 * Disposable CI fixture helper. Prepares a CAS snapshot from the MCP read
 * interface for existing positive and fault-injection integration tests.
 *
 * NOT included in component, client or production releases. A real client
 * must preserve the user's observed snapshot rather than refreshing it
 * automatically at write time.
 */
function disposableCasRpc($api, $action, array $data = array())
{
    static $mutations = null;
    if ($mutations === null) {
        $inventory = json_decode(
            file_get_contents(__DIR__.'/cas-mutation-inventory.json'),
            true
        );
        if (!is_array($inventory) || !isset($inventory['actions'])) {
            throw new \RuntimeException('CAS test inventory missing');
        }
        $mutations = array_fill_keys(array_keys($inventory['actions']), true);
    }
    if (isset($mutations[$action]) && !array_key_exists('_runtime_preconditions',$data)) {
        $observed = $api->processRequest('get_site_state', '', array());
        $guard = array(
            'observed_site_revision' => (int)$observed['site_revision'],
            'strict_site_revision' => true,
            'read_set' => array(),
        );
        if ($action === 'update_resource_tvs') {
            if (empty($data['resource_id']) || !isset($data['tvs']) || !is_array($data['tvs'])) {
                throw new \RuntimeException('Cannot build disposable test TV CAS');
            }
            $read = $api->processRequest('get_resource_tvs', '', array(
                'resource_id' => (int)$data['resource_id'],
            ));
            $map = array();
            foreach ($read['tvs'] as $tv) {
                if (isset($tv['name'])) {
                    $map[(string)$tv['name']] = (string)$tv['value'];
                }
            }
            $expected = array();
            foreach ($data['tvs'] as $name => $value) {
                $expected[$name] = isset($map[$name]) ? $map[$name] : '';
            }
            $guard['expected_tvs'] = $expected;
        }
        $data['_runtime_preconditions'] = $guard;
    }
    return $api->processRequest($action, '', $data);
}
