<?php
/**
 * Plugin Name: WEM Inquiry
 * Description: Private inquiry forms plus backwards-compatible Phone/Country fields for native comments.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Text Domain: wem-inquiry
 * License: GPL-2.0-or-later
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'WEM_INQUIRY_TYPE', 'wem_inquiry' );

/** Render a private inquiry form anywhere using [wem_inquiry_form]. */
function wem_inquiry_form( $atts = array() ) {
    $atts = shortcode_atts( array( 'post_id' => 0 ), $atts, 'wem_inquiry_form' );
    $post_id = absint( $atts['post_id'] );
    if ( ! $post_id ) { $post_id = get_queried_object_id(); }
    $post = get_post( $post_id );
    $post_type = $post ? get_post_type_object( $post->post_type ) : null;
    if ( ! $post || 'publish' !== $post->post_status || ! $post_type || ! is_post_type_viewable( $post_type ) ) { return ''; }
    $status = isset( $_GET['wem_inquiry_status'] ) && is_string( $_GET['wem_inquiry_status'] ) ? sanitize_key( wp_unslash( $_GET['wem_inquiry_status'] ) ) : '';
    ob_start();
    if ( 'sent' === $status ) { echo '<p role="status">' . esc_html__( 'Thank you. Your inquiry has been received.', 'wem-inquiry' ) . '</p>'; }
    elseif ( 'error' === $status ) { echo '<p role="alert">' . esc_html__( 'Your inquiry could not be submitted. Please check the fields and try again.', 'wem-inquiry' ) . '</p>'; }
    ?>
    <form class="wem-inquiry-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="wem_inquiry_submit">
        <input type="hidden" name="wem_post_id" value="<?php echo esc_attr( $post_id ); ?>">
        <?php wp_nonce_field( 'wem_inquiry_' . $post_id, 'wem_inquiry_nonce' ); ?>
        <p><label><?php esc_html_e( 'Name', 'wem-inquiry' ); ?> <input type="text" name="wem_name" maxlength="120" required autocomplete="name"></label></p>
        <p><label><?php esc_html_e( 'Email', 'wem-inquiry' ); ?> <input type="email" name="wem_email" maxlength="190" required autocomplete="email"></label></p>
        <p><label><?php esc_html_e( 'Phone', 'wem-inquiry' ); ?> <input type="tel" name="wem_phone" maxlength="80" autocomplete="tel"></label></p>
        <p><label><?php esc_html_e( 'Country', 'wem-inquiry' ); ?> <input type="text" name="wem_country" maxlength="120" autocomplete="country-name"></label></p>
        <p><label><?php esc_html_e( 'Message', 'wem-inquiry' ); ?> <textarea name="wem_message" maxlength="10000" required rows="5"></textarea></p>
        <div class="wem-inquiry-trap" aria-hidden="true" style="position:absolute;left:-9999px;"><label>Website <input type="text" name="wem_website" tabindex="-1" autocomplete="off"></label></div>
        <p><button type="submit"><?php esc_html_e( 'Send Inquiry', 'wem-inquiry' ); ?></button></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'wem_inquiry_form', 'wem_inquiry_form' );

function wem_inquiry_input( $name, $max = 0 ) {
    if ( ! isset( $_POST[ $name ] ) || ! is_string( $_POST[ $name ] ) ) { return ''; }
    $value = sanitize_text_field( wp_unslash( $_POST[ $name ] ) );
    return $max ? ( function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max ) ) : $value;
}
/** Accept WordPress-native field names only on the private admin-post endpoint. */
function wem_inquiry_field( $preferred, $fallback, $max = 0 ) {
    if ( isset( $_POST[ $preferred ] ) ) {
        return wem_inquiry_input( $preferred, $max );
    }
    return wem_inquiry_input( $fallback, $max );
}
function wem_inquiry_redirect( $post_id, $status ) {
    $url = get_permalink( $post_id );
    if ( ! $url ) { $url = home_url( '/' ); }
    wp_safe_redirect( add_query_arg( 'wem_inquiry_status', $status, $url ), 303 );
    exit;
}
function wem_inquiry_submit() {
    $post_id = isset( $_POST['wem_post_id'] ) ? absint( $_POST['wem_post_id'] ) : 0;
    $post = get_post( $post_id );
    $post_type = $post ? get_post_type_object( $post->post_type ) : null;
    if ( ! $post || 'publish' !== $post->post_status || ! $post_type || ! is_post_type_viewable( $post_type ) ) { wp_die( esc_html__( 'Invalid destination.', 'wem-inquiry' ), '', array( 'response' => 400 ) ); }
    $nonce = wem_inquiry_input( 'wem_inquiry_nonce' );
    if ( ! wp_verify_nonce( $nonce, 'wem_inquiry_' . $post_id ) ) { wem_inquiry_redirect( $post_id, 'error' ); }
    if ( '' !== wem_inquiry_input( 'wem_website' ) ) { wem_inquiry_redirect( $post_id, 'sent' ); }
    $name = wem_inquiry_field( 'wem_name', 'author', 120 );
    $email = wem_inquiry_field( 'wem_email', 'email', 190 );
    $phone = wem_inquiry_field( 'wem_phone', 'phone', 80 );
    $country = wem_inquiry_field( 'wem_country', 'country', 120 );
    $message_key = isset( $_POST['wem_message'] ) ? 'wem_message' : 'comment';
    $message = isset( $_POST[ $message_key ] ) && is_string( $_POST[ $message_key ] )
        ? sanitize_textarea_field( wp_unslash( $_POST[ $message_key ] ) ) : '';
    if ( '' === $name || ! is_email( $email ) || '' === trim( $message ) || strlen( $message ) > 10000 ) { wem_inquiry_redirect( $post_id, 'error' ); }
    // Per-address rate limit; key is a one-way HMAC, not the raw address.
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
    $key = 'wem_inq_' . hash_hmac( 'sha256', $ip . '|' . $post_id, wp_salt( 'nonce' ) );
    if ( get_transient( $key ) ) { wem_inquiry_redirect( $post_id, 'error' ); }
    set_transient( $key, 1, 60 );
    $comment_id = wp_insert_comment( array(
        'comment_post_ID'      => $post_id,
        'comment_author'       => $name,
        'comment_author_email' => sanitize_email( $email ),
        'comment_content'      => $message,
        'comment_type'         => WEM_INQUIRY_TYPE,
        'comment_approved'     => '0',
        'comment_agent'        => '',
    ) );
    if ( is_wp_error( $comment_id ) || ! $comment_id ) { delete_transient( $key ); wem_inquiry_redirect( $post_id, 'error' ); }
    if ( '' !== $phone ) { update_comment_meta( $comment_id, 'phone', $phone ); }
    if ( '' !== $country ) { update_comment_meta( $comment_id, 'country', $country ); }
    // Data is retained in WordPress Comments; no administrator email is sent.
    wem_inquiry_redirect( $post_id, 'sent' );
}
add_action( 'admin_post_nopriv_wem_inquiry_submit', 'wem_inquiry_submit' );
add_action( 'admin_post_wem_inquiry_submit', 'wem_inquiry_submit' );

/** Prevent accidental publication by an administrator or another plugin. */
add_filter( 'pre_comment_approved', function ( $approved, $commentdata ) {
    return isset( $commentdata['comment_type'] ) && WEM_INQUIRY_TYPE === $commentdata['comment_type'] ? '0' : $approved;
}, 10, 2 );
add_action( 'transition_comment_status', function ( $new_status, $old_status, $comment ) {
    if ( WEM_INQUIRY_TYPE === $comment->comment_type && 'approved' === $new_status ) {
        wp_set_comment_status( $comment->comment_ID, 'hold' );
    }
}, 10, 3 );

/** Exclude inquiries from normal WP_Comment_Query queries for users without moderation permission. */
add_filter( 'comments_clauses', function ( $clauses ) {
    if ( current_user_can( 'moderate_comments' ) ) { return $clauses; }
    global $wpdb;
    if ( ! isset( $clauses['where'] ) ) { $clauses['where'] = '1=1'; }
    $clauses['where'] .= $wpdb->prepare( " AND {$wpdb->comments}.comment_type <> %s", WEM_INQUIRY_TYPE );
    return $clauses;
}, 10, 1 );

/** Block standard REST single-comment lookup; normal REST collections use the query filter. */
add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
    if ( null !== $result || current_user_can( 'moderate_comments' ) ) { return $result; }
    if ( preg_match( '#^/wp/v2/comments/([0-9]+)$#', $request->get_route(), $match ) ) {
        $comment = get_comment( absint( $match[1] ) );
        if ( $comment && WEM_INQUIRY_TYPE === $comment->comment_type ) {
            return new WP_Error( 'wem_inquiry_private', __( 'Comment not found.', 'wem-inquiry' ), array( 'status' => 404 ) );
        }
    }
    return $result;
}, 10, 3 );

/** Dedicated, read-only administrator overview; native edit screen remains available. */
add_action( 'admin_menu', function () {
    add_comments_page( __( 'Inquiries', 'wem-inquiry' ), __( 'Inquiries', 'wem-inquiry' ), 'moderate_comments', 'wem-inquiries', 'wem_inquiry_admin_page' );
} );
function wem_inquiry_admin_page() {
    if ( ! current_user_can( 'moderate_comments' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'wem-inquiry' ) ); }
    $page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
    $query = new WP_Comment_Query();
    $items = $query->query( array( 'type' => WEM_INQUIRY_TYPE, 'status' => 'all', 'number' => 20, 'offset' => ( $page - 1 ) * 20, 'orderby' => 'comment_date_gmt', 'order' => 'DESC' ) );
    echo '<div class="wrap"><h1>' . esc_html__( 'Inquiries', 'wem-inquiry' ) . '</h1><table class="widefat striped"><thead><tr>';
    foreach ( array( 'Date', 'Related page', 'Name', 'Email', 'Phone', 'Country', 'Message' ) as $header ) { echo '<th scope="col">' . esc_html( $header ) . '</th>'; }
    echo '</tr></thead><tbody>';
    foreach ( $items as $item ) {
        $edit = admin_url( 'comment.php?action=editcomment&c=' . absint( $item->comment_ID ) );
        echo '<tr><td>' . esc_html( $item->comment_date ) . '</td><td>' . esc_html( get_the_title( $item->comment_post_ID ) ) . '</td><td><a href="' . esc_url( $edit ) . '">' . esc_html( $item->comment_author ) . '</a></td><td>' . esc_html( $item->comment_author_email ) . '</td><td>' . esc_html( get_comment_meta( $item->comment_ID, 'phone', true ) ) . '</td><td>' . esc_html( get_comment_meta( $item->comment_ID, 'country', true ) ) . '</td><td>' . esc_html( wp_trim_words( $item->comment_content, 22 ) ) . '</td></tr>';
    }
    if ( ! $items ) { echo '<tr><td colspan="7">' . esc_html__( 'No inquiries yet.', 'wem-inquiry' ) . '</td></tr>'; }
    echo '</tbody></table>';
    if ( 20 === count( $items ) ) { echo '<p><a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'wem-inquiries', 'paged' => $page + 1 ), admin_url( 'edit-comments.php' ) ) ) . '">' . esc_html__( 'Next page', 'wem-inquiry' ) . '</a></p>'; }
    echo '</div>';
}

/** Allow editors to correct the two historical comment-meta fields on inquiry records. */
add_action( 'add_meta_boxes_comment', function ( $comment ) {
    if ( $comment && WEM_INQUIRY_TYPE === $comment->comment_type ) {
        add_meta_box( 'wem-inquiry-contact', __( 'Inquiry contact details', 'wem-inquiry' ), 'wem_inquiry_contact_box', 'comment', 'normal', 'default' );
    }
} );
function wem_inquiry_contact_box( $comment ) {
    wp_nonce_field( 'wem_inquiry_edit_' . $comment->comment_ID, 'wem_inquiry_edit_nonce' );
    foreach ( array( 'phone' => 80, 'country' => 120 ) as $field => $max ) {
        echo '<p><label for="wem-inquiry-' . esc_attr( $field ) . '">' . esc_html( ucfirst( $field ) ) . '</label>';
        echo '<input class="widefat" id="wem-inquiry-' . esc_attr( $field ) . '" name="wem_inquiry_meta_' . esc_attr( $field ) . '" maxlength="' . esc_attr( $max ) . '" value="' . esc_attr( get_comment_meta( $comment->comment_ID, $field, true ) ) . '"></p>';
    }

    // The source belongs to WordPress comment_post_ID, not comment_author_url.
    $source_id = (int) $comment->comment_post_ID;
    $source = $source_id ? get_post( $source_id ) : null;
    echo '<p><strong>' . esc_html__( 'Source Page', 'wem-inquiry' ) . '</strong><br>';
    if ( $source ) {
        $source_title = get_the_title( $source_id );
        if ( '' === $source_title ) {
            $source_title = sprintf( __( 'Post #%d', 'wem-inquiry' ), $source_id );
        }
        $source_url = get_permalink( $source_id );
        if ( $source_url ) {
            echo '<a href="' . esc_url( $source_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $source_title ) . '</a>';
        } else {
            echo esc_html( $source_title );
        }
        echo ' <span class="description">(#' . esc_html( (string) $source_id ) . ')</span>';
    } else {
        echo esc_html__( 'Source page unavailable', 'wem-inquiry' );
    }
    echo '</p>';
}
add_action( 'edit_comment', function ( $comment_id ) {
    $comment = get_comment( $comment_id );
    if ( ! $comment || WEM_INQUIRY_TYPE !== $comment->comment_type || ! current_user_can( 'edit_comment', $comment_id ) ) { return; }
    $nonce = isset( $_POST['wem_inquiry_edit_nonce'] ) && is_string( $_POST['wem_inquiry_edit_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wem_inquiry_edit_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'wem_inquiry_edit_' . $comment_id ) ) { return; }
    foreach ( array( 'phone' => 80, 'country' => 120 ) as $field => $max ) {
        $input = 'wem_inquiry_meta_' . $field;
        if ( ! isset( $_POST[ $input ] ) ) { continue; }
        $value = wem_inquiry_input( $input, $max );
        if ( '' === $value ) { delete_comment_meta( $comment_id, $field ); }
        else { update_comment_meta( $comment_id, $field, $value ); }
    }
} );


/**
 * Compatibility layer for Custom Comment Fields 1.1.0.
 * It intentionally preserves historical meta keys: phone and country.
 * Native comments are not automatically private; site owners must disable
 * public comment output if using native comments as inquiries.
 */
function wem_inquiry_legacy_render_fields() {
    ?>
    <p class="comment-form-phone">
        <label for="wem-legacy-phone"><?php esc_html_e( 'Phone', 'wem-inquiry' ); ?></label>
        <input id="wem-legacy-phone" name="wccf_phone" type="tel" maxlength="80" autocomplete="tel">
    </p>
    <p class="comment-form-country">
        <label for="wem-legacy-country"><?php esc_html_e( 'Country', 'wem-inquiry' ); ?></label>
        <input id="wem-legacy-country" name="wccf_country" type="text" maxlength="120" autocomplete="country-name">
    </p>
    <?php wp_nonce_field( 'wccf_save_fields', 'wccf_fields_nonce', false ); ?>
    <?php
}
add_action( 'comment_form_after_fields', 'wem_inquiry_legacy_render_fields' );
add_action( 'comment_form_logged_in_after', 'wem_inquiry_legacy_render_fields' );

/** Sanitize legacy values without accepting arrays or objects. */
function wem_inquiry_legacy_clean( $raw, $max ) {
    if ( ! is_string( $raw ) ) { return ''; }
    $value = sanitize_text_field( wp_unslash( $raw ) );
    return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
}

function wem_inquiry_legacy_save_comment( $comment_id, $approved = null, $commentdata = array() ) {
    // wp-comments-post.php creates the comment first. This hook supports both
    // comment_form() fields and historical hand-written contact forms.
    $comment = get_comment( $comment_id );
    if ( ! $comment || WEM_INQUIRY_TYPE === $comment->comment_type ) { return; }

    // The generated WordPress comment_form() sends a nonce. Historical
    // wp-comments-post.php templates may not contain that additional field.
    // If supplied, a bad nonce must never be silently ignored.
    if ( isset( $_POST['wccf_fields_nonce'] ) ) {
        if ( ! is_string( $_POST['wccf_fields_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wccf_fields_nonce'] ) ), 'wccf_save_fields' ) ) {
            return;
        }
    }

    // Prefer legacy plugin field names where both are present. Never accept
    // array values, and never overwrite existing meta on an absent input.
    $fields = array(
        'phone'   => array( 'wccf_phone', 'phone', 80 ),
        'country' => array( 'wccf_country', 'country', 120 ),
    );
    foreach ( $fields as $meta_key => $spec ) {
        $input = isset( $_POST[ $spec[0] ] ) ? $spec[0] : $spec[1];
        if ( ! isset( $_POST[ $input ] ) || ! is_string( $_POST[ $input ] ) ) { continue; }
        $value = wem_inquiry_legacy_clean( $_POST[ $input ], $spec[2] );
        if ( '' !== $value ) {
            update_comment_meta( $comment_id, $meta_key, $value );
        }
    }
}
add_action( 'comment_post', 'wem_inquiry_legacy_save_comment', 10, 3 );

/** Use one editor for both old comments and new private inquiries. */
function wem_inquiry_legacy_add_meta_box( $comment ) {
    if ( ! $comment ) { return; }
    // Inquiry records already get their own editing box above.
    if ( WEM_INQUIRY_TYPE === $comment->comment_type ) { return; }
    add_meta_box( 'wem-legacy-contact', __( 'Contact details', 'wem-inquiry' ), 'wem_inquiry_legacy_meta_box', 'comment', 'normal', 'default' );
}
add_action( 'add_meta_boxes_comment', 'wem_inquiry_legacy_add_meta_box' );

function wem_inquiry_legacy_meta_box( $comment ) {
    wp_nonce_field( 'wccf_edit_comment', 'wccf_admin_nonce' );
    foreach ( array( 'phone' => 80, 'country' => 120 ) as $field => $max ) {
        echo '<p><label for="wem-legacy-admin-' . esc_attr( $field ) . '">' . esc_html( ucfirst( $field ) ) . '</label>';
        echo '<input class="widefat" type="text" id="wem-legacy-admin-' . esc_attr( $field ) . '" name="wccf_admin_' . esc_attr( $field ) . '" maxlength="' . esc_attr( $max ) . '" value="' . esc_attr( get_comment_meta( $comment->comment_ID, $field, true ) ) . '"></p>';
    }
}
function wem_inquiry_legacy_save_admin( $comment_id ) {
    $comment = get_comment( $comment_id );
    if ( ! $comment || WEM_INQUIRY_TYPE === $comment->comment_type || ! current_user_can( 'edit_comment', $comment_id ) ) { return; }
    $nonce = isset( $_POST['wccf_admin_nonce'] ) && is_string( $_POST['wccf_admin_nonce'] )
        ? sanitize_text_field( wp_unslash( $_POST['wccf_admin_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'wccf_edit_comment' ) ) { return; }
    foreach ( array( 'phone' => 80, 'country' => 120 ) as $field => $max ) {
        $input = 'wccf_admin_' . $field;
        if ( ! isset( $_POST[ $input ] ) ) { continue; }
        $value = wem_inquiry_legacy_clean( $_POST[ $input ], $max );
        if ( '' === $value ) { delete_comment_meta( $comment_id, $field ); }
        else { update_comment_meta( $comment_id, $field, $value ); }
    }
}
add_action( 'edit_comment', 'wem_inquiry_legacy_save_admin' );

/** Retain the historical columns for older comments and current inquiries. */
function wem_inquiry_legacy_columns( $columns ) {
    $columns['wccf_phone'] = __( 'Phone', 'wem-inquiry' );
    $columns['wccf_country'] = __( 'Country', 'wem-inquiry' );
    return $columns;
}
add_filter( 'manage_edit-comments_columns', 'wem_inquiry_legacy_columns' );
function wem_inquiry_legacy_column_value( $column, $comment_id ) {
    $fields = array( 'wccf_phone' => 'phone', 'wccf_country' => 'country' );
    if ( ! isset( $fields[ $column ] ) ) { return; }
    $value = get_comment_meta( $comment_id, $fields[ $column ], true );
    echo '' !== $value ? esc_html( $value ) : '&mdash;';
}
add_action( 'manage_comments_custom_column', 'wem_inquiry_legacy_column_value', 10, 2 );
