<?php
defined('ABSPATH') || exit;
final class WEM_Inquiry_Fields {
    public static function builtins() {
        return array(
            'name' => array('label'=>'Name','type'=>'text','required'=>true,'max'=>120),
            'email' => array('label'=>'Email','type'=>'email','required'=>true,'max'=>190),
            'phone' => array('label'=>'Phone','type'=>'tel','required'=>false,'max'=>80),
            'country' => array('label'=>'Country','type'=>'text','required'=>false,'max'=>120),
            'message' => array('label'=>'Message','type'=>'textarea','required'=>true,'max'=>10000),
        );
    }
    public static function customs() {
        $data = get_option('wem_inquiry_fields', array());
        return is_array($data) ? $data : array();
    }
    public static function sanitize_config($raw) {
        if (!current_user_can('manage_options')) return self::customs();
        if (!is_array($raw)) return array();
        $out = array();
        $original = self::customs();
        $existing_keys = array();
        foreach ($original as $old_field) {
            if (is_array($old_field) && isset($old_field['key'])) $existing_keys[$old_field['key']] = true;
        }
        foreach ($raw as $field) {
            if (!is_array($field)) continue;
            $key = isset($field['key']) && is_string($field['key']) ? sanitize_key($field['key']) : '';
            if (count($out) >= 8 && !isset($existing_keys[$key])) continue;
            if (!$key || !preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key) || in_array($key, array('name','email','phone','country','message','action','post_id','website','nonce'), true) || isset($out[$key])) continue;
            $type = isset($field['type']) && is_string($field['type']) ? $field['type'] : 'text';
            if (!in_array($type, array('text','textarea','select','radio','checkbox'), true)) $type = 'text';
            $label = isset($field['label']) && is_string($field['label']) ? sanitize_text_field($field['label']) : '';
            if (!$label) continue;
            $options = array();
            if (in_array($type, array('select','radio'), true)) {
                $lines = isset($field['options']) && is_string($field['options']) ? preg_split('/\r\n|\r|\n/', $field['options']) : array();
                foreach (array_slice($lines, 0, 30) as $line) {
                    $choice = sanitize_text_field($line);
                    if ($choice !== '' && strlen($choice) <= 150) $options[] = $choice;
                }
                $options = array_values(array_unique($options));
                if (!$options) continue;
            }
            $out[$key] = array('key'=>$key,'label'=>(function_exists('mb_substr') ? mb_substr($label, 0, 100) : substr($label, 0, 100)),'type'=>$type,'required'=>!empty($field['required']),'options'=>$options);
        }
        return array_values($out);
    }
    /**
     * Trusted server-side field scope. Product inquiries collect only built-in
     * contact fields; other sources retain registered custom fields. Plugins may
     * adjust this on the server; posted form data cannot choose the scope.
     */
    public static function for_source($post_id) {
        $post_id = absint($post_id);
        $fields = get_post_type($post_id) === 'product' ? array() : self::customs();
        $fields = apply_filters('wem_inquiry_fields_for_source', $fields, $post_id);
        if (!is_array($fields)) return array();
        $registered = array();
        foreach (self::customs() as $f) if (isset($f['key'])) $registered[$f['key']] = $f;
        $safe = array();
        foreach ($fields as $f) {
            if (!is_array($f) || !isset($f['key'], $registered[$f['key']])) continue;
            $safe[$f['key']] = $registered[$f['key']];
        }
        return array_values($safe);
    }
    public static function posted($name) {
        $name = 'wem_cf_' . $name;
        if (!isset($_POST[$name]) || !is_string($_POST[$name])) return '';
        return sanitize_text_field(wp_unslash($_POST[$name]));
    }
    public static function validate_customs($post_id) {
        $values = array();
        foreach (self::for_source($post_id) as $field) {
            if (!isset($field['key'], $field['type'])) continue;
            $key = $field['key'];
            $value = self::posted($key);
            if ($field['type'] === 'checkbox') $value = $value === '1' ? '1' : '';
            if (in_array($field['type'], array('select','radio'), true) && $value !== '' && !in_array($value, (array)$field['options'], true)) return new WP_Error('invalid_custom', 'Invalid selection.');
            if (strlen($value) > 3000) return new WP_Error('invalid_custom', 'Custom field too long.');
            if (!empty($field['required']) && $value === '') return new WP_Error('required_custom', 'A required field is empty.');
            $values[$key] = $value;
        }
        return $values;
    }
}
