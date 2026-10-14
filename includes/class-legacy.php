<?php
defined('ABSPATH') || exit;
/** Historical wp-comments-post.php forms continue to store phone / country under their original keys. */
final class WEM_Inquiry_Legacy {
    public static function register() {
        add_action('comment_form_after_fields', array(__CLASS__,'fields'));
        add_action('comment_form_logged_in_after', array(__CLASS__,'fields'));
        add_action('comment_post', array(__CLASS__,'save'),10,3);
    }
    public static function fields() {
        echo '<p><label for="wem-legacy-phone">Phone</label><input id="wem-legacy-phone" type="tel" name="wccf_phone" maxlength="80"></p>';
        echo '<p><label for="wem-legacy-country">Country</label><input id="wem-legacy-country" type="text" name="wccf_country" maxlength="120"></p>';
        wp_nonce_field('wccf_save_fields','wccf_fields_nonce',false);
    }
    public static function save($id,$approved=null,$data=array()) {
        $c=get_comment($id);if (!$c || $c->comment_type===WEM_INQUIRY_TYPE) return;
        if (isset($_POST['wccf_fields_nonce'])) {
            if (!is_string($_POST['wccf_fields_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wccf_fields_nonce'])),'wccf_save_fields')) return;
        }
        foreach (array('phone'=>array('wccf_phone','phone',80),'country'=>array('wccf_country','country',120)) as $key=>$names) {
            $n=isset($_POST[$names[0]])?$names[0]:$names[1];
            if (!isset($_POST[$n]) || !is_string($_POST[$n])) continue;
            $v=substr(sanitize_text_field(wp_unslash($_POST[$n])),0,$names[2]);
            if ($v!=='') update_comment_meta($id,$key,$v);
        }
    }
}
