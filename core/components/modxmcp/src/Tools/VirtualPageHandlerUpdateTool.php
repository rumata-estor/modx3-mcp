<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Virtual Page Handler Update»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Virtual Page Handler Update»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class VirtualPageHandlerUpdateTool implements ToolInterface
{
    public function name() { return 'virtualpage_update_handler'; }
    public function group() { return 'virtualpage'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $handler = VirtualPageSupport::resolveObject(
            $context,
            'vpHandler',
            $data
        );
        $payload = VirtualPageSupport::preparePayload(
            $data,
            array(
                'name', 'type', 'entry', 'content', 'description',
                'cache', 'rank', 'active',
            )
        );
        if (empty($payload)) {
            throw new \ModxMCPClientException(
                'No VirtualPage handler fields to update.'
            );
        }
        if (!empty($payload['name'])) {
            $duplicate = $context->modx()->getObject(
                'vpHandler',
                array('name' => $payload['name'])
            );
            if ($duplicate
                && (int)$duplicate->get('id') !== (int)$handler->get('id')) {
                throw new \ModxMCPClientException(
                    'VirtualPage handler already exists: '
                    . $payload['name'] . '.'
                );
            }
        }

        if (array_key_exists('type', $payload)) {
            $payload['type'] = VirtualPageSupport::normalizeHandlerType(
                $payload['type']
            );
        }
        $type = array_key_exists('type', $payload)
            ? $payload['type']
            : (int)$handler->get('type');
        $entry = array_key_exists('entry', $payload)
            ? $payload['entry']
            : $handler->get('entry');
        VirtualPageSupport::assertHandlerEntry($context, $type, $entry);

        $handler->fromArray($payload, '', true, true);
        if (!$handler->save()) {
            throw new \ModxMCPClientException(
                'Could not update VirtualPage handler.'
            );
        }

        VirtualPageSupport::clearCache($context);
        AuditSupport::log(
            $context,
            $this->name(),
            'virtualpage_handler',
            array('id' => $handler->get('id'))
        );
        return VirtualPageSupport::normalizeHandler($context, $handler, true);
    }
}
