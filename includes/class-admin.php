<?php
defined('ABSPATH') || exit;
final class WEM_Inquiry_Admin {
    public static function register_menu() {
        add_options_page('WEM Inquiry Fields', 'WEM Inquiry Fields', 'manage_options', 'wem-inquiry-fields', array(__CLASS__, 'page'));
    }
    public static function register_settings() {
        register_setting('wem_inquiry_settings', 'wem_inquiry_fields', array('type'=>'array','sanitize_callback'=>array('WEM_Inquiry_Fields','sanitize_config'),'default'=>array()));
    }
    private static function field_row($f, $index) {
        $name = 'wem_inquiry_fields[' . $index . ']';
        echo '<tr class="wem-field-row"><td><input type="text" maxlength="40" name="'.esc_attr($name.'[key]').'" value="'.esc_attr($f['key'] ?? '').'" placeholder="Field Key" required></td>';
        echo '<td><input type="text" maxlength="100" name="'.esc_attr($name.'[label]').'" value="'.esc_attr($f['label'] ?? '').'" placeholder="Field Label" required></td>';
        echo '<td><select name="'.esc_attr($name.'[type]').'">';
        foreach (array('text','textarea','select','radio','checkbox') as $type) {
            echo '<option value="'.esc_attr($type).'" '.selected($f['type'] ?? 'text', $type, false).'>'.esc_html(ucfirst($type)).'</option>';
        }
        echo '</select></td><td><input type="checkbox" name="'.esc_attr($name.'[required]').'" value="1" '.checked(!empty($f['required']), true, false).'></td>';
        echo '<td><textarea rows="2" cols="23" name="'.esc_attr($name.'[options]').'" placeholder="For Select / Radio">'.esc_textarea(isset($f['options']) && is_array($f['options']) ? implode("\n", $f['options']) : '').'</textarea></td>';
        echo '<td><button type="button" class="button wem-field-remove" aria-label="Remove field row">Remove</button></td></tr>';
    }
    public static function page() {
        if (!current_user_can('manage_options')) return;
        $fields = WEM_Inquiry_Fields::customs();
        $count = count($fields);
        echo '<div class="wrap"><h1>WEM Inquiry — Custom Fields</h1>';
        echo '<p>Native Comments remains the inquiry manager. Built-in fields: Name, Email, Phone, Country, Message. Up to 8 new custom field definitions. Do not rename field keys after collecting data.</p>';
        if ($count > 8) echo '<div class="notice notice-warning inline"><p>Existing configuration contains more than 8 fields. All existing definitions are preserved; remove unneeded definitions before adding more.</p></div>';
        echo '<form action="options.php" method="post" id="wem-fields-form">';
        settings_fields('wem_inquiry_settings');
        echo '<table class="widefat striped" style="max-width:1250px"><thead><tr><th>Field Key</th><th>Label</th><th>Type</th><th>Required</th><th>Options (one per line)</th><th>Action</th></tr></thead><tbody id="wem-fields-rows">';
        foreach ($fields as $i => $f) self::field_row($f, $i);
        echo '</tbody></table><p><button type="button" class="button" id="wem-field-add">+ Add Field</button> <span id="wem-field-count" aria-live="polite"></span></p>';
        submit_button('Save Custom Fields');
        echo '</form>';
        echo '<template id="wem-field-row-template">';
        self::field_row(array(), '__INDEX__');
        echo '</template>';
        echo '<p>Removing a field definition does not delete values already saved in historical inquiries. Custom HTML: <code>wem_inquiry_form_hidden_fields($post_id)</code> and <code>wem_cf_{key}</code>. See README.</p>';
        ?>
        <script>
        (function () {
            'use strict';
            var form = document.getElementById('wem-fields-form');
            var body = document.getElementById('wem-fields-rows');
            var add = document.getElementById('wem-field-add');
            var counter = document.getElementById('wem-field-count');
            var template = document.getElementById('wem-field-row-template');
            if (!form || !body || !add || !counter || !template) return;
            function update() {
                var count = body.querySelectorAll('.wem-field-row').length;
                counter.textContent = count + ' / 8 fields';
                add.disabled = count >= 8;
            }
            function reindex() {
                body.querySelectorAll('.wem-field-row').forEach(function (row, index) {
                    row.querySelectorAll('[name]').forEach(function (field) {
                        field.name = field.name.replace(/wem_inquiry_fields\[[^\]]+\]/, 'wem_inquiry_fields[' + index + ']');
                    });
                });
                update();
            }
            add.addEventListener('click', function () {
                if (body.querySelectorAll('.wem-field-row').length >= 8) return;
                var html = template.innerHTML.replace(/__INDEX__/g, String(body.querySelectorAll('.wem-field-row').length));
                body.insertAdjacentHTML('beforeend', html);
                reindex();
                var last = body.lastElementChild;
                if (last) last.querySelector('input').focus();
            });
            body.addEventListener('click', function (event) {
                if (!event.target.closest('.wem-field-remove')) return;
                var row = event.target.closest('.wem-field-row');
                if (!row) return;
                if (!window.confirm('Remove this field definition? Historical inquiry data will be retained.')) return;
                row.remove();
                reindex();
            });
            form.addEventListener('submit', function () {
                reindex();
                // Ensure clearing every row sends an explicit empty array to the Settings API.
                if (!body.querySelector('.wem-field-row')) {
                    var input = document.createElement('input');
                    input.type = 'hidden'; input.name = 'wem_inquiry_fields[empty]'; input.value = '';
                    form.appendChild(input);
                }
            });
            update();
        }());
        </script>
        <?php
        echo '</div>';
    }
    public static function register_comment_screen() {
        add_action('add_meta_boxes_comment', array(__CLASS__, 'meta_box'));
        add_action('edit_comment', array(__CLASS__, 'save_comment'));
        add_filter('manage_edit-comments_columns', array(__CLASS__, 'columns'));
        add_action('manage_comments_custom_column', array(__CLASS__, 'column_value'), 10, 2);
        add_action('restrict_manage_comments', array(__CLASS__, 'type_filter'));
        add_action('pre_get_comments', array(__CLASS__, 'query_type'));
    }
    public static function type_filter() {
        if (!current_user_can('moderate_comments')) return;
        $selected = isset($_GET['wem_inquiry_only']) && $_GET['wem_inquiry_only']==='1';
        echo '<label class="screen-reader-text" for="wem-inquiry-only">Inquiry filter</label><select id="wem-inquiry-only" name="wem_inquiry_only"><option value="">All comments</option><option value="1" '.selected($selected,true,false).'>Private inquiries only</option></select>';
    }
    public static function query_type($query) {
        if (!is_admin() || !current_user_can('moderate_comments') || !function_exists('get_current_screen')) return;
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'edit-comments') return;
        if (isset($_GET['wem_inquiry_only']) && $_GET['wem_inquiry_only'] === '1') {
            $query->query_vars['type'] = WEM_INQUIRY_TYPE;
        } elseif (empty($query->query_vars['type'])) {
            // Include custom inquiry comments in the standard All Comments list.
            $query->query_vars['type'] = 'all';
        }
    }
    public static function columns($columns) {
        $columns['wem_phone'] = 'Phone'; $columns['wem_country'] = 'Country'; $columns['wem_kind'] = 'Type';
        return $columns;
    }
    public static function column_value($column, $id) {
        if ($column === 'wem_kind') { $c=get_comment($id);echo ($c && $c->comment_type === WEM_INQUIRY_TYPE) ? 'Private Inquiry' : 'Comment'; return; }
        if ($column === 'wem_phone' || $column === 'wem_country') {
            $value = get_comment_meta($id, $column==='wem_phone'?'phone':'country', true);
            echo $value !== '' ? esc_html($value) : '&mdash;';
        }
    }
    public static function meta_box($comment) {
        if (!$comment) return;
        add_meta_box('wem-inquiry-details','Inquiry Contact & Custom Fields', array(__CLASS__,'render_box'),'comment','normal','default');
    }
    /** Inquiry details are a read-only snapshot; native Comments controls manage moderation. */
    public static function render_box($comment) {
        if ($comment->comment_type !== WEM_INQUIRY_TYPE) {
            wp_nonce_field('wem_inquiry_edit_'.$comment->comment_ID,'wem_inquiry_edit_nonce');
            foreach (array('phone'=>'Phone','country'=>'Country') as $key=>$label) {
                echo '<p><label>'.esc_html($label).' <input class="widefat" name="wem_edit_'.esc_attr($key).'" value="'.esc_attr(get_comment_meta($comment->comment_ID,$key,true)).'"></label></p>';
            }
            return;
        }
        echo '<style>
        #wem-inquiry-details .inside{margin:0;padding:16px 20px 20px}
        .wem-inq-copybar{display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin:0 0 16px}
        #wem-inq-copy-button{display:inline-flex;align-items:center;justify-content:center;gap:7px;line-height:1.2}
        #wem-inq-copy-button .dashicons{width:16px;height:16px;font-size:16px;line-height:1;margin:0;display:inline-flex;align-items:center;justify-content:center}
        .wem-inq-copymsg{font-size:13px;color:#646970}
        .wem-inq-copymsg.is-success{color:#008a20}.wem-inq-copymsg.is-error{color:#b32d2e}
        .wem-inq-detail{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px 20px}
        .wem-inq-section{grid-column:1/-1;margin:8px 0 0;padding:0 0 9px;border-bottom:1px solid #dcdcde;font-size:13px;font-weight:600;color:#1d2327}
        .wem-inq-field{min-width:0}.wem-inq-label{display:block;font-size:12px;font-weight:600;color:#646970;margin-bottom:5px}
        .wem-inq-value{box-sizing:border-box;min-height:39px;border:1px solid #dcdcde;background:#f6f7f7;border-radius:4px;padding:9px 11px;color:#2c3338;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.55}
        .wem-inq-wide{grid-column:1/-1}.wem-inq-muted{color:#787c82;font-style:italic}
        .wem-inq-notice{grid-column:1/-1;margin:10px 0 0;color:#646970;font-size:12px}
        @media(max-width:782px){.wem-inq-detail{grid-template-columns:1fr}}
        </style>';
        self::copy_panel($comment);
        echo '<div class="wem-inq-detail">';
        $source_id = (int) $comment->comment_post_ID;
        $source = $source_id ? get_post($source_id) : null;
        $source_label = $source ? get_the_title($source_id) : '';
        if ($source && $source_label === '') $source_label = sprintf('Post #%d', $source_id);
        $source_url = $source ? get_permalink($source_id) : '';
        echo '<div class="wem-inq-section">Inquiry Source</div>';
        echo '<div class="wem-inq-field wem-inq-wide"><span class="wem-inq-label">Source Page</span><div class="wem-inq-value">';
        if ($source && $source_url) echo '<a href="'.esc_url($source_url).'" target="_blank" rel="noopener noreferrer">'.esc_html($source_label).'</a> <span class="wem-inq-muted">(#'.absint($source_id).')</span>';
        else echo '<span class="wem-inq-muted">'.esc_html($source ? $source_label.' (#'.$source_id.')' : 'Source page unavailable').'</span>';
        echo '</div></div>';
        echo '<div class="wem-inq-section">Contact Details</div>';
        self::readonly_field('Phone',get_comment_meta($comment->comment_ID,'phone',true));
        self::readonly_field('Country',get_comment_meta($comment->comment_ID,'country',true));
        $customs = WEM_Inquiry_Fields::customs();
        $known = array();
        foreach ($customs as $f) {
            $known[$f['key']] = true;
        }
        $meta = get_comment_meta($comment->comment_ID);
        $extra = array();
        foreach ($meta as $key => $values) {
            if (strpos($key,'_wem_inquiry_') !== 0) continue;
            $field_key = substr($key,strlen('_wem_inquiry_'));
            if ($field_key !== '' && !isset($known[$field_key])) $extra[$field_key] = get_comment_meta($comment->comment_ID,$key,true);
        }
        $present = array();
        foreach ($customs as $f) {
            if (metadata_exists('comment', $comment->comment_ID, '_wem_inquiry_'.$f['key'])) $present[] = $f;
        }
        if ($present || $extra) {
            echo '<div class="wem-inq-section">Custom Fields</div>';
            foreach ($present as $f) {
                $value = get_comment_meta($comment->comment_ID,'_wem_inquiry_'.$f['key'],true);
                if ($f['type'] === 'checkbox' && (string)$value === '1') $value = 'Yes (1)';
                elseif ($f['type'] === 'checkbox' && (string)$value === '0') $value = 'No (0)';
                self::readonly_field($f['label'],$value,$f['type']==='textarea');
            }
            foreach ($extra as $key=>$value) self::readonly_field(ucwords(str_replace('_',' ',$key)).' (archived field)',$value);
        }
        echo '<div class="wem-inq-section">Submission Environment</div>';
        $ip = is_string($comment->comment_author_IP) ? $comment->comment_author_IP : '';
        $agent = is_string($comment->comment_agent) ? $comment->comment_agent : '';
        self::readonly_field('IP Address',$ip);
        self::readonly_field('Browser / User Agent',$agent,true);
        echo '<p class="wem-inq-notice">These details are read-only submission records. Older inquiries may not contain an IP address or browser information. Name, email, message and moderation status remain managed by the native WordPress Comments editor. Limit access to trusted administrators.</p>';
        echo '</div>';
    }
    /** Build copy text from the stored inquiry, never from editable form inputs. */
    private static function copy_panel($comment) {
        $id = (int) $comment->comment_ID;
        $source_id = (int) $comment->comment_post_ID;
        $source = $source_id ? get_post($source_id) : null;
        $source_name = $source ? get_the_title($source_id) : '';
        if ($source && $source_name === '') $source_name = sprintf('Post #%d', $source_id);
        $lines = array('Inquiry Details', '');
        foreach (array(
            'Name' => $comment->comment_author,
            'Email' => $comment->comment_author_email,
            'Phone' => get_comment_meta($id, 'phone', true),
            'Country' => get_comment_meta($id, 'country', true),
        ) as $label => $value) {
            if (is_scalar($value) && trim((string) $value) !== '') $lines[] = $label . ': ' . (string) $value;
        }
        $configured = array();
        foreach (WEM_Inquiry_Fields::customs() as $field) {
            $key = $field['key'];
            $configured[$key] = true;
            $value = get_comment_meta($id, '_wem_inquiry_' . $key, true);
            if (!is_scalar($value) || trim((string)$value) === '') continue;
            if ($field['type'] === 'checkbox') {
                if ((string)$value === '1') $value = 'Yes';
                elseif ((string)$value === '0') $value = 'No';
            }
            $lines[] = $field['label'] . ': ' . (string)$value;
        }
        // Preserve fields on old inquiries even when their definitions were removed.
        foreach (get_comment_meta($id) as $meta_key => $values) {
            if (strpos($meta_key, '_wem_inquiry_') !== 0) continue;
            $key = substr($meta_key, strlen('_wem_inquiry_'));
            if ($key === '' || isset($configured[$key])) continue;
            $value = get_comment_meta($id, $meta_key, true);
            if (is_scalar($value) && trim((string)$value) !== '') {
                $lines[] = ucwords(str_replace('_', ' ', $key)) . ' (archived field): ' . (string)$value;
            }
        }
        $message = (string)$comment->comment_content;
        if (trim($message) !== '') {
            $lines[] = '';
            $lines[] = 'Message:';
            $lines[] = $message;
        }
        $lines[] = '';
        $lines[] = 'Source: ' . ($source ? $source_name . ' (#' . $source_id . ')' : 'Source page unavailable');
        $lines[] = 'Submitted: ' . (string)$comment->comment_date;
        $payload_id = 'wem-inq-copy-data-' . $id;
        $message_id = 'wem-inq-copy-status-' . $id;
        echo '<div class="wem-inq-copybar"><button type="button" class="button button-primary" id="wem-inq-copy-button" data-copy-target="' . esc_attr($payload_id) . '" data-status-target="' . esc_attr($message_id) . '"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span>Copy Inquiry</button>';
        echo '<span class="wem-inq-copymsg" id="' . esc_attr($message_id) . '" role="status" aria-live="polite"></span></div>';
        // Encode as inert JSON, not executable JavaScript and not an HTML attribute.
        $json = wp_json_encode(implode("\n", $lines), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<script type="application/json" id="' . esc_attr($payload_id) . '">' . $json . '</script>';
        ?>
        <script>
        (function () {
            'use strict';
            var button = document.getElementById('wem-inq-copy-button');
            if (!button) return;
            button.addEventListener('click', async function () {
                var content = document.getElementById(button.dataset.copyTarget);
                var status = document.getElementById(button.dataset.statusTarget);
                if (!content || !status) return;
                var value;
                try { value = JSON.parse(content.textContent); }
                catch (e) { status.textContent = 'Copy failed'; status.className = 'wem-inq-copymsg is-error'; return; }
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(value);
                    } else {
                        var temp = document.createElement('textarea');
                        temp.value = value;
                        temp.setAttribute('readonly', '');
                        temp.style.position = 'fixed';
                        temp.style.left = '-9999px';
                        document.body.appendChild(temp);
                        temp.select();
                        var copied = document.execCommand('copy');
                        document.body.removeChild(temp);
                        if (!copied) throw new Error('Clipboard unavailable');
                    }
                    status.textContent = 'Copied!';
                    status.className = 'wem-inq-copymsg is-success';
                } catch (e) {
                    status.textContent = 'Copy failed — select and copy manually.';
                    status.className = 'wem-inq-copymsg is-error';
                }
            });
        }());
        </script>
        <?php
    }

    private static function readonly_field($label,$value,$wide=false) {
        $value = is_scalar($value) ? (string)$value : '';
        echo '<div class="wem-inq-field'.($wide?' wem-inq-wide':'').'"><span class="wem-inq-label">'.esc_html($label).'</span><div class="wem-inq-value">';
        echo $value !== '' ? esc_html($value) : '<span class="wem-inq-muted">Not recorded</span>';
        echo '</div></div>';
    }

    public static function save_comment($id) {
        if (!current_user_can('edit_comment',$id)) return;
        $existing = get_comment($id);
        // New private inquiries are immutable here. Native comment fields
        // and moderation are handled separately by WordPress.
        if ($existing && $existing->comment_type === WEM_INQUIRY_TYPE) return;
        $nonce=isset($_POST['wem_inquiry_edit_nonce'])&&is_string($_POST['wem_inquiry_edit_nonce'])?sanitize_text_field(wp_unslash($_POST['wem_inquiry_edit_nonce'])):'';
        if (!wp_verify_nonce($nonce,'wem_inquiry_edit_'.$id)) return;
        foreach (array('phone'=>80,'country'=>120) as $key=>$max) {
            $n='wem_edit_'.$key;
            if (!isset($_POST[$n])||!is_string($_POST[$n])) continue;
            $v=sanitize_text_field(wp_unslash($_POST[$n]));
            $v=substr($v,0,$max);
            if ($v==='') delete_comment_meta($id,$key); else update_comment_meta($id,$key,$v);
        }
        $c=get_comment($id);
        if (!$c || $c->comment_type !== WEM_INQUIRY_TYPE) return;
        $posted=isset($_POST['wem_edit_custom'])&&is_array($_POST['wem_edit_custom'])?wp_unslash($_POST['wem_edit_custom']):array();
        foreach (WEM_Inquiry_Fields::customs() as $f) {
            $key=$f['key'];if (!isset($posted[$key])||!is_string($posted[$key])) continue;
            $v=substr(sanitize_text_field($posted[$key]),0,3000);
            $meta='_wem_inquiry_'.$key;
            if ($v==='') delete_comment_meta($id,$meta); else update_comment_meta($id,$meta,$v);
        }
    }
}
