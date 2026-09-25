# ALIFY WordPress MCP

Production-ready WordPress plugin by [ALIFY](https://alify.site/) for connecting ChatGPT and MCP-compatible AI clients to WordPress through a secure remote Model Context Protocol (MCP) server.

## Current release

**v1.6.0**

## Highlights

- OAuth Authorization Code + PKCE
- Audience-bound access tokens and refresh-token rotation
- WordPress capability checks for every operation
- Posts, pages, custom post types, archives, taxonomies, media, comments, revisions, users, menus and settings
- Persistent MCP-managed custom post type and taxonomy definitions
- Archive/rewrite configuration and guarded rewrite-rule flushing
- Full ACF/ACF PRO field-group and field management
- ACF location rules, conditional logic, nested fields, repeaters, groups and flexible content
- ACF values for posts, users, terms, comments and option-page targets
- Persistent MCP-managed ACF PRO option pages
- Elementor builder integration using Elementor's document/widget APIs
- Read complete Elementor element trees and page settings
- Add/update/move/duplicate/delete Elementor containers and widgets
- Replace complete Elementor documents with SHA-256 conflict protection
- Render Elementor document HTML for inspection
- Optional WooCommerce product/order/customer tools
- Guarded plugin, theme and theme-source maintenance
- Automatic database migrations and maintenance
- Reverse-proxy support with explicit trusted-proxy opt-in

## Requirements

- WordPress 6.4+
- PHP 7.4+
- HTTPS recommended for production
- Pretty permalinks enabled
- ACF/ACF PRO only for ACF-specific tools
- WooCommerce only for WooCommerce-specific tools

## Installation

1. Download the production ZIP.
2. In WordPress go to **Plugins → Add New → Upload Plugin**.
3. Upload and activate the plugin.
4. Open **ChatGPT MCP → Connection**.
5. Copy the MCP endpoint.
6. Add the endpoint to an MCP-compatible client and authorize with WordPress.

## Elementor builder support

When Elementor is active, the MCP connector exposes Elementor-aware tools instead of treating an Elementor page as ordinary post content only.

Supported operations include:

- Inspect Elementor document metadata, page settings and the full recursive element tree
- List registered Elementor widgets available on the site
- Enable Elementor for a post/page/CPT
- Add containers and widgets
- Add raw validated Elementor elements for advanced/nested structures
- Update widget/container settings
- Duplicate and move elements
- Delete elements with explicit confirmation
- Replace a complete Elementor document with read-before-write SHA-256 conflict protection
- Update Elementor page/document settings
- Render the current Elementor page HTML for inspection
- Clear generated Elementor files/cache with confirmation

The connector does not simulate mouse clicks inside the browser editor. It works directly with Elementor's own document model and APIs, so saved changes remain Elementor-native and editable in the Elementor UI.

## Destructive-operation safety

The plugin intentionally keeps a confirmation gate for permanent deletion and dangerous structural changes.

**Permanent delete and dangerous structural operations require `confirm=true`.**

This applies to operations such as permanent content/media/comment/user deletion, ACF field or field-group permanent deletion, deleting MCP-managed CPT/taxonomy/ACF option-page definitions, destructive plugin/theme actions, and guarded rewrite/theme-source operations where confirmation is required.

This safeguard is deliberate and should not be removed in a production installation. Native WordPress capability checks are also enforced server-side.

## Documentation

See `readme.txt` and `TOOL-CATALOG.md` for the full plugin/tool catalog and safety boundaries.

## Security

Use HTTPS in production and only enable write tools when required. Theme-source writes require WordPress capabilities, explicit confirmation, a matching read-before-write SHA-256, protected backup creation and WordPress core validation/rollback checks.

## Branding & support

Developed by **ALIFY**  
https://alify.site/

## License

GPL-2.0-or-later.
