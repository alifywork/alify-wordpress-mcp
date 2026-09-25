=== WP ChatGPT MCP ===
Contributors: alify
Tags: chatgpt, mcp, model context protocol, wordpress, oauth
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later

Connect ChatGPT directly to WordPress through a remote MCP server. No OpenAI API key is used by the plugin.

Developed by ALIFY — https://alify.site/

== Description ==

WP ChatGPT MCP turns WordPress into a remote Model Context Protocol server with:

* Dual-era MCP endpoint: modern 2026-07-28 plus legacy 2025 handshake compatibility
* OAuth authorization discovery
* OAuth Authorization Code + PKCE
* Client ID Metadata Documents (CIMD) with Dynamic Client Registration fallback
* Audience-bound bearer access tokens
* Refresh-token rotation
* WordPress capability checks
* Read/write MCP tool annotations
* Broad WordPress management tools for content, media, taxonomies, comments, users, revisions, menus, site settings and guarded theme-source maintenance
* Persistent MCP-managed custom post type and taxonomy definitions, including archives and rewrite settings
* Guarded WordPress.org plugin and theme installation, activation, update and deletion
* Secure public-URL and native ChatGPT file image uploads with featured-image assignment
* Comprehensive ACF/ACF PRO tools for field groups, fields, location rules, conditional logic, values, nested structures and option pages
* 10 additional product/order/customer tools when WooCommerce is active
* Connection revocation and activity logs

Use HTTPS, keep write tools disabled when they are not required, and review destructive actions before approving them on a production site.

== Installation ==

1. Upload and activate the plugin.
2. Enable pretty permalinks in Settings > Permalinks.
3. Ensure the public site uses HTTPS.
4. Open ChatGPT MCP > Connection and copy the MCP endpoint.
5. In an eligible ChatGPT account/workspace with Developer Mode, create a custom app and provide the endpoint.
6. Choose OAuth when prompted, scan tools, then authorize on WordPress.

== Security ==

* Only WordPress administrators can approve a new OAuth connection.
* OAuth access and refresh tokens are stored as SHA-256 hashes, not plaintext.
* Access tokens expire after one hour.
* Refresh tokens expire after 30 days and rotate on refresh.
* WordPress permissions are checked for each content operation.
* Permanent deletion and dangerous structural operations are guarded and require explicit `confirm=true` where exposed.
* This confirmation requirement is intentional and should not be removed in production.
* Logs do not store raw OAuth tokens, full post bodies, or ACF values.
* Temporary ChatGPT file URLs and file identifiers are not stored in activity logs.
* Theme-source reads are restricted to the active theme and its parent; absolute server paths are never returned.
* Theme-source reads require edit_theme_options; writes additionally require edit_themes, confirm=true, and a matching read-before-write SHA-256.
* Theme-source writes only replace existing PHP, CSS, JavaScript or JSON files, retain up to 20 protected database backups, and respect DISALLOW_FILE_EDIT.
* PHP replacements are parsed before writing and use WordPress core active-theme fatal-error rollback checks.
* Reverse-proxy/CDN client IP headers are ignored by default. Define `WPCMCP_TRUST_PROXY_HEADERS` as true only when your trusted proxy overwrites `CF-Connecting-IP`, `X-Forwarded-For`, or `X-Real-IP`.

== Changelog ==

= 1.7.0 =
* Added WPBakery shortcode-document support with read, enable, element append/prepend and full-document replacement.
* Added Divi support for legacy et_pb shortcode modules plus newer registered Divi block content.
* Added Muffin Builder/BeBuilder support for mfn-page-items with serialized/base64/JSON storage preservation.
* Added custom theme and child-theme creation.
* Added installed-theme file create/read/update/delete tools with safe paths, PHP/JSON validation and SHA-256 conflict protection.
* Kept explicit confirm=true gates for destructive builder/theme operations.

= 1.6.0 =
* Added Elementor-aware MCP tools using Elementor document/widget APIs.
* Added full Elementor document and page-settings reads with SHA-256 fingerprints.
* Added container/widget/raw-element creation, element update, duplicate, move and guarded delete.
* Added whole-document replacement with read-before-write conflict protection.
* Added Elementor page rendering and generated-files/cache clearing.
* Elementor operations preserve Elementor-native document data so pages remain editable in Elementor.

= 1.5.0 =
* Added persistent MCP-managed custom post type and taxonomy definitions, including archives, rewrite slugs, REST visibility, supports and taxonomy connections.
* Added archive inspection and guarded rewrite-rule flushing.
* Added comprehensive ACF/ACF PRO field-group CRUD, duplication, trash/permanent delete, location rules and field-group settings.
* Added ACF field CRUD for installed field types, including nested fields, conditional logic, repeaters, groups and flexible-content structures.
* Added ACF values management for posts, users, terms, comments and options targets with capability checks.
* Added persistent MCP-managed ACF PRO option-page creation, update and deletion without deleting stored option values.
* Permanent deletion and dangerous structural operations require explicit confirm=true.

= 1.4.0 =
* Added automatic database schema/version upgrades on normal plugin updates, not only activation.
* Added daily maintenance for log retention, expired authorization-code cleanup, and abandoned DCR client cleanup.
* Hardened OAuth PKCE validation and made authorization-code consumption and refresh-token rotation atomic to prevent replay races.
* Removed Host-header trust from the OAuth login return URL.
* Added no-store security headers to OAuth REST responses.
* Added checked database writes for authorization codes and tokens with explicit server errors on storage failure.
* Added safe reverse-proxy client-IP support behind the opt-in WPCMCP_TRUST_PROXY_HEADERS constant and wpcmcp_client_ip filter.
* Aligned theme-source read access with edit_theme_options while retaining edit_themes for writes.
* Confirmed JSON theme files against WordPress core editable-file support and retained an explicit JSON fallback.

= 1.3.1 =
* Separated non-mutating theme-source reads from WordPress theme-editing permissions: reads require manage_options, while writes still require manage_options plus edit_themes.
* Added wordpress.get_theme_source_status to report capability checks, DISALLOW_FILE_EDIT, DISALLOW_FILE_MODS, file-mod policy and multisite state without exposing server paths.
* Improved theme-source write permission errors with an actionable diagnostic reference.

= 1.3.0 =
* Added guarded active/parent theme source file listing and reading.
* Added read-before-write SHA-256 protected theme file updates with explicit confirmation.
* Added protected database backup metadata, automatic backup retention and backup restoration.
* Restricted theme source access to existing WordPress-editable PHP, CSS, JavaScript and JSON files.
* Added PHP parse validation, JSON validation and WordPress core fatal-error rollback integration.
* Added capability, path traversal, symlink escape, size and DISALLOW_FILE_EDIT protections.

= 1.2.1 =
* Hardened ChatGPT tool-schema compatibility for ACF archive option-page reads and writes.
* wordpress.update_acf_option now explicitly exposes optional option_page and post_id string parameters.
* wordpress.get_acf_options now explicitly exposes the same archive-target parameters.
* Added archive-specific parameter examples and guidance for fields shared across multiple ACF option pages.

= 1.2.0 =
* Fixed ACF archive/options writes for option pages registered with custom post_id values such as services_archive.
* Added automatic option storage resolution from ACF field-group options_page location rules.
* Added wordpress.list_acf_option_pages for discovering registered menu_slug and post_id mappings.
* Added optional post_id and option_page overrides to ACF option read/write tools with registered-page validation.
* ACF update_field false responses are now treated as successful no-op writes when the persisted value already matches the request.
* ACF option writes now prefer field keys so new option values receive the correct ACF field reference metadata.

= 1.1.0 =
* Added wordpress.upload_media_file with official ChatGPT openai/fileParams metadata.
* ChatGPT can now pass attached, generated or file-library images as temporary authenticated file references.
* Preserved wordpress.upload_media for public HTTPS URL sideloads and backward compatibility.
* Reused WordPress capability, HTTPS/SSRF, upload-size, content MIME and raster-image validation for ChatGPT files.

= 1.0.0 =
* Expanded the authenticated MCP catalog from 35 to 79 core WordPress tools.
* Added media editing/deletion, content restore/permanent deletion and revision management.
* Added generic public-taxonomy term CRUD and content assignment.
* Added comment moderation/CRUD plus guarded user and role administration.
* Added classic navigation menu CRUD, menu locations and an allowlisted site-settings API.
* Added WordPress.org-only plugin/theme lifecycle tools with MCP self-protection.
* Added ACF field-group, bulk/repeater and option-page tools when ACF is active.
* Added privacy-minimized WooCommerce product, order and customer tools when WooCommerce is active.
* Added server-side JSON Schema argument validation for every MCP tool call.
* Added explicit confirmation requirements for permanent and destructive operations.

= 0.3.0 =
* Added wordpress.upload_media for safe public HTTPS raster-image sideloads.
* Added wordpress.set_featured_image for posts, pages and public custom post types.
* Added upload-size, MIME/content, parent-content and per-object capability validation.

= 0.2.0 =
* Added MCP 2026-07-28 stateless server/discover support.
* Added required modern MCP routing headers and request-envelope validation.
* Added cache hints for modern list responses and server identity response metadata.
* Added Client ID Metadata Document (CIMD) OAuth support while retaining DCR fallback.
* Added RFC 9207 issuer parameter to OAuth authorization responses.
* Hardened JSON-RPC validation, authorization consent payload expiry, Origin checks and deterministic tool lists.
* Retained legacy 2025-11-25 / 2025-06-18 / 2025-03-26 initialize compatibility.

= 0.1.0 =
* Initial MCP/OAuth release.
