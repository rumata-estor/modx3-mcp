# MODX element categories

These commands manage MODX element categories (modCategory), not miniShop2 categories or the resource tree. MODX Revolution 2.8.x and 3.x are supported.

- modx_list_categories: list id, name, parent and rank; optional parent=0 filters top-level categories.
- modx_get_category: read a category including counts of child categories, assigned elements and property sets.
- modx_create_category: name, optional parent and rank.
- modx_update_category: id and optional name, parent and rank. A category cannot be moved under itself or a descendant.
- modx_assign_element_category: type, id, category_id. Type is chunk, snippet, template, tv, or plugin. category_id=0 means uncategorized. Assignment does not rewrite element code.
- modx_delete_category: first call with dry_run=true to preview dependencies, then dry_run=false to delete if empty. Nonempty categories cannot be deleted.

The generic element create, update and delete operations accept type=category, with the same safety checks. For other elements, the generic create/update operations accept a numeric category identifier. Ordinary MODX resources have no modCategory field; they use the resource parent hierarchy.
