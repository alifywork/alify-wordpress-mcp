# ALIFY WordPress MCP

Production-ready WordPress plugin by [ALIFY](https://alify.site/) for connecting ChatGPT and MCP-compatible AI clients to WordPress through a secure remote Model Context Protocol (MCP) server.

## Current release

**v2.5.0**

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
- WPBakery shortcode-builder document support
- Divi legacy shortcode and newer block-aware builder support
- Muffin Builder / BeBuilder `mfn-page-items` support
- Custom theme and child-theme scaffolding
- Installed theme file create/read/update/delete tools with safe paths and SHA-256 conflict checks
- Universal Plugin Operator for unfamiliar plugins
- Plugin-owned REST-route and shortcode discovery
- Safe installed-plugin source inspection for API/schema discovery
- Generic in-process plugin REST execution with native permission callbacks
- Dedicated Contact Form 7 create/read/update/delete adapter
- Adapter SDK registry with automatic adapter detection
- Natural-language operation planning for installed plugins
- Extensible adapter registration through the `wpcmcp_adapter_registry` filter
- Registered plugin settings discovery and safe settings updates
- Authenticated Admin-AJAX action discovery and execution
- Plugin admin-page discovery
- Universal operation diagnostics and interface prioritization
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

## Additional page builders

### WPBakery

WPBakery content is managed in its native shortcode/post-content model. The connector can read the complete document, enable WPBakery mode, append/prepend registered shortcode elements, and replace the complete builder document with SHA-256 conflict protection.

### Divi

Divi support handles both legacy `et_pb_*` shortcode content and newer block-aware content. The connector can read complete Divi content, enable Divi Builder, append registered legacy modules, append registered Divi blocks, and replace the complete builder document with conflict protection.

### Muffin Builder / BeBuilder

BeTheme builder data is read from `mfn-page-items`. The connector detects serialized, base64-serialized and JSON-style storage, preserves the detected encoding on writes, and can replace the complete builder document, append sections, update indexed builder items and delete indexed items with confirmation.

## Custom theme development

The connector can now create a minimal custom theme or child theme and safely manage installed-theme source files.

Supported operations include:

- Create custom theme scaffolds
- Create child themes from installed parent themes
- Read files from installed themes
- Create new theme files without overwriting existing files
- Update existing theme files using SHA-256 read-before-write protection
- Delete non-critical theme files with SHA-256 protection
- Validate PHP syntax and JSON before writes
- Respect WordPress file-editing restrictions and native capabilities

Critical bootstrap files such as `style.css`, `index.php`, and `functions.php` are not deletable through the MCP file-delete tool.

## Universal Plugin Operator

The long-term goal of the plugin is natural-language WordPress operation:

**install plugin → activate plugin → discover its capabilities → operate it**

For unfamiliar plugins, GPT can now:

- inspect installed plugin metadata and activation state
- discover REST routes whose callbacks belong to that plugin
- discover shortcodes registered by that plugin
- list/read safe plugin source files to understand its public/internal API surface
- invoke registered non-core plugin REST routes in-process as the authenticated WordPress user
- rely on the target plugin's own REST permission callbacks

The generic REST operator intentionally blocks WordPress core and this MCP plugin's own routes; those operations should use dedicated MCP tools instead. Any generic mutating REST call requires `confirm=true`.

Plugins that expose no usable REST API/CPT/shortcode/public PHP API may still need a dedicated adapter. The architecture is designed so adapters can be added without changing the natural-language workflow.

## Contact Form 7 adapter

When Contact Form 7 is active, GPT can list/read forms and create/update/delete forms through CF7's native APIs. This supports requests such as:

> Install Contact Form 7, activate it, create a form called “Project Enquiry”, configure its fields and mail settings, then give me the shortcode.

CF7's current save flow exposes `wpcf7_save_contact_form()`, which the adapter uses rather than writing CF7 storage directly.

## Adapter SDK & Auto Registry

The connector now includes an adapter registry so GPT can decide how to operate a plugin instead of hard-coding that decision into the prompt.

Typical flow:

1. Install the requested plugin with `wordpress.install_plugin`.
2. Activate it with `wordpress.activate_plugin`.
3. Detect the best adapter with `wordpress.adapter_registry_detect`.
4. Use `wordpress.adapter_registry_plan` to map the user's intent to the dedicated adapter when available.
5. If no dedicated adapter exists, fall back to `wordpress.plugin_operator_discover` and inspect plugin REST routes, shortcodes, registered content models and safe source files.

Built-in registry entries currently cover Contact Form 7, Elementor, WPBakery, Divi, Muffin/BeBuilder, WooCommerce and ACF/ACF PRO.

Developers can extend the registry with the `wpcmcp_adapter_registry` filter without modifying the core plugin.

## Universal Operator Core v2

Unknown-plugin operation no longer depends on REST alone.

The operator can now inspect and use these interfaces in priority order:

1. dedicated MCP adapter
2. plugin-owned REST API
3. WordPress registered settings
4. authenticated `wp_ajax_*` actions
5. registered CPTs/taxonomies/shortcodes/public APIs
6. safe source inspection to understand the plugin and design a dedicated adapter

New v2 tools include:

- `wordpress.plugin_operator_list_settings`
- `wordpress.plugin_operator_get_setting`
- `wordpress.plugin_operator_update_setting`
- `wordpress.plugin_operator_list_ajax_actions`
- `wordpress.plugin_operator_call_ajax`
- `wordpress.plugin_operator_list_admin_pages`
- `wordpress.plugin_operator_diagnose`

Generic settings access is deliberately restricted to registered settings attributable to the target plugin. Credential/secret-looking options are blocked from the generic settings operator.

Admin-AJAX execution is limited to authenticated `wp_ajax_*` callbacks attributed to the selected plugin. Plugin nonce and capability checks are not bypassed.

## Transaction, rollback & dry-run

The MCP now includes a generic transaction-safety layer for multi-step workflows:

- create rollback snapshots for selected posts and non-secret options
- preview post/option diffs without writing
- inspect recent snapshots
- restore captured state with `confirm=true`
- delete old snapshots explicitly

This is designed for workflows such as plugin configuration + page edits + builder edits where GPT should establish a recovery point before making changes.

## Cron, webhooks & post-change verification

The MCP can now inspect WP-Cron, run/schedule/unschedule known hooks with confirmation, manage signed outgoing HTTPS webhooks for allowlisted site events, and perform lightweight site-health verification after changes.

Supported webhook events currently include:

- `save_post`
- `user_register`
- `comment_post`
- `woocommerce_order_status_changed`

Webhook deliveries are signed with an HMAC derived from the site's WordPress auth salt. Signing keys are not exposed through MCP.

## Permission profiles

Built-in profiles now control tool exposure on top of OAuth scopes and WordPress capabilities:

- `read_only`
- `content_manager`
- `developer`
- `full_admin`

Profiles never grant WordPress capabilities; they only further restrict which MCP tools are exposed.

## Database diagnostics

Safe database diagnostics are now available without exposing arbitrary SQL execution:

- WordPress-prefixed table inventory and approximate sizes
- table schema/index inspection
- heuristic plugin-table discovery
- autoload-size report without returning option values
- orphan metadata counts
- core expired-transient cleanup with confirmation

## Workflow orchestration

The MCP can validate and execute up to 25 existing MCP tool calls as one sequential workflow.

A workflow can optionally create a rollback snapshot first. When a later step returns an error, the transaction engine can automatically restore the snapshot.

This enables requests such as:

> Install and activate a form plugin, create a form, edit a builder page, add the form, update settings, then verify the site.

Every nested tool still keeps its own capability checks and confirmation requirements.

## Write-only secret vault

API keys and credentials can now be stored encrypted server-side using AES-256-GCM when OpenSSL is available.

Secret values are never returned by read tools. GPT can:

- store/update a named secret
- list only secret names and metadata
- check whether a secret exists
- delete a secret
- apply a secret directly to an explicit WordPress option without receiving the plaintext back

Encryption keys are derived from WordPress authentication salts and the site URL; raw encryption keys are not stored in the database.

## Learned adapter manifests

For plugins without a native PHP adapter, GPT can store a declarative capability manifest.

The manifest records discovered:

- REST routes
- registered settings
- authenticated Admin-AJAX actions
- shortcodes
- post-type hints
- taxonomy hints
- capability labels and operator notes

`wordpress.adapter_manifest_learn` can build and save this manifest automatically from the plugin's live registration state.

Saved manifests are injected back into the adapter registry, so later sessions can recognize the plugin without repeating full discovery. Relearning after a major plugin upgrade refreshes the capability map.

## Common plugin operator profiles

The adapter registry now pre-recognizes these additional ecosystems and routes them into the Universal Operator + learned-manifest workflow when no native adapter is present:

- WPForms
- Fluent Forms
- Gravity Forms
- Forminator
- Ninja Forms
- Rank Math
- Yoast SEO
- SEOPress
- WP Mail SMTP
- FluentSMTP
- LearnDash
- Tutor LMS
- MemberPress

These are operator profiles, not a claim that every private plugin API is stable or fully covered. The connector still inspects each installed version's live REST/settings/AJAX/shortcode/content-model surface before mutating it.

### MCP connection compatibility

v2.4.1 bounds `tools/list` responses with MCP cursor pagination (50 tools per page by default) so large WordPress installations do not return one oversized discovery payload during connector setup.

The OAuth layer also exposes explicit REST metadata endpoints in addition to the well-known documents, improving diagnostics and compatibility for WordPress installations hosted in subdirectories.

## Connection-first OAuth model

v2.5.0 allows MCP connection discovery before OAuth. `initialize`, `ping`, and `tools/list` can complete without an access token, while every listed tool declares an OAuth `securitySchemes` policy.

When ChatGPT invokes a protected tool without a token, the server returns `_meta["mcp/www_authenticate"]` with the protected-resource metadata URL. This lets ChatGPT add the server first and launch the WordPress OAuth consent flow when authentication is actually needed.

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
