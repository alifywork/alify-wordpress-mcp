<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Workflow_Tools {
    public static function definitions( $can_read, $can_write ) {
        $tools = array();

        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.workflow_validate', 'Validate MCP Workflow', 'Validate a multi-step MCP workflow against currently exposed tool schemas without executing it.', array(
                'steps' => array(
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 25,
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'tool' => array( 'type' => 'string' ),
                            'arguments' => array( 'type' => 'object', 'additionalProperties' => true ),
                        ),
                        'required' => array( 'tool' ),
                        'additionalProperties' => false,
                    ),
                ),
            ), array( 'steps' ), true, false, true );
        }

        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.workflow_execute', 'Execute MCP Workflow', 'Execute up to 25 existing MCP tools sequentially. Optionally creates a rollback snapshot first and restores it automatically if a later step fails. Requires confirm=true.', array(
                'label' => array( 'type' => 'string' ),
                'steps' => array(
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 25,
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'tool' => array( 'type' => 'string' ),
                            'arguments' => array( 'type' => 'object', 'additionalProperties' => true ),
                        ),
                        'required' => array( 'tool' ),
                        'additionalProperties' => false,
                    ),
                ),
                'snapshot' => array(
                    'type' => 'object',
                    'properties' => array(
                        'post_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer', 'minimum' => 1 ), 'maxItems' => 100 ),
                        'options' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'maxItems' => 100 ),
                    ),
                    'additionalProperties' => false,
                ),
                'rollback_on_error' => array( 'type' => 'boolean' ),
                'confirm' => array( 'type' => 'boolean' ),
            ), array( 'steps', 'confirm' ), false, true, false );
        }

        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.workflow_validate': return self::validate_workflow( $args );
            case 'wordpress.workflow_execute': return self::execute_workflow( $args );
            default: return null;
        }
    }

    private static function current_definitions() {
        return WPCMCP_Tools::definitions( array( 'wordpress.read', 'wordpress.write' ) );
    }

    private static function definition_map() {
        $map = array();
        foreach ( self::current_definitions() as $definition ) {
            if ( empty( $definition['name'] ) ) continue;
            $map[ $definition['name'] ] = $definition;
        }
        return $map;
    }

    private static function blocked_nested_tool( $name ) {
        return in_array(
            $name,
            array(
                'wordpress.workflow_execute',
                'wordpress.workflow_validate',
                'wordpress.permission_profile_set',
                'wordpress.transaction_rollback',
                'wordpress.transaction_delete_snapshot',
            ),
            true
        );
    }

    private static function validate_steps( array $steps ) {
        $definitions = self::definition_map();
        $validated = array();

        foreach ( $steps as $index => $step ) {
            if ( ! is_array( $step ) || empty( $step['tool'] ) ) {
                return new WP_Error( 'invalid_workflow_step', 'Workflow step ' . $index . ' is missing a tool name.' );
            }

            $tool = sanitize_text_field( $step['tool'] );
            if ( self::blocked_nested_tool( $tool ) ) {
                return new WP_Error( 'blocked_workflow_tool', 'Tool cannot be nested inside workflow_execute: ' . $tool );
            }
            if ( ! isset( $definitions[ $tool ] ) ) {
                return new WP_Error( 'workflow_tool_unavailable', 'Tool is not exposed by the current permission profile: ' . $tool );
            }

            $arguments = isset( $step['arguments'] ) && is_array( $step['arguments'] ) ? $step['arguments'] : array();
            $valid = WPCMCP_Tools::validate_arguments( $definitions[ $tool ], $arguments );
            if ( is_wp_error( $valid ) ) {
                return new WP_Error(
                    'invalid_workflow_arguments',
                    'Invalid arguments for step ' . $index . ' (' . $tool . '): ' . $valid->get_error_message()
                );
            }

            $validated[] = array(
                'tool' => $tool,
                'arguments' => $arguments,
                'read_only' => ! empty( $definitions[ $tool ]['annotations']['readOnlyHint'] ),
                'destructive' => ! empty( $definitions[ $tool ]['annotations']['destructiveHint'] ),
            );
        }

        return $validated;
    }

    private static function validate_workflow( array $args ) {
        $steps = isset( $args['steps'] ) && is_array( $args['steps'] ) ? $args['steps'] : array();
        $validated = self::validate_steps( $steps );
        if ( is_wp_error( $validated ) ) return $validated;

        return array(
            'valid' => true,
            'step_count' => count( $validated ),
            'steps' => $validated,
            'write_step_count' => count( array_filter( $validated, static function ( $step ) { return ! $step['read_only']; } ) ),
            'destructive_step_count' => count( array_filter( $validated, static function ( $step ) { return $step['destructive']; } ) ),
        );
    }

    private static function create_snapshot_if_requested( array $args ) {
        if ( empty( $args['snapshot'] ) || ! is_array( $args['snapshot'] ) ) return null;
        if ( ! class_exists( 'WPCMCP_Transaction_Tools' ) ) return new WP_Error( 'snapshot_unavailable', 'Transaction snapshot engine is unavailable.' );

        $snapshot_args = array(
            'label' => ! empty( $args['label'] ) ? sanitize_text_field( $args['label'] ) : 'Workflow snapshot',
            'post_ids' => isset( $args['snapshot']['post_ids'] ) ? $args['snapshot']['post_ids'] : array(),
            'options' => isset( $args['snapshot']['options'] ) ? $args['snapshot']['options'] : array(),
        );

        return WPCMCP_Transaction_Tools::execute( 'wordpress.transaction_create_snapshot', $snapshot_args );
    }

    private static function rollback_snapshot( $snapshot_id ) {
        if ( ! $snapshot_id || ! class_exists( 'WPCMCP_Transaction_Tools' ) ) return null;
        return WPCMCP_Transaction_Tools::execute(
            'wordpress.transaction_rollback',
            array( 'snapshot_id' => $snapshot_id, 'confirm' => true )
        );
    }

    private static function execute_workflow( array $args ) {
        if ( empty( $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Executing a multi-step workflow requires confirm=true.' );

        $steps = isset( $args['steps'] ) && is_array( $args['steps'] ) ? $args['steps'] : array();
        $validated = self::validate_steps( $steps );
        if ( is_wp_error( $validated ) ) return $validated;

        $snapshot = self::create_snapshot_if_requested( $args );
        if ( is_wp_error( $snapshot ) ) return $snapshot;
        $snapshot_id = is_array( $snapshot ) && ! empty( $snapshot['id'] ) ? $snapshot['id'] : null;

        $results = array();
        $rollback_on_error = ! array_key_exists( 'rollback_on_error', $args ) || (bool) $args['rollback_on_error'];

        foreach ( $validated as $index => $step ) {
            $started = microtime( true );
            $result = WPCMCP_Tools::execute( $step['tool'], $step['arguments'] );
            $duration_ms = (int) round( ( microtime( true ) - $started ) * 1000 );

            if ( is_wp_error( $result ) ) {
                $results[] = array(
                    'index' => $index,
                    'tool' => $step['tool'],
                    'success' => false,
                    'error' => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                    'duration_ms' => $duration_ms,
                );

                $rollback = null;
                if ( $rollback_on_error && $snapshot_id ) {
                    $rollback = self::rollback_snapshot( $snapshot_id );
                }

                WPCMCP_DB::log(
                    'workflow',
                    $step['tool'],
                    'error',
                    get_current_user_id(),
                    '',
                    'step=' . $index . ';snapshot=' . ( $snapshot_id ?: 'none' )
                );

                return array(
                    'success' => false,
                    'label' => isset( $args['label'] ) ? sanitize_text_field( $args['label'] ) : '',
                    'snapshot_id' => $snapshot_id,
                    'failed_step' => $index,
                    'results' => $results,
                    'rollback_attempted' => (bool) ( $rollback_on_error && $snapshot_id ),
                    'rollback' => is_wp_error( $rollback ) ? array( 'success' => false, 'message' => $rollback->get_error_message() ) : $rollback,
                );
            }

            $results[] = array(
                'index' => $index,
                'tool' => $step['tool'],
                'success' => true,
                'duration_ms' => $duration_ms,
                'result' => $result,
            );
        }

        WPCMCP_DB::log(
            'workflow',
            '',
            'success',
            get_current_user_id(),
            '',
            'steps=' . count( $validated ) . ';snapshot=' . ( $snapshot_id ?: 'none' )
        );

        return array(
            'success' => true,
            'label' => isset( $args['label'] ) ? sanitize_text_field( $args['label'] ) : '',
            'snapshot_id' => $snapshot_id,
            'results' => $results,
        );
    }
}
