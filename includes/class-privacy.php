<?php
defined('ABSPATH') || exit;
final class WEM_Inquiry_Privacy {
    public static function register() {
        add_filter('pre_comment_approved', array(__CLASS__, 'hold'), 10, 2);
        add_filter('comments_clauses', array(__CLASS__, 'exclude_public'), 10, 1);
        // Comment feeds use WP_Query's own SQL, bypassing comments_clauses.
        add_filter('comment_feed_where', array(__CLASS__, 'exclude_comment_feed'), 10, 2);
        add_filter('comments_array', array(__CLASS__, 'filter_rendered_comments'), 10, 2);
        add_filter('rest_pre_dispatch', array(__CLASS__, 'block_single_rest'), 10, 3);
    }
    public static function hold($status, $data) {
        return isset($data['comment_type']) && $data['comment_type'] === WEM_INQUIRY_TYPE ? '0' : $status;
    }
    /**
     * Approve/Unapprove/Spam/Trash are native administrator workflow actions.
     * An approved inquiry must STILL stay private in frontend queries.
     * Never use the approval status itself as an access-control boundary.
     */
    public static function exclude_public($clauses) {
        if (current_user_can('moderate_comments')) return $clauses;
        global $wpdb;
        $clauses['where'] = (isset($clauses['where']) ? $clauses['where'] : '1=1') . $wpdb->prepare(" AND {$wpdb->comments}.comment_type <> %s", WEM_INQUIRY_TYPE);
        return $clauses;
    }
    /** Exclude private inquiries from both site-wide and per-post comment feeds. */
    public static function exclude_comment_feed($where, $query) {
        if (current_user_can('moderate_comments')) return $where;
        global $wpdb;
        return $where . $wpdb->prepare(" AND {$wpdb->comments}.comment_type <> %s", WEM_INQUIRY_TYPE);
    }
    /** Defense in depth for themes that pass already-loaded comments to wp_list_comments(). */
    public static function filter_rendered_comments($comments, $post_id) {
        if (current_user_can('moderate_comments') || !is_array($comments)) return $comments;
        return array_values(array_filter($comments, static function ($comment) {
            return !($comment instanceof WP_Comment && $comment->comment_type === WEM_INQUIRY_TYPE);
        }));
    }
    public static function block_single_rest($result, $server, $request) {
        if ($result !== null || current_user_can('moderate_comments')) return $result;
        if (preg_match('#^/wp/v2/comments/([0-9]+)$#', $request->get_route(), $m)) {
            $item = get_comment(absint($m[1]));
            if ($item && $item->comment_type === WEM_INQUIRY_TYPE) return new WP_Error('wem_inquiry_private', __('Comment not found.', 'wem-inquiry'), array('status'=>404));
        }
        return $result;
    }
}
