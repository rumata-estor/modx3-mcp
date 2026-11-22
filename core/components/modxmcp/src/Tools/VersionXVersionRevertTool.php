<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Инструмент MCP «Version X Version Revert»: выполняется через защищённый диспетчер.
 * EN: MCP tool «Version X Version Revert»: invoked through the guarded dispatcher.
 */
namespace ModxMcp\Tools;

class VersionXVersionRevertTool implements ToolInterface
{
    public function name() { return 'versionx_revert_version'; }
    public function group() { return 'versionx'; }
    public function isMutation() { return true; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $meta = VersionXSupport::mutationMeta($context, $data);
        $contentId = VersionXSupport::positiveInt($data, 'content_id');
        $versionId = VersionXSupport::positiveInt($data, 'version_id');

        if (empty($data['confirm'])) {
            throw new \ModxMCPClientException(
                'confirm=true is required to revert a VersionX version.'
            );
        }

        VersionXSupport::load($context);
        $modx = $context->modx();
        $before = $modx->getObject($meta['content_class'], $contentId);
        $version = $modx->getObject($meta['class'], array(
            'content_id' => $contentId,
            'version_id' => $versionId,
        ));
        if (!$version) {
            throw new \ModxMCPClientException(
                'VersionX version not found: ' . $data['type']
                . ' content_id=' . $contentId
                . ' version_id=' . $versionId . '.'
            );
        }

        // RU: С этого момента процессор мог уже изменить ресурс.
        // EN: Once native revert starts, the outcome can be partial or unknown.
        try {
        $response = $modx->runProcessor(
            $meta['processor'] . '/revert',
            array(
                'content_id' => $contentId,
                'version_id' => $versionId,
            ),
            array(
                'processors_path' => VersionXSupport::corePath($context)
                    . 'processors/mgr/',
            )
        );
        if (!$response || $response->isError()) {
            // RU: Даже ответ с isError() не доказывает отсутствие отката.
            // EN: An error response cannot establish that revert had no effect.
            throw new \RuntimeException('native VersionX processor response unsuccessful');
        }

        $after = $modx->getObject($meta['content_class'], $contentId);
        AuditSupport::log(
            $context,
            $this->name(),
            (string)$data['type'],
            array(
                'content_id' => $contentId,
                'version_id' => $versionId,
            )
        );

        return array(
            'reverted' => true,
            'type' => $data['type'],
            'content_id' => $contentId,
            'version_id' => $versionId,
            'version' => VersionXSupport::normalize($version, $meta, false),
            'before_exists' => (bool)$before,
            'after_exists' => (bool)$after,
            'after' => $after ? array(
                'id' => $after->get('id'),
                'class_key' => $after->get('class_key'),
                'name' => $this->label($after),
            ) : null,
        );
        } catch (\Throwable $error) {
            // RU: Не сообщаем "отменено" и не повторяем изменяющий процессор.
            // RU: Текст стороннего исключения не может попасть к клиенту.
            // EN: Never infer rollback, replay automatically or leak addon errors.
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: VersionX revert may have changed '
                . 'the target; read its current state through MCP before '
                . 'requesting another explicitly authorized mutation.'
            );
        }
    }

    private function label($object)
    {
        foreach (array('pagetitle', 'name', 'templatename', 'caption') as $field) {
            $value = $object->get($field);
            if ($value !== null && $value !== '') { return $value; }
        }
        return '';
    }
}
