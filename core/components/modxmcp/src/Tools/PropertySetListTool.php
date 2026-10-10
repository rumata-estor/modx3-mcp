<?php
namespace ModxMcp\Tools;

class PropertySetListTool implements ToolInterface
{
    public function name() { return 'list_property_sets'; }
    public function group() { return 'property_sets'; }
    public function isMutation() { return false; }
    public function supports($context) { return $context && $context->modx() && $context->platform(); }

    public function execute($context, array $data)
    {
        $modx = $context->modx();
        $class = $context->platform()->className('property_set');
        $query = $modx->newQuery($class);
        if (!empty($data['query'])) {
            $query->where(array('name:LIKE' => '%' . $data['query'] . '%'));
        }
        $total = (int)$modx->getCount($class, $query);
        $query->sortby('name', 'ASC');
        $limit = array_key_exists('limit', $data) ? max(1, min((int)$data['limit'], 500)) : 100;
        $start = !empty($data['start']) ? max(0, (int)$data['start']) : 0;
        $query->limit($limit, $start);
        $rows = array();
        foreach ($modx->getCollection($class, $query) as $propertySet) {
            $rows[] = array(
                'id' => (int)$propertySet->get('id'),
                'name' => $propertySet->get('name'),
                'description' => $propertySet->get('description'),
                'category' => (int)$propertySet->get('category'),
            );
        }
        return array('total' => $total, 'results' => $rows);
    }
}
