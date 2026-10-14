<?php
defined('ABSPATH') || exit;
final class WEM_Inquiry_Form {
    public static function register() {
        add_shortcode('wem_inquiry_form', array(__CLASS__, 'shortcode'));
        add_action('admin_post_nopriv_wem_inquiry_submit', array(__CLASS__, 'submit'));
        add_action('admin_post_wem_inquiry_submit', array(__CLASS__, 'submit'));
    }
    public static function eligible($id) {
        $post = get_post($id);
        if (!$post || $post->post_status !== 'publish') return false;
        $type = get_post_type_object($post->post_type);
        return $type && is_post_type_viewable($type);
    }
    public static function hidden_fields($post_id = 0) {
        $post_id = absint($post_id ?: get_queried_object_id());
        if (!self::eligible($post_id)) return false;
        echo '<input type="hidden" name="action" value="wem_inquiry_submit">';
        echo '<input type="hidden" name="wem_post_id" value="' . esc_attr($post_id) . '">';
        wp_nonce_field('wem_inquiry_' . $post_id, 'wem_inquiry_nonce');
        echo '<span class="wem-inquiry-trap" aria-hidden="true" style="position:absolute;left:-9999px"><label>Website <input type="text" name="wem_website" tabindex="-1" autocomplete="off"></label></span>';
        return true;
    }
    public static function shortcode($atts = array()) {
        $atts = shortcode_atts(array('post_id'=>0), $atts, 'wem_inquiry_form');
        $id = absint($atts['post_id'] ?: get_queried_object_id());
        if (!self::eligible($id)) return '';
        ob_start();
        $status = isset($_GET['wem_inquiry_status']) && is_string($_GET['wem_inquiry_status']) ? sanitize_key(wp_unslash($_GET['wem_inquiry_status'])) : '';
        if ($status === 'sent') echo '<p role="status">' . esc_html__('Thank you. Your inquiry has been received.', 'wem-inquiry') . '</p>';
        if ($status === 'error') echo '<p role="alert">' . esc_html__('Your inquiry could not be submitted. Please try again.', 'wem-inquiry') . '</p>';
        echo '<form class="wem-inquiry-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        self::hidden_fields($id);
        foreach (WEM_Inquiry_Fields::builtins() as $key=>$f) {
            $field_name = 'wem_' . $key;
            echo '<p><label for="wem-' . esc_attr($key . '-' . $id) . '">' . esc_html($f['label']) . '</label> ';
            if ($f['type'] === 'textarea') echo '<textarea id="wem-' . esc_attr($key . '-' . $id) . '" name="' . esc_attr($field_name) . '" maxlength="' . esc_attr($f['max']) . '" required rows="5"></textarea>';
            else echo '<input id="wem-' . esc_attr($key . '-' . $id) . '" type="' . esc_attr($f['type']) . '" name="' . esc_attr($field_name) . '" maxlength="' . esc_attr($f['max']) . '" ' . (!empty($f['required']) ? 'required ' : '') . '>';
            echo '</p>';
        }
        foreach (WEM_Inquiry_Fields::for_source($id) as $f) {
            $key = $f['key']; $name = 'wem_cf_' . $key; $required = !empty($f['required']) ? 'required ' : '';
            echo '<p><label>' . esc_html($f['label']) . ' ';
            if ($f['type'] === 'textarea') echo '<textarea name="' . esc_attr($name) . '" maxlength="3000" ' . $required . '></textarea>';
            elseif ($f['type'] === 'select') {
                echo '<select name="' . esc_attr($name) . '" ' . $required . '><option value="">Select...</option>';
                foreach ($f['options'] as $option) echo '<option value="' . esc_attr($option) . '">' . esc_html($option) . '</option>';
                echo '</select>';
            } elseif ($f['type'] === 'radio') {
                echo '</label>';
                foreach ($f['options'] as $option) echo '<label><input type="radio" name="' . esc_attr($name) . '" value="' . esc_attr($option) . '" ' . $required . '> ' . esc_html($option) . '</label> ';
            } elseif ($f['type'] === 'checkbox') echo '<input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . $required . '>';
            else echo '<input type="text" name="' . esc_attr($name) . '" maxlength="3000" ' . $required . '>';
            echo ($f['type'] === 'radio' ? '</p>' : '</label></p>');
        }
        echo '<p><button type="submit">' . esc_html__('Send Inquiry', 'wem-inquiry') . '</button></p></form>';
        return ob_get_clean();
    }
    private static function input($names, $max) {
        foreach ($names as $name) if (isset($_POST[$name])) {
            if (!is_string($_POST[$name])) return '';
            $v = sanitize_text_field(wp_unslash($_POST[$name]));
            return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
        }
        return '';
    }
    private static function redirect($id, $status) {
        $url = get_permalink($id);
        wp_safe_redirect(add_query_arg('wem_inquiry_status', $status, $url ?: home_url('/')), 303);
        exit;
    }
    public static function submit() {
        $id = isset($_POST['wem_post_id']) ? absint($_POST['wem_post_id']) : 0;
        if (!self::eligible($id)) wp_die(esc_html__('Invalid destination.', 'wem-inquiry'), '', array('response'=>400));
        $nonce = self::input(array('wem_inquiry_nonce'), 200);
        if (!wp_verify_nonce($nonce, 'wem_inquiry_' . $id)) self::redirect($id, 'error');
        if (self::input(array('wem_website'), 200) !== '') self::redirect($id, 'sent');
        $values = array(
            'name'=>self::input(array('wem_name','author','name'),120),
            'email'=>self::input(array('wem_email','email'),190),
            'phone'=>self::input(array('wem_phone','phone'),80),
            'country'=>self::input(array('wem_country','country'),120),
        );
        $msg_key = isset($_POST['wem_message']) ? 'wem_message' : (isset($_POST['comment']) ? 'comment' : 'message');
        $values['message'] = isset($_POST[$msg_key]) && is_string($_POST[$msg_key]) ? sanitize_textarea_field(wp_unslash($_POST[$msg_key])) : '';
        if (!$values['name'] || !is_email($values['email']) || !trim($values['message']) || strlen($values['message']) > 10000) self::redirect($id, 'error');
        $customs = WEM_Inquiry_Fields::validate_customs($id);
        if (is_wp_error($customs)) self::redirect($id, 'error');
        $ip = isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $limit_key = 'wem_inq_' . hash_hmac('sha256', $ip . '|' . $id, wp_salt('nonce'));
        if (get_transient($limit_key)) self::redirect($id, 'error');
        set_transient($limit_key, 1, 60);
        $saved = WEM_Inquiry_Storage::save($id, $values, $customs);
        if (is_wp_error($saved)) { delete_transient($limit_key); self::redirect($id, 'error'); }
        self::redirect($id, 'sent');
    }
}
/** Theme helper: echo the nonce, source ID, action and honeypot inside a custom <form>. */
function wem_inquiry_form_hidden_fields($post_id = 0) {
    return WEM_Inquiry_Form::hidden_fields($post_id);
}
