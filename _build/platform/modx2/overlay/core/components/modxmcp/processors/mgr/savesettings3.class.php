<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Обработчик административной панели MODX: savesettings3.class.
 * EN: MODX Manager controller/processor: savesettings3.class.
 */


/** Strictly allowlisted manager settings. No arbitrary key may be written. */
class ModxmcpSaveSettings3Processor extends modProcessor
{
    public function checkPermissions()
    {
        return $this->modx->user && $this->modx->user->isAuthenticated('mgr')
            && $this->modx->hasPermission('settings');
    }

    public function process()
    {
        $values = array();
        foreach (array('auto_static', 'audit_log') as $name) {
            $raw = $this->getProperty($name, null);
            if ($raw !== '0' && $raw !== '1' && $raw !== 0 && $raw !== 1) {
                return $this->failure('Invalid value for ' . $name);
            }
            $values['modxmcp.' . $name] = (string) $raw;
        }
        $this->modx->beginTransaction();
        try {
            foreach ($values as $key => $value) {
                $setting = $this->modx->getObject('modSystemSetting', array('key' => $key));
                if (!$setting) {
                    throw new \RuntimeException('Setting not found: ' . $key);
                }
                $setting->set('value', $value);
                if (!$setting->save()) {
                    throw new \RuntimeException('Cannot save setting: ' . $key);
                }
            }
            $this->modx->commit();
        } catch (\Throwable $e) {
            $this->modx->rollBack();
            return $this->failure('Could not save MCP settings.');
        }
        $this->modx->reloadConfig();
        if ($this->modx->getCacheManager()) {
            $this->modx->getCacheManager()->refresh();
        }
        $this->modx->logManagerAction('modxmcp_save_settings', 'modSystemSetting', 'modxmcp');
        return $this->success('', array('auto_static' => $values['modxmcp.auto_static'], 'audit_log' => $values['modxmcp.audit_log']));
    }
}

return 'ModxmcpSaveSettings3Processor';
