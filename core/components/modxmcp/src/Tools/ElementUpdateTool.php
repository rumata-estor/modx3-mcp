<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Element Update»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Element Update»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class ElementUpdateTool implements ToolInterface
{
    public function name() { return 'update_element'; }
    public function group() { return 'elements'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $type = ElementSupport::type($data);
        ElementMutationSupport::loadLexicons($context);
        $runtimePreconditions = (
            isset($data['_runtime_preconditions'])
            && is_array($data['_runtime_preconditions'])
        ) ? $data['_runtime_preconditions'] : null;
        unset($data['_runtime_preconditions']);
        $prepared = ElementMutationSupport::prepare($context, $type, $data, $this->name());

        if (empty($prepared['id'])) {
            throw new \ModxMCPClientException(
                $type . ' not found by name or ID is missing.'
            );
        }

        if ($type === 'category') {
            CategorySupport::validateParent(
                $context,
                isset($prepared['parent']) ? $prepared['parent'] : (int)CategorySupport::find($context, $prepared['id'])->get('parent'),
                (int)$prepared['id']
            );
        } elseif (in_array($type, array('chunk', 'snippet', 'template', 'tv', 'plugin'), true)
            && array_key_exists('category', $prepared)) {
            $prepared['category'] = CategorySupport::validateElementCategory(
                $context, $type, $prepared['category']
            );
        }

        $current = $context->platform()->runProcessor(
            $context->modx(),
            ElementSupport::processorBase($type) . 'get',
            array('id' => $prepared['id'])
        );
        if (!$current) {
            throw new \ModxMCPClientException('No processor response.');
        }
        if ($current->isError()) {
            throw new \ModxMCPClientException(ProcessorSupport::error($current));
        }

        $updateData = array_merge($current->getObject(), $prepared);
        unset(
            $updateData['events'],
            $updateData['templates'],
            $updateData['input_properties'],
            $updateData['media_source'],
            $updateData['field_type']
        );
        $updateData = ElementMutationSupport::filterData($type, $updateData);

        return ElementMutationSupport::transaction(
            $context,
            function () use (
                $context,
                $type,
                $prepared,
                $updateData,
                $runtimePreconditions
            ) {
                if (is_array($runtimePreconditions)) {
                    StateSupport::assertPreconditions(
                        $context,
                        $runtimePreconditions
                    );
                }
                $id = (int)$prepared['id'];
                $beforeStatic = ElementMutationSupport::staticReference($context, $type, $id);
                $response = ElementMutationSupport::withoutNativeCleanup(
                    $context, $type, function () use ($context, $type, $updateData) {
                        return $context->platform()->runProcessor(
                            $context->modx(), ElementSupport::processorBase($type) . 'update', $updateData
                        );
                    }
                );
                if (!$response) {
                    throw new \ModxMCPClientException('Update failed: no processor response.');
                }
                if ($response->isError()) {
                    throw new \ModxMCPClientException(
                        'Update failed: ' . ProcessorSupport::error($response)
                    );
                }

                if ($type === 'tv') {
                    ElementMutationSupport::handleTvRelations(
                        $context,
                        $id,
                        $prepared
                    );
                }
                if ($type === 'plugin') {
                    ElementMutationSupport::handlePluginEvents(
                        $context,
                        $id,
                        $prepared
                    );
                }
                $afterStatic = ElementMutationSupport::staticReference($context, $type, $id);
                // Never reconvert an already-static element on a content/name update.
                // A deliberate detach is also not silently undone by auto_static.
                if ($afterStatic && !$afterStatic['static'] && !$beforeStatic['static']
                    && ElementMutationSupport::shouldAutoStatic($context, $type, $prepared)) {
                    ElementMutationSupport::makeStatic($context, $type, $id);
                    $afterStatic = ElementMutationSupport::staticReference($context, $type, $id);
                }
                $warning = ElementMutationSupport::staticReferenceWarning($context, $beforeStatic, $afterStatic);

                ElementMutationSupport::refreshCache($context);
                AuditSupport::log(
                    $context,
                    'update_element',
                    $type,
                    array('id' => $id)
                );
                return 'Successfully updated ' . $type . ' (ID: ' . $id . ').' . $warning;
            }
        );
    }
}
