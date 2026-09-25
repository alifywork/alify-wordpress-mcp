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
        if ( ! self::can_read_source() ) return new WP_Error( 'fo