# WP ChatGPT MCP 1.6.0 — Tool Catalog

Tools are exposed only when the matching OAuth scope and the **Read tools** or **Write tools** setting is enabled. Every operation also checks the authenticated WordPress user's native capabilities.

## Site and content

- `wordpress.site_info`
- `wordpress.get_site_info`
- `wordpress.get_wordpress_version`
- `wordpress.list_post_types`
- `wordpress.search_content`
- `wordpress.get_content`
- `wordpress.create_content`
- `wordpress.update_content`
- `wordpress.trash_content`
- `wordpress.restore_content`
- `wordpress.delete_content_permanently`
- `wordpress.list_posts`
- `wordpress.get_post`
- `wordpress.create_post`
- `wordpress.update_post`
- `wordpress.delete_post`
- `wordpress.list_pages`
- `wordpress.get_page`
- `wordpress.create_page`
- `wordpress.update_page`
- `wordpress.delete_page`

## Media

- `wordpress.list_media`
- `wordpress.get_media`
- `wordpress.upload_media`
- `wordpress.upload_media_file`
- `wordpress.update_media`
- `wordpress.set_featured_image`
- `wordpress.delete_media`

## Taxonomies

- `wordpress.list_taxonomies`
- `wordpress.list_terms`
- `wordpress.get_term`
- `wordpress.create_term`
- `wordpress.update_term`
- `wordpress.delete_term`
- `wordpress.assign_terms`
- `wordpress.list_categories`
- `wordpress.list_tags`

## Comments

- `wordpress.list_comments`
- `wordpress.get_comment`
- `wordpress.create_comment`
- `wordpress.update_comment`
- `wordpress.moderate_comment`
- `wordpress.delete_comment`

## Users and roles

- `wordpress.list_users`
- `wordpress.get_user`
- `wordpress.list_roles`
- `wordpress.create_user`
- `wordpress.update_user`
- `wordpress.set_user_roles`
- `wordpress.delete_user`

## Revisions

- `wordpress.list_revisions`
- `wordpress.get_revision`
- `wordpress.restore_revision`
- `wordpress.delete_revision`

## Navigation and settings

- `wordpress.list_menus`
- `wordpress.get_menu`
- `wordpress.create_menu`
- `wordpress.update_menu`
- `wordpress.delete_menu`
- `wordpress.create_menu_item`
- `wordpress.update_menu_item`
- `wordpress.delete_menu_item`
- `wordpress.assign_menu_location`
- `wordpress.get_site_settings`
- `wordpress.update_site_settings`

## Plugins and themes

- `wordpress.list_plugins`
- `wordpress.get_plugin_status`
- `wordpress.install_plugin`
- `wordpress.activate_plugin`
- `wordpress.deactivate_plugin`
- `wordpress.update_plugin`
- `wordpress.delete_plugin`
- `wordpress.get_active_theme`
- `wordpress.list_themes`
- `wordpress.install_theme`
- `wordpress.activate_theme`
- `wordpress.update_theme`
- `wordpress.delete_theme`
- `wordpress.get_theme_source_status`
- `wordpress.list_theme_files`
- `wordpress.read_theme_file`
- `wordpress.update_theme_file`
- `wordpress.list_theme_file_backups`
- `wordpress.restore_theme_file_backup`

Plugin/theme installation accepts exact WordPress.org slugs only. Arbitrary package URLs are intentionally unsupported. The MCP plugin cannot deactivate, update, or delete itself through MCP.

Theme-source access is limited to existing editable files in the active theme and its parent. Writes require a matching SHA-256 from a prior read, `confirm=true`, administrator theme-editing capabilities, protected backup creation and WordPress core validation/rollback checks.

## ACF integration

The first two tools remain discoverable and report when ACF is unavailable. The other six appear only when the corresponding ACF functions are active.

- `wordpress.get_acf_fields`
- `wordpress.update_acf_field`
- `wordpress.list_acf_field_groups`
- `wordpress.get_acf_field_group`
- `wordpress.list_acf_option_pages`
- `wordpress.update_acf_fields`
- `wordpress.get_acf_options` (`fields`, plus optional `option_page` or `post_id`; explicit field names required and bulk option dumps are blocked)
- `wordpress.update_acf_option` (`field`, `value`, plus optional `option_page` or `post_id`)

## Structural management

- `wordpress.get_post_type_definition`
- `wordpress.get_taxonomy_definition`
- `wordpress.get_archive_info`
- `wordpress.save_post_type_definition`
- `wordpress.delete_post_type_definition`
- `wordpress.save_taxonomy_definition`
- `wordpress.delete_taxonomy_definition`
- `wordpress.flush_rewrite_rules`

MCP-managed custom post types can control archive enablement, archive/rewrite slugs, REST visibility, hierarchy, supports, capability type and taxonomy connections. MCP-managed taxonomies can control hierarchy, rewrite behavior, REST visibility, admin columns and connected post types.

## ACF PRO structure management

- `wordpress.acf_status`
- `wordpress.acf_list_field_groups`
- `wordpress.acf_get_field_group`
- `wordpress.acf_list_fields`
- `wordpress.acf_get_field`
- `wordpress.acf_create_field_group`
- `wordpress.acf_update_field_group`
- `wordpress.acf_duplicate_field_group`
- `wordpress.acf_trash_field_group`
- `wordpress.acf_delete_field_group`
- `wordpress.acf_create_field`
- `wordpress.acf_update_field`
- `wordpress.acf_duplicate_field`
- `wordpress.acf_trash_field`
- `wordpress.acf_delete_field`
- `wordpress.acf_set_location_rules`
- `wordpress.acf_set_conditional_logic`
- `wordpress.acf_get_values`
- `wordpress.acf_update_values`
- `wordpress.acf_delete_value`
- `wordpress.acf_list_option_pages_managed`
- `wordpress.acf_save_option_page`
- `wordpress.acf_delete_option_page`

Permanent ACF deletion and destructive structural operations require `confirm=true`.

## Elementor builder

These tools appear only when Elementor is active.

- `wordpress.elementor_status`
- `wordpress.elementor_get_document`
- `wordpress.elementor_get_element`
- `wordpress.elementor_list_widgets`
- `wordpress.elementor_render_document`
- `wordpress.elementor_enable_document`
- `wordpress.elementor_replace_document`
- `wordpress.elementor_add_container`
- `wordpress.elementor_add_widget`
- `wordpress.elementor_add_element`
- `wordpress.elementor_update_element`
- `wordpress.elementor_duplicate_element`
- `wordpress.elementor_move_element`
- `wordpress.elementor_delete_element`
- `wordpress.elementor_update_page_settings`
- `wordpress.elementor_clear_cache`

Elementor data is managed through Elementor's document/widget APIs. Whole-document replacement requires the latest SHA-256 returned by `wordpress.elementor_get_document` plus `confirm=true`. Element deletion and cache clearing also require explicit confirmation.

## WooCommerce integration

These ten tools appear only when WooCommerce is active.

- `woocommerce.list_products`
- `woocommerce.get_product`
- `woocommerce.create_product`
- `woocommerce.update_product`
- `woocommerce.delete_product`
- `woocommerce.list_orders`
- `woocommerce.get_order`
- `woocommerce.update_order_status`
- `woocommerce.list_customers`
- `woocommerce.get_customer`

WooCommerce order/customer responses intentionally omit emails, phone numbers, street addresses, and payment credentials.

## Safety boundaries

- Raw SQL, arbitrary option access, arbitrary filesystem operations, arbitrary PHP execution, and arbitrary plugin/theme package URLs are not exposed.
- Permanent deletion requires `confirm=true`.
- The active OAuth user cannot delete themselves or change their own roles.
- HTTPS media downloads are SSRF-checked, size-limited, and MIME-validated.
- MCP arguments are validated against each tool's JSON Schema before execution.
