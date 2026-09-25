# ALIFY WordPress MCP

Production-ready WordPress plugin by [ALIFY](https://alify.site/) for connecting ChatGPT and MCP-compatible AI clients to WordPress through a secure remote Model Context Protocol (MCP) server.

## Current release

**v1.4.0**

## Highlights

- OAuth Authorization Code + PKCE
- Audience-bound access tokens and refresh-token rotation
- WordPress capability checks for every operation
- MCP tools for WordPress content, media, taxonomies, comments, revisions, menus, settings and guarded theme-source maintenance
- Optional ACF and WooCommerce toolsets
- Automatic database migrations and maintenance
- Security-focused theme source read/write controls
- Reverse-proxy support with explicit trusted-proxy opt-in

## Requirements

- WordPress 6.4+
- PHP 7.4+
- HTTPS recommended for production
- Pretty permalinks enabled

## Installation

1. Download the release ZIP.
2. In WordPress go to **Plugins → Add New → Upload Plugin**.
3. Upload and activate the plugin.
4. Open **ChatGPT MCP → Connection**.
5. Copy the MCP endpoint.
6. Add the endpoint to an MCP-compatible client and authorize with WordPress.

## Documentation

See `readme.txt` and `TOOL-CATALOG.md` for full plugin and tool documentation.

## Security

Use HTTPS in production and only enable write tools when required. Theme-source writes require WordPress capabilities, explicit confirmation and read-before-write integrity checks.

## Branding & support

Developed by **ALIFY**  
https://alify.site/

## License

GPL-2.0-or-later.
