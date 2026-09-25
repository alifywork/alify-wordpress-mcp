<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Theme_Source_Tools {
    const MAX_READ_BYTES  = 1048576;
    const MAX_WRITE_BYTES = 524288;
    const MAX_FILES       = 500;
    const MAX_BACKUPS     = 20;
    const BACKUP_OPTION   = 'wpcmcp_theme_source_backups';

    public static function definitions( $can_read, $can_write ) {
        $tools = array();
        $theme_target = array(
            'type'        => 'string',
            'enum'        => array( 'active', 'parent' ),
            'description' => 'Theme target. Defaults to active; parent selects the active theme parent when present.',
        );

        if ( $can_read ) {
            $tools[] = self::t(
                'wordpress.get_theme_source_status',
                'Get Theme Source Access Status',
                'Report the current MCP user capabilities and WordPress file-editing policy that control theme-source reads and writes. No server paths or source content are returned.',
                array(),
                array(),
                true,
                false,
                true
            );
            $tools[] = self::t(
                'wordpress.list_theme_files',
                'List Active Theme Source Files',
                'List editable PHP, CSS, JavaScript, and JSON files in the active theme or its parent. Absolute server paths are never returned.',
                array( 'theme' => $theme_target ),
                array(),
                true,
                false,
                true
            );
            $tools[] = self::t(
                'wordpress.read_theme_file',
                'Read Active Theme Source File',
                'Read one existing editable source file from the active theme or its parent. Returns a SHA-256 value required for safe updates.',
                array(
                    'theme' => $theme_target,
                    'path'  => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 512, 'description' => 'Relative theme file path, for example inc/setup.php.' ),
                ),
                array( 'path' ),
                true,
                false,
                true
            );
            $tools[] = self::t(
                'wordpress.list_theme_file_backups',
                'List Theme Source Backups',
                'List metadata for recent protected theme-file backups created before MCP writes. Backup contents are not returned.',
                array(),
                array(),
                true,
                false,
                true
            );
        }

        if ( $can_write ) {
            $tools[] = self::t(
                'wordpress.update_theme_file',
                'Update Active Theme Source File',
                'Replace one existing source file in the active theme or its parent. Requires read-before-write SHA-256 concurrency protection and confirm=true. A protected rollback backup is created first; PHP is parsed before writing and WordPress core performs active-theme fatal-error rollback checks.',
                array(
                    'theme'           => $theme_target,
                    'path'            => array( 'type' => 'string', 'minLength' => 1, 'maxLength' => 512, 'description' => 'Relative path returned by wordpress.read_theme_file.' ),
                    'content'         => array( 'type' => 'string', 'maxLength' => self::MAX_WRITE_BYTES, 'description' => 'Complete replacement file content.' ),
                    'expected_sha256' => array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64, 'description' => 'Exact SHA-256 returned by the most recent read of this file.' ),
                    'confirm'         => array( 'type' => 'boolean', 'description' => 'Must be true.' ),
                ),
                array( 'path', 'content', 'expected_sha256', 'confirm' ),
                false,
                true,
                true
            );
            $tools[] = self::t(
                'wordpress.restore_theme_file_backup',
                'Restore Theme Source Backup',
                'Restore a protected MCP theme-file backup. The current file must be read first and its SHA-256 supplied. Requires confirm=true and creates another backup before restoring.',
                array(
                    'backup_id'      => array( 'type' => 'string', 'minLength' => 36, 'maxLength' => 36 ),
                    'expected_sha256'=> array( 'type' => 'string', 'minLength' => 64, 'maxLength' => 64, 'description' => 'Current SHA-256 of the target file.' ),
                    'confirm'        => array( 'type' => 'boolean', 'description' => 'Must be true.' ),
                ),
                array( 'backup_id', 'expected_sha256', 'confirm' ),
                false,
                true,
                true
            );
        }

        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.get_theme_source_status': return self::get_theme_source_status();
            case 'wordpress.list_theme_files': return self::list_theme_files( $args );
            case 'wordpress.read_theme_file': return self::read_theme_file( $args );
            case 'wordpress.list_theme_file_backups': return self::list_theme_file_backups();
            case 'wordpress.update_theme_file': return self::update_theme_file( $args );
            case 'wordpress.restore_theme_file_backup': return self::restore_theme_file_backup( $args );
            default: return null;
        }
    }

    private static function load_file_admin() {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    private static function can_read_source() {
        return current_user_can( 'edit_theme_options' );
    }

    private static function can_write_source() {
        return self::can_read_source() && current_user_can( 'edit_themes' );
    }

    private static function file_editing_allowed() {
        if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) return false;
        if ( function_exists( 'wp_is_file_mod_allowed' ) && ! wp_is_file_mod_allowed( 'file_editor' ) ) return false;
        return true;
    }

    private static function get_theme_source_status() {
        if ( ! is_user_logged_in() ) return new WP_Error( 'forbidden', 'Theme source access status requires an authenticated WordPress user.' );
        $user = wp_get_current_user();
        $manage_options = current_user_can( 'manage_options' );
        $edit_theme_options = current_user_can( 'edit_theme_options' );
        $edit_themes = current_user_can( 'edit_themes' );
        $disallow_file_edit = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
        $disallow_file_mods = defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS;
        $file_mod_allowed = function_exists( 'wp_is_file_mod_allowed' ) ? wp_is_file_mod_allowed( 'file_editor' ) : ! $disallow_file_mods;
        $reasons = array();
        if ( ! $edit_theme_options ) $reasons[] = 'The connected user does not have edit_theme_options.';
        if ( ! $edit_themes ) $reasons[] = 'The connected user does not currently pass the edit_themes capability check.';
        if ( $disallow_file_edit ) $reasons[] = 'DISALLOW_FILE_EDIT is enabled.';
        if ( $disallow_file_mods ) $reasons[] = 'DISALLOW_FILE_MODS is enabled.';
        if ( ! $file_mod_allowed ) $reasons[] = 'WordPress wp_is_file_mod_allowed(file_editor) returned false.';
        return array(
            'user_id'                    => (int) $user->ID,
            'roles'                      => array_values( (array) $user->roles ),
            'manage_options'             => $manage_options,
            'edit_theme_options'          => $edit_theme_options,
            'edit_themes'                => $edit_themes,
            'disallow_file_edit_defined' => defined( 'DISALLOW_FILE_EDIT' ),
            'disallow_file_edit'         => (bool) $disallow_file_edit,
            'disallow_file_mods_defined' => defined( 'DISALLOW_FILE_MODS' ),
            'disallow_file_mods'         => (bool) $disallow_file_mods,
            'file_mod_allowed'           => (bool) $file_mod_allowed,
            'multisite'                  => is_multisite(),
            'read_allowed'               => $edit_theme_options,
            'write_capability_allowed'   => $edit_theme_options && $edit_themes,
            'write_allowed'              => $edit_theme_options && $edit_themes && ! $disallow_file_edit && $file_mod_allowed,
            'blocked_reasons'            => $reasons,
        );
    }

    private static function write_permission_error() {
        if ( ! self::can_read_source() ) return new WP_Error( 'forbidden', 'Theme source writes require the WordPress edit_theme_options capability.' );
        if ( ! current_user_can( 'edit_themes' ) ) return new WP_Error( 'forbidden', 'Theme source writes require the WordPress edit_themes capability. Use wordpress.get_theme_source_status for the exact policy state.' );
        return new WP_Error( 'forbidden', 'Theme source writes are not allowed for the connected user.' );
    }

    private static function resolve_theme( $target ) {
        $target = 'parent' === $target ? 'parent' : 'active';
        $stylesheet = 'parent' === $target ? get_template() : get_stylesheet();
        $theme = wp_get_theme( $stylesheet );
        if ( ! $theme->exists() ) return new WP_Error( 'theme_not_found', 'The active theme target could not be resolved.' );
        if ( $theme->errors() ) return new WP_Error( 'theme_error', $theme->errors()->get_error_message() );
        $directory = realpath( $theme->get_stylesheet_directory() );
        if ( false === $directory || ! is_dir( $directory ) ) return new WP_Error( 'theme_directory_missing', 'The theme source directory is unavailable.' );
        return array(
            'target'     => $target,
            'stylesheet' => $theme->get_stylesheet(),
            'name'       => $theme->get( 'Name' ),
            'directory'  => wp_normalize_path( $directory ),
            'theme'      => $theme,
        );
    }

    private static function allowed_extensions( $theme ) {
        self::load_file_admin();
        $core = function_exists( 'wp_get_theme_file_editable_extensions' ) ? wp_get_theme_file_editable_extensions( $theme ) : array( 'php', 'css', 'js', 'json' );
        return array_values( array_intersect( array( 'php', 'css', 'js', 'json' ), array_map( 'strtolower', (array) $core ) ) );
    }

    private static function normalize_path( $path ) {
        if ( ! is_string( $path ) ) return new WP_Error( 'invalid_theme_path', 'Theme file path must be a string.' );
        $path = trim( wp_normalize_path( $path ) );
        if ( '' === $path || '/' === substr( $path, 0, 1 ) || false !== strpos( $path, "\0" ) || 0 !== validate_file( $path ) ) {
            return new WP_Error( 'invalid_theme_path', 'A safe relative theme file path is required.' );
        }
        $path = preg_replace( '#/+#', '/', $path );
        foreach ( explode( '/', $path ) as $segment ) {
            if ( '' === $segment || '.' === substr( $segment, 0, 1 ) ) return new WP_Error( 'invalid_theme_path', 'Hidden and dot-path theme files are not accessible.' );
        }
        return $path;
    }

    private static function resolve_file( array $theme, $path ) {
        $path = self::normalize_path( $path );
        if ( is_wp_error( $path ) ) return $path;
        $extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        if ( ! in_array( $extension, self::allowed_extensions( $theme['theme'] ), true ) ) {
            return new WP_Error( 'unsupported_theme_file', 'Only WordPress-editable PHP, CSS, JavaScript, and JSON theme files are supported.' );
        }
        $candidate = realpath( $theme['directory'] . '/' . $path );
        if ( false === $candidate || ! is_file( $candidate ) ) return new WP_Error( 'theme_file_not_found', 'The requested existing theme file was not found.' );
        $candidate = wp_normalize_path( $candidate );
        $root = trailingslashit( $theme['directory'] );
        if ( 0 !== strpos( $candidate, $root ) ) return new WP_Error( 'theme_path_escape', 'The requested file resolves outside the allowed theme directory.' );
        return array( 'path' => $path, 'absolute' => $candidate, 'extension' => $extension );
    }

    private static function file_metadata( array $theme, array $file, $include_content = false ) {
        $size = wp_filesize( $file['absolute'] );
        if ( false === $size ) return new WP_Error( 'theme_file_unreadable', 'WordPress could not determine the theme file size.' );
        if ( $size > self::MAX_READ_BYTES ) return new WP_Error( 'theme_file_too_large', 'Theme source files larger than 1 MB cannot be read through MCP.' );
        $content = file_get_contents( $file['absolute'] );
        if ( false === $content ) return new WP_Error( 'theme_file_unreadable', 'WordPress could not read the theme file.' );
        $result = array(
            'theme'        => $theme['target'],
            'stylesheet'   => $theme['stylesheet'],
            'theme_name'   => $theme['name'],
            'path'         => $file['path'],
            'extension'    => $file['extension'],
            'size_bytes'   => strlen( $content ),
            'sha256'       => hash( 'sha256', $content ),
            'modified_gmt' => gmdate( 'c', (int) filemtime( $file['absolute'] ) ),
        );
        if ( $include_content ) $result['content'] = $content;
        return $result;
    }

    private static function list_theme_files( array $args ) {
        if ( ! self::can_read_source() ) return new WP_Error( 'forbidden', 'Theme source reads require the WordPress edit_theme_options capability.' );
        $theme = self::resolve_theme( isset( $args['theme'] ) ? $args['theme'] : 'active' );
        if ( is_wp_error( $theme ) ) return $theme;
        self::load_file_admin();
        $paths = list_files( $theme['directory'], 20, array( '.git', 'node_modules', 'vendor' ), false );
        if ( false === $paths ) return new WP_Error( 'theme_files_unavailable', 'WordPress could not list theme source files.' );
        $allowed = self::allowed_extensions( $theme['theme'] );
        $items = array();
        foreach ( $paths as $absolute ) {
            $listed = wp_normalize_path( $absolute );
            $absolute = realpath( $absolute );
            if ( false === $absolute || ! is_file( $absolute ) ) continue;
            $absolute = wp_normalize_path( $absolute );
            if ( 0 !== strpos( $absolute, trailingslashit( $theme['directory'] ) ) || 0 !== strpos( $listed, trailingslashit( $theme['directory'] ) ) ) continue;
            $relative = ltrim( substr( $listed, strlen( $theme['directory'] ) ), '/' );
            $extension = strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) );
            if ( ! in_array( $extension, $allowed, true ) ) continue;
            $items[] = array( 'path' => $relative, 'extension' => $extension, 'size_bytes' => (int) wp_filesize( $absolute ) );
        }
        usort( $items, static function ( $a, $b ) { return strcmp( $a['path'], $b['path'] ); } );
        $total = count( $items );
        if ( $total > self::MAX_FILES ) $items = array_slice( $items, 0, self::MAX_FILES );
        return array(
            'theme'       => $theme['target'],
            'stylesheet'  => $theme['stylesheet'],
            'theme_name'  => $theme['name'],
            'files'       => $items,
            'total'       => $total,
            'truncated'   => $total > self::MAX_FILES,
            'extensions'  => $allowed,
        );
    }

    private static function read_theme_file( array $args ) {
        if ( ! self::can_read_source() ) return new WP_Error( 'forbidden', 'Theme source reads require the WordPress edit_theme_options capability.' );
        $theme = self::resolve_theme( isset( $args['theme'] ) ? $args['theme'] : 'active' );
        if ( is_wp_error( $theme ) ) return $theme;
        $file = self::resolve_file( $theme, isset( $args['path'] ) ? $args['path'] : '' );
        if ( is_wp_error( $file ) ) return $file;
        return self::file_metadata( $theme, $file, true );
    }

    private static function backups() {
        $backups = get_option( self::BACKUP_OPTION, array() );
        return is_array( $backups ) ? $backups : array();
    }

    private static function save_backup( array $theme, array $file, $content, $reason ) {
        $backups = self::backups();
        $id = wp_generate_uuid4();
        $backups[] = array(
            'id'          => $id,
            'created_gmt' => gmdate( 'c' ),
            'created_by'  => get_current_user_id(),
            'reason'      => sanitize_key( $reason ),
            'stylesheet'  => $theme['stylesheet'],
            'theme_name'  => $theme['name'],
            'path'        => $file['path'],
            'extension'   => $file['extension'],
            'size_bytes'  => strlen( $content ),
            'sha256'      => hash( 'sha256', $content ),
            'content'     => $content,
        );
        if ( count( $backups ) > self::MAX_BACKUPS ) $backups = array_slice( $backups, -self::MAX_BACKUPS );
        $saved = update_option( self::BACKUP_OPTION, $backups, false );
        if ( ! $saved ) {
            $persisted = self::backups();
            $found = false;
            foreach ( $persisted as $backup ) {
                if ( isset( $backup['id'] ) && hash_equals( $id, (string) $backup['id'] ) ) {
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) return new WP_Error( 'theme_backup_failed', 'The protected theme source backup could not be saved; the file was not changed.' );
        }
        return $id;
    }

    private static function list_theme_file_backups() {
        if ( ! self::can_read_source() ) return new WP_Error( 'forbidden', 'Theme source backup reads require the WordPress edit_theme_options capability.' );
        $items = array();
        foreach ( array_reverse( self::backups() ) as $backup ) {
            $item = $backup;
            unset( $item['content'] );
            $items[] = $item;
        }
        return array( 'backups' => $items, 'retention_limit' => self::MAX_BACKUPS );
    }

    private static function validate_new_content( $content, $extension ) {
        if ( ! is_string( $content ) || strlen( $content ) > self::MAX_WRITE_BYTES ) return new WP_Error( 'theme_content_too_large', 'Replacement theme source must be a string no larger than 512 KB.' );
        if ( false !== strpos( $content, "\0" ) ) return new WP_Error( 'invalid_theme_content', 'Theme source cannot contain null bytes.' );
        if ( 'json' === $extension ) {
            json_decode( $content, true );
            if ( JSON_ERROR_NONE !== json_last_error() ) return new WP_Error( 'invalid_json', 'Replacement JSON is invalid: ' . json_last_error_msg() );
        }
        if ( 'php' === $extension && defined( 'TOKEN_PARSE' ) ) {
            try {
                token_get_all( $content, TOKEN_PARSE );
            } catch ( ParseError $error ) {
                return new WP_Error( 'php_parse_error', 'Replacement PHP failed syntax validation: ' . $error->getMessage() );
            }
        }
        return true;
    }

    private static function write_theme_file( array $theme, array $file, $content, $expected_sha256, $reason ) {
        if ( ! self::file_editing_allowed() ) return new WP_Error( 'theme_file_editing_disabled', 'WordPress theme file editing is disabled by site configuration.' );
        $validation = self::validate_new_content( $content, $file['extension'] );
        if ( is_wp_error( $validation ) ) return $validation;
        $current = file_get_contents( $file['absolute'] );
        if ( false === $current ) return new WP_Error( 'theme_file_unreadable', 'WordPress could not read the current theme file.' );
        if ( strlen( $current ) > self::MAX_WRITE_BYTES ) return new WP_Error( 'theme_file_too_large', 'Theme source files larger than 512 KB cannot be changed through MCP.' );
        $current_sha256 = hash( 'sha256', $current );
        if ( ! is_string( $expected_sha256 ) || ! hash_equals( $current_sha256, strtolower( $expected_sha256 ) ) ) {
            return new WP_Error( 'theme_file_conflict', 'The theme file changed after it was read. Read it again and use the latest SHA-256.' );
        }
        $new_sha256 = hash( 'sha256', $content );
        if ( hash_equals( $current_sha256, $new_sha256 ) ) {
            return array( 'success' => true, 'changed' => false, 'theme' => $theme['target'], 'stylesheet' => $theme['stylesheet'], 'path' => $file['path'], 'sha256' => $current_sha256 );
        }
        $backup_id = self::save_backup( $theme, $file, $current, $reason );
        if ( is_wp_error( $backup_id ) ) return $backup_id;
        self::load_file_admin();
        $result = wp_edit_theme_plugin_file(
            array(
                'file'       => $file['path'],
                'theme'      => $theme['stylesheet'],
                'newcontent' => $content,
                'nonce'      => wp_create_nonce( 'edit-theme_' . $theme['stylesheet'] . '_' . $file['path'] ),
            )
        );
        if ( is_wp_error( $result ) ) return $result;
        $written = file_get_contents( $file['absolute'] );
        if ( false === $written || ! hash_equals( $new_sha256, hash( 'sha256', $written ) ) ) return new WP_Error( 'theme_write_verification_failed', 'The theme file write could not be verified. The protected backup remains available.' );
        return array(
            'success'       => true,
            'changed'       => true,
            'theme'         => $theme['target'],
            'stylesheet'    => $theme['stylesheet'],
            'path'          => $file['path'],
            'previous_sha256'=> $current_sha256,
            'sha256'        => $new_sha256,
            'size_bytes'    => strlen( $written ),
            'backup_id'     => $backup_id,
            'php_rollback_checked' => 'php' === $file['extension'],
        );
    }

    private static function update_theme_file( array $args ) {
        if ( ! self::can_write_source() ) return self::write_permission_error();
        if ( ! isset( $args['confirm'] ) || true !== $args['confirm'] ) return new WP_Error( 'confirmation_required', 'Theme source editing requires confirm=true.' );
        $theme = self::resolve_theme( isset( $args['theme'] ) ? $args['theme'] : 'active' );
        if ( is_wp_error( $theme ) ) return $theme;
        $file = self::resolve_file( $theme, isset( $args['path'] ) ? $args['path'] : '' );
        if ( is_wp_error( $file ) ) return $file;
        return self::write_theme_file(
            $theme,
            $file,
            isset( $args['content'] ) ? $args['content'] : '',
            isset( $args['expected_sha256'] ) ? $args['expected_sha256'] : '',
            'update'
        );
    }

    private static function restore_theme_file_backup( array $args ) {
        if ( ! self::can_write_source() ) return self::write_permission_error();
        if ( ! isset( $args['confirm'] ) || true !== $args['confirm'] ) return new WP_Error( 'confirmation_required', 'Theme source backup restoration requires confirm=true.' );
        $backup_id = isset( $args['backup_id'] ) ? sanitize_text_field( $args['backup_id'] ) : '';
        $selected = null;
        foreach ( self::backups() as $backup ) {
            if ( isset( $backup['id'] ) && hash_equals( (string) $backup['id'], $backup_id ) ) {
                $selected = $backup;
                break;
            }
        }
        if ( ! $selected || ! isset( $selected['content'], $selected['stylesheet'], $selected['path'] ) ) return new WP_Error( 'backup_not_found', 'Theme source backup not found.' );
        if ( get_stylesheet() === $selected['stylesheet'] ) {
            $target = 'active';
        } elseif ( get_template() === $selected['stylesheet'] ) {
            $target = 'parent';
        } else {
            return new WP_Error( 'backup_theme_inactive', 'The backup does not belong to the current active theme or its parent.' );
        }
        $theme = self::resolve_theme( $target );
        if ( is_wp_error( $theme ) ) return $theme;
        $file = self::resolve_file( $theme, $selected['path'] );
        if ( is_wp_error( $file ) ) return $file;
        $result = self::write_theme_file(
            $theme,
            $file,
            $selected['content'],
            isset( $args['expected_sha256'] ) ? $args['expected_sha256'] : '',
            'restore'
        );
        if ( ! is_wp_error( $result ) ) $result['restored_from_backup_id'] = $backup_id;
        return $result;
    }
}
