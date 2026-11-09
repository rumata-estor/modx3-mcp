<?php
namespace ModxMcp\Tools;

/** Shared MODX 2/3 modCategory operations; never confuses element categories with resources. */
class CategorySupport
{
    private static function categoryClass($context)
    {
        return $context->platform()->className('category');
    }

    public static function find($context, $id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new \ModxMCPClientException('A positive modCategory id is required.');
        }
        $item = $context->modx()->getObject(self::categoryClass($context), $id);
        if (!$item) {
            throw new \ModxMCPClientException('MODX element category ' . $id . ' not found.');
        }
        return $item;
    }

    public static function validateParent($context, $parent, $movingId = 0)
    {
        $parent = (int)$parent;
        if ($parent < 0) {
            throw new \ModxMCPClientException('Category parent must be zero or a valid category id.');
        }
        $seen = array();
        while ($parent > 0) {
            if ($parent === (int)$movingId || isset($seen[$parent])) {
                throw new \ModxMCPClientException('Cannot move a category into itself or its descendants.');
            }
            $seen[$parent] = true;
            $category = self::find($context, $parent);
            $parent = (int)$category->get('parent');
        }
    }

    public static function validateElementCategory($context, $type, $categoryId)
    {
        if (!in_array($type, array('chunk', 'snippet', 'template', 'tv', 'plugin'), true)) {
            throw new \ModxMCPClientException('Only chunks, snippets, templates, TVs and plugins use modCategory.');
        }
        if (!is_numeric($categoryId) || (int)$categoryId < 0
            || (string)(int)$categoryId !== (string)$categoryId) {
            throw new \ModxMCPClientException('category_id must be an integer (0 for uncategorized).');
        }
        $id = (int)$categoryId;
        if ($id > 0) {
            self::find($context, $id);
        }
        return $id;
    }

    public static function usages($context, $id)
    {
        $modx = $context->modx();
        $categoryClass = self::categoryClass($context);
        $counts = array(
            'child_categories' => (int)$modx->getCount($categoryClass, array('parent' => (int)$id)),
        );
        foreach (array('chunk', 'snippet', 'template', 'tv', 'plugin') as $type) {
            $counts[$type] = (int)$modx->getCount(
                $context->platform()->className($type),
                array('category' => (int)$id)
            );
        }
        // Property sets may also be organized under modCategory.
        $counts['property_sets'] = (int)$modx->getCount(
            $context->platform()->className('property_set'),
            array('category' => (int)$id)
        );
        return $counts;
    }

    public static function row($category)
    {
        return array(
            'id' => (int)$category->get('id'),
            'name' => (string)$category->get('category'),
            'parent' => (int)$category->get('parent'),
            'rank' => (int)$category->get('rank'),
        );
    }

    public static function listing($context, array $data)
    {
        $modx = $context->modx();
        $class = self::categoryClass($context);
        $query = $modx->newQuery($class);
        if (array_key_exists('parent', $data)) {
            $parent = (int)$data['parent'];
            if ($parent < 0) { throw new \ModxMCPClientException('Invalid parent.'); }
            $query->where(array('parent' => $parent));
        }
        if (isset($data['query']) && trim((string)$data['query']) !== '') {
            $query->where(array('category:LIKE' => '%' . trim((string)$data['query']) . '%'));
        }
        $total = (int)$modx->getCount($class, $query);
        $limit = isset($data['limit']) ? max(1, min(500, (int)$data['limit'])) : 100;
        $start = isset($data['start']) ? max(0, (int)$data['start']) : 0;
        $query->sortby('parent', 'ASC');
        $query->sortby('rank', 'ASC');
        $query->sortby('category', 'ASC');
        $query->limit($limit, $start);
        $rows = array();
        foreach ($modx->getCollection($class, $query) as $category) {
            $rows[] = self::row($category);
        }
        return array('total' => $total, 'results' => $rows, 'limit' => $limit, 'start' => $start);
    }

    public static function get($context, array $data)
    {
        if (empty($data['id'])) {
            throw new \ModxMCPClientException('get_category: id is required.');
        }
        $category = self::find($context, $data['id']);
        $row = self::row($category);
        $row['usages'] = self::usages($context, $row['id']);
        return $row;
    }

    private static function processor($context, $action, array $payload)
    {
        ElementMutationSupport::loadLexicons($context);
        $response = $context->platform()->runProcessor(
            $context->modx(), 'element/category/' . $action, $payload
        );
        if (!$response || $response->isError()) {
            throw new \ModxMCPClientException(
                $response ? ProcessorSupport::error($response) : 'Category processor returned no response.'
            );
        }
        return $response;
    }

    public static function create($context, array $data)
    {
        $name = isset($data['name']) ? trim((string)$data['name']) : '';
        if ($name === '') {
            throw new \ModxMCPClientException('create_category: non-empty name is required.');
        }
        $parent = isset($data['parent']) ? (int)$data['parent'] : 0;
        self::validateParent($context, $parent);
        $payload = array('category' => $name, 'parent' => $parent);
        if (isset($data['rank'])) { $payload['rank'] = (int)$data['rank']; }
        $response = self::processor($context, 'create', $payload);
        ElementMutationSupport::refreshCache($context);
        $result = $response->getObject();
        $id = isset($result['id']) ? (int)$result['id'] : 0;
        AuditSupport::log($context, 'create_category', 'category', array('id' => $id, 'name' => $name));
        return array('id' => $id, 'name' => $name, 'parent' => $parent);
    }

    public static function update($context, array $data)
    {
        $category = self::find($context, isset($data['id']) ? $data['id'] : 0);
        $id = (int)$category->get('id');
        $payload = self::row($category);
        $payload['category'] = $payload['name'];
        unset($payload['name']);
        if (array_key_exists('name', $data)) {
            $name = trim((string)$data['name']);
            if ($name === '') { throw new \ModxMCPClientException('Category name cannot be empty.'); }
            $payload['category'] = $name;
        }
        if (array_key_exists('parent', $data)) {
            $payload['parent'] = (int)$data['parent'];
        }
        self::validateParent($context, $payload['parent'], $id);
        if (array_key_exists('rank', $data)) {
            $payload['rank'] = (int)$data['rank'];
        }
        self::processor($context, 'update', $payload);
        ElementMutationSupport::refreshCache($context);
        AuditSupport::log($context, 'update_category', 'category', array('id' => $id));
        return self::row(self::find($context, $id));
    }

    public static function delete($context, array $data)
    {
        $category = self::find($context, isset($data['id']) ? $data['id'] : 0);
        $id = (int)$category->get('id');
        $usages = self::usages($context, $id);
        $blocked = array_sum($usages) > 0;
        $preview = array(
            'id' => $id, 'name' => (string)$category->get('category'),
            'dry_run' => true, 'can_delete' => !$blocked,
            'usages' => $usages,
        );
        if (!array_key_exists('dry_run', $data)) {
            throw new \ModxMCPClientException('delete_category: specify dry_run:true or dry_run:false explicitly.');
        }
        if ($data['dry_run'] !== false) { return $preview; }
        if ($blocked) {
            throw new \ModxMCPClientException(
                'Category is not empty: move or unassign children and elements first. Run dry_run to inspect usages.'
            );
        }
        self::processor($context, 'remove', array('id' => $id));
        ElementMutationSupport::refreshCache($context);
        AuditSupport::log($context, 'delete_category', 'category', array('id' => $id));
        return array('deleted' => true, 'id' => $id, 'name' => $preview['name']);
    }

    public static function assignElement($context, array $data)
    {
        $type = isset($data['type']) ? (string)$data['type'] : '';
        if (!array_key_exists('category_id', $data) || !isset($data['id'])) {
            throw new \ModxMCPClientException('assign_element_category requires type, id, category_id.');
        }
        $categoryId = self::validateElementCategory($context, $type, $data['category_id']);
        $id = (int)$data['id'];
        if ($id <= 0) { throw new \ModxMCPClientException('Positive element id required.'); }
        $element = $context->modx()->getObject($context->platform()->className($type), $id);
        if (!$element) { throw new \ModxMCPClientException('Element not found.'); }
        $previous = (int)$element->get('category');
        if ($previous === $categoryId) {
            return array('id' => $id, 'type' => $type, 'category_id' => $categoryId, 'status' => 'unchanged');
        }
        // Change only the category, never rewrite element code or static files.
        $element->set('category', $categoryId);
        if (!$element->save()) {
            throw new \ModxMCPClientException('Category assignment save failed.');
        }
        ElementMutationSupport::refreshCache($context);
        $updated = $context->modx()->getObject($context->platform()->className($type), $id);
        if (!$updated || (int)$updated->get('category') !== $categoryId) {
            throw new \ModxMCPClientException('Category assignment not persisted.');
        }
        AuditSupport::log($context, 'assign_element_category', $type, array(
            'id' => $id, 'from' => $previous, 'to' => $categoryId,
        ));
        return array(
            'id' => $id, 'type' => $type,
            'previous_category_id' => $previous, 'category_id' => $categoryId,
            'status' => 'assigned',
        );
    }
}
