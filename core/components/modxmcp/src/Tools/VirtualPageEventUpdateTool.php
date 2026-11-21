<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Virtual Page Event Update»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Virtual Page Event Update»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class VirtualPageEventUpdateTool implements ToolInterface
{
    public function name() { return 'virtualpage_update_event'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx(); }

    public function execute($context, array $data)
    {
        $event = VirtualPageSupport::resolveObject($context, 'vpEvent', $data);
        $payload = VirtualPageSupport::preparePayload(
            $data,
            array('name', 'description', 'rank', 'active')
        );
        if (empty($payload)) {
            throw new \ModxMCPClientException(
                'No VirtualPage event fields to update.'
            );
        }
        if (!empty($payload['name'])) {
            $duplicate = $context->modx()->getObject(
                'vpEvent',
                array('name' => $payload['name'])
            );
            if ($duplicate
                && (int)$duplicate->get('id') !== (int)$event->get('id')) {
                throw new \ModxMCPClientException(
                    'VirtualPage event already exists: '
                    . $payload['name'] . '.'
                );
            }
        }

        $event->fromArray($payload, '', true, true);
        if (!$event->save()) {
            throw new \ModxMCPClientException(
                'Could not update VirtualPage event.'
            );
        }

        VirtualPageSupport::ensurePluginEvent($context, $event->get('name'));
        VirtualPageSupport::clearCache($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'virtualpage_event',
            array('id' => $event->get('id'))
        );
        return VirtualPageSupport::normalizeEvent($context, $event, true);
    }
}
