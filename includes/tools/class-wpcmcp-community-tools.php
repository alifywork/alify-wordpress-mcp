<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCMCP_Community_Tools {
    public static function definitions( $can_read, $can_write ) {
        $tools = array();
        if ( $can_read ) {
            $tools[] = self::t( 'wordpress.list_roles', 'List User Roles', 'List WordPress roles and their display names. Requires list_users.', array(), array(), true, false, true );
        }
        if ( $can_write ) {
            $tools[] = self::t( 'wordpress.create_comment', 'Create Comment', 'Create a comment on a WordPress content item. Requires comment moderation capability.', array(
                'post_id'      => array( 'type' => 'integer', 'minimum' => 1 ),
                'content'      => array( 'type' => 'string' ),
                'author_name'  => array( 'type' => 'string' ),
                'author_email' => array( 'type' => 'string', 'format' => 'email' ),
                'author_url'   => array( 'type' => 'string', 'format' => 'uri' ),
                'parent_id'    => array( 'type' => 'integer', 'minimum' => 0 ),
                'status'       => array( 'type' => 'string', 'enum' => array( 'approve', 'hold' ) ),
            ), array( 'post_id', 'content' ), false, false, false );
            $tools[] = self::t( 'wordpress.update_comment', 'Update Comment', 'Update comment content or author profile fields.', array(
                'comment_id'   => array( 'type' => 'integer', 'minimum' => 1 ),
                'content'      => array( 'type' => 'string' ),
                'author_name'  => array( 'type' => 'string' ),
                'author_email' => array( 'type' => 'string', 'format' => 'email' ),
                'author_url'   => array( 'type' => 'string', 'format' => 'uri' ),
            ), array( 'comment_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.moderate_comment', 'Moderate Comment', 'Approve, hold, spam, or trash a comment.', array(
                'comment_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'status'     => array( 'type' => 'string', 'enum' => array( 'approve', 'hold', 'spam', 'trash' ) ),
            ), array( 'comment_id', 'status' ), false, true, true );
            $tools[] = self::t( 'wordpress.delete_comment', 'Delete Comment', 'Trash a comment, or permanently delete it when permanent=true and confirm=true.', array(
                'comment_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'permanent'  => array( 'type' => 'boolean' ),
                'confirm'    => array( 'type' => 'boolean', 'description' => 'Required for permanent deletion.' ),
            ), array( 'comment_id' ), false, true, true );
            $tools[] = self::t( 'wordpress.create_user', 'Create User', 'Create a WordPress user. Password is never returned.', array(
                'username'     => array( 'type' => 'string' ),
                'email'        => array( 'type' => 'string', 'format' => 'email' ),
                'password'     => array( 'type' => 'string', 'minLength' => 12 ),
                'display_name' => array( 'type' => 'string' ),
                'first_name'   => array( 'type' => 'string' ),
                'last_name'    => array( 'type' => 'string' ),
                'description'  => array( 'type' => 'string' ),
                'role'         => array( 'type' => 'string' ),
            ), array( 'username', 'email', 'password' ), false, false, false );
            $tools[] = self::t( 'wordpress.update_user', 'Update User', 'Update a WordPress user profile or password.', array(
                'user_id'      => array( 'type' => 'integer', 'minimum' => 1 ),
                'email'        => array( 'type' => 'string', 'format' => 'email' ),
                'password'     => array( 'type' => 'string', 'minLength' => 12 ),
                'display_name' => array( 'type' => 'string' ),
                'first_name'   => array( 'type' => 'string' ),
                'last_name'    => array( 'type' => 'string' ),
                'description'  => array( 'type' => 'string' ),
            ), array( 'user_id' ), false, false, true );
            $tools[] = self::t( 'wordpress.set_user_roles', 'Set User Roles', 'Replace a user’s WordPress roles. The active MCP user cannot change their own roles.', array(
                'user_id' => array( 'type' => 'integer', 'minimum' => 1 ),
                'roles'   => array( 'type' => 'array', 'minItems' => 1, 'items' => array( 'type' => 'string' ) ),
            ), array( 'user_id', 'roles' ), false, true, true );
            $tools[] = self::t( 'wordpress.delete_user', 'Delete User', 'Permanently delete a WordPress user and optionally reassign their content. Requires confirm=true.', array(
                'user_id'     => array( 'type' => 'integer', 'minimum' => 1 ),
                'reassign_to' => array( 'type' => 'integer', 'minimum' => 1 ),
                'confirm'     => array( 'type' => 'boolean', 'description' => 'Must be true.' ),
            ), array( 'user_id', 'confirm' ), false, true, true );
        }
        return $tools;
    }

    private static function t( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent ) {
        return WPCMCP_Tools::tool( $name, $title, $description, $properties, $required, $read_only, $destructive, $idempotent );
    }

    public static function execute( $name, array $args ) {
        switch ( $name ) {
            case 'wordpress.list_roles': return self::list_roles();
            case 'wordpress.create_comment': return self::create_comment( $args );
            case 'wordpress.update_comment': return self::update_comment( $args );
            case 'wordpress.moderate_comment': return self::moderate_comment( $args );
            case 'wordpress.delete_comment': return self::delete_comment( $args );
            case 'wordpress.create_user': return self::create_user( $args );
            case 'wordpress.update_user': return self::update_user( $args );
            case 'wordpress.set_user_roles': return self::set_user_roles( $args );
            case 'wordpress.delete_user': return self::delete_user( $args );
            default: return null;
        }
    }

    private static function list_roles() {
        if ( ! current_user_can( 'list_users' ) ) return new WP_Error( 'forbidden', 'You cannot list WordPress roles.' );
        $items = array();
        foreach ( wp_roles()->roles as $slug => $data ) $items[] = array( 'slug' => $slug, 'name' => translate_user_role( $data['name'] ) );
        return array( 'roles' => $items );
    }

    private static function comment_record( $comment ) {
        return array( 'id' => (int) $comment->comment_ID, 'post_id' => (int) $comment->comment_post_ID, 'parent_id' => (int) $comment->comment_parent, 'author' => $comment->comment_author, 'author_url' => $comment->comment_author_url, 'status' => wp_get_comment_status( $comment ), 'date_gmt' => $comment->comment_date_gmt, 'content' => $comment->comment_content );
    }

    private static function create_comment( array $args ) {
        if ( ! current_user_can( 'moderate_comments' ) ) return new WP_Error( 'forbidden', 'You cannot create administrative comments.' );
        $post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
        if ( ! get_post( $post_id ) ) return new WP_Error( 'not_found', 'Target content not found.' );
        $content = isset( $args['content'] ) ? wp_kses_post( $args['content'] ) : '';
        if ( '' === trim( wp_strip_all_tags( $content ) ) ) return new WP_Error( 'invalid_content', 'Comment content is required.' );
        if ( isset( $args['author_email'] ) && ! is_email( $args['author_email'] ) ) return new WP_Error( 'invalid_email', 'Comment author email is invalid.' );
        $parent_id = isset( $args['parent_id'] ) ? absint( $args['parent_id'] ) : 0;
        $parent = $parent_id ? get_comment( $parent_id ) : false;
        if ( $parent_id && ( ! $parent || (int) $parent->comment_post_ID !== $post_id ) ) return new WP_Error( 'invalid_parent', 'Parent comment does not belong to the target content.' );
        $status = isset( $args['status'] ) && 'approve' === sanitize_key( $args['status'] ) ? 1 : 0;
        $data = array(
            'comment_post_ID'      => $post_id,
            'comment_content'      => $content,
            'comment_author'       => isset( $args['author_name'] ) ? sanitize_text_field( $args['author_name'] ) : wp_get_current_user()->display_name,
            'comment_author_email' => isset( $args['author_email'] ) ? sanitize_email( $args['author_email'] ) : wp_get_current_user()->user_email,
            'comment_author_url'   => isset( $args['author_url'] ) ? esc_url_raw( $args['author_url'] ) : '',
            'comment_parent'       => $parent_id,
            'comment_approved'     => $status,
            'user_id'              => get_current_user_id(),
        );
        $id = wp_insert_comment( wp_slash( $data ) );
        if ( ! $id ) return new WP_Error( 'create_failed', 'WordPress could not create the comment.' );
        return self::comment_record( get_comment( $id ) );
    }

    private static function update_comment( array $args ) {
        $id = isset( $args['comment_id'] ) ? absint( $args['comment_id'] ) : 0;
        $comment = get_comment( $id );
        if ( ! $comment ) return new WP_Error( 'not_found', 'Comment not found.' );
        if ( ! current_user_can( 'edit_comment', $id ) ) return new WP_Error( 'forbidden', 'You cannot edit this comment.' );
        $data = array( 'comment_ID' => $id );
        if ( isset( $args['author_email'] ) && ! is_email( $args['author_email'] ) ) return new WP_Error( 'invalid_email', 'Comment author email is invalid.' );
        if ( array_key_exists( 'content', $args ) ) $data['comment_content'] = wp_kses_post( $args['content'] );
        if ( array_key_exists( 'author_name', $args ) ) $data['comment_author'] = sanitize_text_field( $args['author_name'] );
        if ( array_key_exists( 'author_email', $args ) ) $data['comment_author_email'] = sanitize_email( $args['author_email'] );
        if ( array_key_exists( 'author_url', $args ) ) $data['comment_author_url'] = esc_url_raw( $args['author_url'] );
        if ( 1 === count( $data ) ) return new WP_Error( 'no_changes', 'No comment fields were supplied.' );
        $result = wp_update_comment( wp_slash( $data ), true );
        if ( is_wp_error( $result ) || false === $result ) return is_wp_error( $result ) ? $result : new WP_Error( 'update_failed', 'WordPress could not update the comment.' );
        return self::comment_record( get_comment( $id ) );
    }

    private static function moderate_comment( array $args ) {
        if ( ! current_user_can( 'moderate_comments' ) ) return new WP_Error( 'forbidden', 'You cannot moderate comments.' );
        $id = isset( $args['comment_id'] ) ? absint( $args['comment_id'] ) : 0;
        if ( ! get_comment( $id ) ) return new WP_Error( 'not_found', 'Comment not found.' );
        $status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
        $allowed = array( 'approve' => 'approve', 'hold' => 'hold', 'spam' => 'spam', 'trash' => 'trash' );
        if ( ! isset( $allowed[ $status ] ) ) return new WP_Error( 'invalid_status', 'Unsupported comment status.' );
        $result = wp_set_comment_status( $id, $allowed[ $status ], true );
        if ( ! $result ) return new WP_Error( 'moderation_failed', 'WordPress could not change the comment status.' );
        return self::comment_record( get_comment( $id ) );
    }

    private static function delete_comment( array $args ) {
        $id = isset( $args['comment_id'] ) ? absint( $args['comment_id'] ) : 0;
        if ( ! get_comment( $id ) ) return new WP_Error( 'not_found', 'Comment not found.' );
        if ( ! current_user_can( 'delete_comment', $id ) ) return new WP_Error( 'forbidden', 'You cannot delete this comment.' );
        $permanent = ! empty( $args['permanent'] );
        if ( $permanent && ( ! isset( $args['confirm'] ) || true !== $args['confirm'] ) ) return new WP_Error( 'confirmation_required', 'Permanent comment deletion requires confirm=true.' );
        $result = $permanent ? wp_delete_comment( $id, true ) : wp_trash_comment( $id );
        if ( ! $result ) return new WP_Error( 'delete_failed', 'WordPress could not delete this comment.' );
        return array( 'success' => true, 'comment_id' => $id, 'permanent' => $permanent );
    }

    private static function valid_role( $role ) {
        $role = sanitize_key( $role );
        return isset( wp_roles()->roles[ $role ] ) ? $role : '';
    }

    private static function user_record( $user ) {
        return array( 'id' => (int) $user->ID, 'username' => $user->user_login, 'display_name' => $user->display_name, 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'roles' => array_values( $user->roles ), 'registered' => $user->user_registered );
    }

    private static function create_user( array $args ) {
        if ( ! current_user_can( 'create_users' ) ) return new WP_Error( 'forbidden', 'You cannot create WordPress users.' );
        $login = isset( $args['username'] ) ? sanitize_user( $args['username'], true ) : '';
        $email = isset( $args['email'] ) ? sanitize_email( $args['email'] ) : '';
        $password = isset( $args['password'] ) ? (string) $args['password'] : '';
        if ( '' === $login || ! is_email( $email ) || strlen( $password ) < 12 ) return new WP_Error( 'invalid_user', 'Valid username/email and a password of at least 12 characters are required.' );
        $data = array( 'user_login' => $login, 'user_email' => $email, 'user_pass' => $password );
        foreach ( array( 'display_name', 'first_name', 'last_name', 'description' ) as $field ) if ( array_key_exists( $field, $args ) ) $data[ $field ] = sanitize_text_field( $args[ $field ] );
        if ( ! empty( $args['role'] ) ) {
            $role = self::valid_role( $args['role'] );
            if ( ! $role ) return new WP_Error( 'invalid_role', 'The requested WordPress role does not exist.' );
            if ( ! current_user_can( 'promote_users' ) ) return new WP_Error( 'forbidden', 'You cannot assign WordPress roles.' );
            $data['role'] = $role;
        }
        $id = wp_insert_user( wp_slash( $data ) );
        if ( is_wp_error( $id ) ) return $id;
        return self::user_record( get_user_by( 'id', $id ) );
    }

    private static function update_user( array $args ) {
        $id = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;
        $user = get_user_by( 'id', $id );
        if ( ! $user ) return new WP_Error( 'not_found', 'User not found.' );
        if ( ! current_user_can( 'edit_user', $id ) ) return new WP_Error( 'forbidden', 'You cannot edit this user.' );
        $data = array( 'ID' => $id );
        if ( array_key_exists( 'email', $args ) ) {
            $email = sanitize_email( $args['email'] );
            if ( ! is_email( $email ) ) return new WP_Error( 'invalid_email', 'A valid email address is required.' );
            $data['user_email'] = $email;
        }
        if ( array_key_exists( 'password', $args ) ) {
            if ( strlen( (string) $args['password'] ) < 12 ) return new WP_Error( 'weak_password', 'Password must be at least 12 characters.' );
            $data['user_pass'] = (string) $args['password'];
        }
        foreach ( array( 'display_name', 'first_name', 'last_name', 'description' ) as $field ) if ( array_key_exists( $field, $args ) ) $data[ $field ] = sanitize_text_field( $args[ $field ] );
        if ( 1 === count( $data ) ) return new WP_Error( 'no_changes', 'No user fields were supplied.' );
        $result = wp_update_user( wp_slash( $data ) );
        if ( is_wp_error( $result ) ) return $result;
        return self::user_record( get_user_by( 'id', $id ) );
    }

    private static function set_user_roles( array $args ) {
        $id = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;
        if ( $id === get_current_user_id() ) return new WP_Error( 'self_role_change_blocked', 'The active MCP user cannot change their own roles.' );
        if ( ! current_user_can( 'promote_users' ) || ! current_user_can( 'edit_user', $id ) ) return new WP_Error( 'forbidden', 'You cannot change this user’s roles.' );
        $user = get_user_by( 'id', $id );
        if ( ! $user ) return new WP_Error( 'not_found', 'User not found.' );
        $roles = isset( $args['roles'] ) && is_array( $args['roles'] ) ? array_values( array_unique( array_map( 'sanitize_key', $args['roles'] ) ) ) : array();
        if ( empty( $roles ) ) return new WP_Error( 'invalid_roles', 'At least one valid role is required.' );
        foreach ( $roles as $role ) if ( ! self::valid_role( $role ) ) return new WP_Error( 'invalid_role', 'One or more requested roles do not exist.' );
        foreach ( array_values( $user->roles ) as $role ) $user->remove_role( $role );
        foreach ( $roles as $role ) $user->add_role( $role );
        return self::user_record( get_user_by( 'id', $id ) );
    }

    private static function delete_user( array $args ) {
        $id = isset( $args['user_id'] ) ? absint( $args['user_id'] ) : 0;
        if ( ! isset( $args['confirm'] ) || true !== $args['confirm'] ) return new WP_Error( 'confirmation_required', 'User deletion requires confirm=true.' );
        if ( $id === get_current_user_id() ) return new WP_Error( 'self_delete_blocked', 'The active MCP user cannot delete themselves.' );
        if ( ! get_user_by( 'id', $id ) ) return new WP_Error( 'not_found', 'User not found.' );
        if ( ! current_user_can( 'delete_user', $id ) ) return new WP_Error( 'forbidden', 'You cannot delete this user.' );
        $reassign = isset( $args['reassign_to'] ) ? absint( $args['reassign_to'] ) : null;
        if ( $reassign && ( $reassign === $id || ! get_user_by( 'id', $reassign ) ) ) return new WP_Error( 'invalid_reassignment', 'Reassignment user is invalid.' );
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $result = wp_delete_user( $id, $reassign );
        if ( ! $result ) return new WP_Error( 'delete_failed', 'WordPress could not delete this user.' );
        return array( 'success' => true, 'user_id' => $id, 'reassigned_to' => $reassign, 'deleted_permanently' => true );
    }
}
