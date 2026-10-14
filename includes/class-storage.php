<?php
defined('ABSPATH') || exit;
final class WEM_Inquiry_Storage {
    public static function save($post_id, $values, $customs) {
        // Use REMOTE_ADDR only; forwarded headers are not trusted without
        // explicit reverse-proxy configuration. Never read IP from form fields.
        $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '';
        if (!filter_var($ip, FILTER_VALIDATE_IP)) $ip = '';
        $agent = isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $agent = function_exists('mb_substr') ? mb_substr($agent,0,254) : substr($agent,0,254);
        $id = wp_insert_comment(array(
            'comment_post_ID' => $post_id,
            'comment_author' => $values['name'],
            'comment_author_email' => sanitize_email($values['email']),
            'comment_content' => $values['message'],
            'comment_type' => WEM_INQUIRY_TYPE,
            'comment_approved' => '0',
            'comment_author_IP' => $ip,
            'comment_agent' => $agent,
        ));
        if (!$id || is_wp_error($id)) return new WP_Error('storage_error', 'Could not save inquiry.');
        foreach (array('phone','country') as $key) if ($values[$key] !== '') update_comment_meta($id, $key, $values[$key]);
        foreach ($customs as $key => $value) if ($value !== '') update_comment_meta($id, '_wem_inquiry_' . $key, $value);
        return $id;
    }
}
