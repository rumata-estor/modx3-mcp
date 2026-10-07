<?php
namespace ModxMcp\Tools;

/**
 * Create or update a lexicon DB override through MODX's native
 * update-from-grid processor. Unlike workspace/lexicon/create this is
 * intentionally idempotent for an existing namespace/topic/language/name key.
 */
class LexiconEntrySetTool implements ToolInterface
{
    public function name() { return 'set_lexicon_entry'; }
    public function group() { return 'lexicon'; }
    public function isMutation() { return true; }
    public function supports($context)
    {
        return $context && $context->modx() && $context->platform();
    }

    public function execute($context, array $data)
    {
        foreach (array('name', 'namespace', 'topic') as $required) {
            if (!isset($data[$required]) || trim((string)$data[$required]) === '') {
                throw new \ModxMCPClientException(
                    'set_lexicon_entry: ' . $required . ' is required.'
                );
            }
        }

        $entry = array(
            'name' => (string)$data['name'],
            'value' => isset($data['value']) ? (string)$data['value'] : '',
            'namespace' => (string)$data['namespace'],
            'topic' => (string)$data['topic'],
            'language' => !empty($data['language'])
                ? (string)$data['language']
                : 'en',
        );

        $result = ProcessorSupport::run(
            $context,
            'workspace/lexicon/updatefromgrid',
            array('data' => json_encode($entry)),
            false,
            array('core:default', 'core:workspaces', 'core:lexicon')
        );

        AuditSupport::log(
            $context,
            $this->name(),
            'workspace',
            array_intersect_key(
                $entry,
                array_flip(array('name', 'namespace', 'topic', 'language'))
            )
        );
        return $result;
    }
}
