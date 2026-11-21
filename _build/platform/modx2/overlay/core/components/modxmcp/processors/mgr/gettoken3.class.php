<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Обработчик административной панели MODX: gettoken3.class.
 * EN: MODX Manager controller/processor: gettoken3.class.
 */


/** Fetch the active token on explicit manager request only. Never embed it in page HTML. */
class ModxmcpGetToken3Processor extends modProcessor
{
    public function checkPermissions()
    {
        return $this->modx->user && $this->modx->user->isAuthenticated('mgr')
            && $this->modx->hasPermission('settings');
    }

    public function process()
    {
        header('Cache-Control: no-store, private');
        $token = (string) $this->modx->getOption('modxmcp.api_token', null, '');
        if ($token === '') {
            return $this->failure('No API token configured.');
        }
        return $this->success('', array('token' => $token));
    }
}

return 'ModxmcpGetToken3Processor';
