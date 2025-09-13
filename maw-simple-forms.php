<?php
/**
 * Plugin Name: MAW Simple Forms
 * Description: Simple, ad-free form plugin. Build fields, receive entries, email notifications, and manage status (Completed/Trash).
 * Version: 0.1.2
 * Author: MA Webb
 * Text Domain: maw-simple-forms
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/*
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, see https://www.gnu.org/licenses/
 */


if (!defined('ABSPATH')) exit;

class MAW_Simple_Forms {
    private static $instance = null;
    private $table;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'maw_form_entries';

        // i18n
        add_action('plugins_loaded', function () {
            load_plugin_textdomain('maw-simple-forms', false, dirname(plugin_basename(__FILE__)) . '/languages');
        });

        // Hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('init', [$this, 'register_cpt']);
        add_action('add_meta_boxes', [$this, 'add_form_metaboxes']);
        add_action('save_post_maw_form', [$this, 'save_form_meta']);
        add_shortcode('maw_form', [$this, 'shortcode_form']);
        add_action('init', [$this, 'maybe_handle_submission']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_post_maw_entry_action', [$this, 'handle_admin_entry_action']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);

        // Gutenberg block (manual registration; no build step needed)
        add_action('init', [$this, 'register_blocks']);
    }

    /** Activation: create table */
    public function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'new',
            data LONGTEXT NOT NULL,
            ip VARCHAR(64) DEFAULT '',
            user_agent TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY status (status)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /** CPT: Forms */
    public function register_cpt() {
        $labels = [
            'name'               => __('Forms', 'maw-simple-forms'),
            'singular_name'      => __('Form', 'maw-simple-forms'),
            'add_new'            => __('Add New', 'maw-simple-forms'),
            'add_new_item'       => __('Add New Form', 'maw-simple-forms'),
            'edit_item'          => __('Edit Form', 'maw-simple-forms'),
            'new_item'           => __('New Form', 'maw-simple-forms'),
            'view_item'          => __('View Form', 'maw-simple-forms'),
            'search_items'       => __('Search Forms', 'maw-simple-forms'),
            'not_found'          => __('No forms found', 'maw-simple-forms'),
            'menu_name'          => __('Forms', 'maw-simple-forms'),
        ];
        register_post_type('maw_form', [
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // we'll put it under our own menu
            'supports' => ['title','slug'],
            'has_archive' => false,
            'show_in_rest' => true,
        ]);
    }

    /** Admin menu */
    public function admin_menu() {
        add_menu_page(
            __('MAW Forms', 'maw-simple-forms'),
            __('MAW Forms', 'maw-simple-forms'),
            'manage_options',
            'maw_forms_root',
            [$this, 'render_forms_landing'],
            'dashicons-feedback',
            25
        );

        add_submenu_page('maw_forms_root', __('Forms', 'maw-simple-forms'), __('Forms', 'maw-simple-forms'),
            'manage_options', 'edit.php?post_type=maw_form');

        add_submenu_page('maw_forms_root', __('Add New', 'maw-simple-forms'), __('Add New', 'maw-simple-forms'),
            'manage_options', 'post-new.php?post_type=maw_form');

        add_submenu_page('maw_forms_root', __('Entries', 'maw-simple-forms'), __('Entries', 'maw-simple-forms'),
            'manage_options', 'maw_entries', [$this, 'render_entries_page']);
    }

    public function render_forms_landing() {
        echo '<div class="wrap"><h1>'.esc_html__('MAW Forms', 'maw-simple-forms').'</h1><p>'.
            esc_html__('Create forms, place the shortcode on a page, and manage entries here.', 'maw-simple-forms').
            '</p></div>';
    }

    /** Metaboxes */
    public function add_form_metaboxes() {
        add_meta_box('maw_form_fields', __('Form Fields', 'maw-simple-forms'),
            [$this, 'metabox_fields'], 'maw_form', 'normal', 'high');
        add_meta_box('maw_form_settings', __('Settings', 'maw-simple-forms'),
            [$this, 'metabox_settings'], 'maw_form', 'side', 'default');
    }

    public function metabox_fields($post) {
        $fields = get_post_meta($post->ID, '_maw_fields', true);
        if (!is_array($fields)) $fields = [];
        wp_nonce_field('maw_save_form', 'maw_form_nonce');
        ?>
        <style>
            .maw-table { width:100%; border-collapse: collapse; }
            .maw-table th, .maw-table td { border:1px solid #ddd; padding:6px; }
            .maw-repeater-actions { margin-top:8px; }
        </style>
        <table class="maw-table" id="maw-fields-table">
            <thead>
                <tr>
                    <th><?php _e('Label', 'maw-simple-forms'); ?></th>
                    <th><?php _e('Name (slug)', 'maw-simple-forms'); ?></th>
                    <th><?php _e('Type', 'maw-simple-forms'); ?></th>
                    <th><?php _e('Required', 'maw-simple-forms'); ?></th>
                    <th><?php _e('Remove', 'maw-simple-forms'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($fields)) : foreach ($fields as $i => $f): ?>
                <tr>
                    <td><input type="text" name="maw_fields[<?php echo $i; ?>][label]" value="<?php echo esc_attr($f['label'] ?? ''); ?>" class="regular-text"></td>
                    <td><input type="text" name="maw_fields[<?php echo $i; ?>][name]" value="<?php echo esc_attr($f['name'] ?? ''); ?>" class="regular-text"></td>
                    <td>
                        <?php $type = $f['type'] ?? 'text'; ?>
                        <select name="maw_fields[<?php echo $i; ?>][type]">
                            <option value="text" <?php selected($type,'text'); ?>><?php esc_html_e('Text','maw-simple-forms'); ?></option>
                            <option value="email" <?php selected($type,'email'); ?>><?php esc_html_e('Email','maw-simple-forms'); ?></option>
                            <option value="tel" <?php selected($type,'tel'); ?>><?php esc_html_e('Phone','maw-simple-forms'); ?></option>
                            <option value="textarea" <?php selected($type,'textarea'); ?>><?php esc_html_e('Textarea','maw-simple-forms'); ?></option>
                        </select>
                    </td>
                    <td><input type="checkbox" name="maw_fields[<?php echo $i; ?>][required]" <?php checked(!empty($f['req'])); ?>></td>
                    <td><button class="button maw-remove-row" type="button">✖</button></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        <div class="maw-repeater-actions">
            <button type="button" class="button" id="maw-add-row">+ <?php esc_html_e('Add field','maw-simple-forms'); ?></button>
        </div>
        <script>
        (function(){
            const table = document.getElementById('maw-fields-table').querySelector('tbody');
            const addBtn = document.getElementById('maw-add-row');
            function row(idx){
                return `
                <tr>
                    <td><input type="text" name="maw_fields[${idx}][label]" class="regular-text"></td>
                    <td><input type="text" name="maw_fields[${idx}][name]" class="regular-text"></td>
                    <td>
                        <select name="maw_fields[${idx}][type]">
                            <option value="text"><?php echo esc_js(__('Text','maw-simple-forms')); ?></option>
                            <option value="email"><?php echo esc_js(__('Email','maw-simple-forms')); ?></option>
                            <option value="tel"><?php echo esc_js(__('Phone','maw-simple-forms')); ?></option>
                            <option value="textarea"><?php echo esc_js(__('Textarea','maw-simple-forms')); ?></option>
                        </select>
                    </td>
                    <td><input type="checkbox" name="maw_fields[${idx}][required]"></td>
                    <td><button class="button maw-remove-row" type="button">✖</button></td>
                </tr>`;
            }
            addBtn.addEventListener('click', () => {
                const idx = table.querySelectorAll('tr').length;
                table.insertAdjacentHTML('beforeend', row(idx));
            });
            table.addEventListener('click', (e) => {
                if (e.target.classList.contains('maw-remove-row')) {
                    e.target.closest('tr').remove();
                }
            });
        })();
        </script>
        <?php
    }

    public function metabox_settings($post) {
        $email = get_post_meta($post->ID, '_maw_notify_email', true);
        $success = get_post_meta($post->ID, '_maw_success_message', true);
        if (!$success) $success = __('Thanks! Your message has been sent.', 'maw-simple-forms');
        ?>
        <p><label for="maw_notify_email"><strong><?php _e('Recipient email', 'maw-simple-forms'); ?></strong></label></p>
        <input type="email" name="maw_notify_email" id="maw_notify_email" class="regular-text" value="<?php echo esc_attr($email); ?>" placeholder="you@example.com">
        <p><label for="maw_success_message"><strong><?php _e('Success message', 'maw-simple-forms'); ?></strong></label></p>
        <textarea name="maw_success_message" id="maw_success_message" class="large-text" rows="3"><?php echo esc_textarea($success); ?></textarea>
        
        <?php
    }

    /** Save meta */
    public function save_form_meta($post_id) {
        if (!isset($_POST['maw_form_nonce']) || !wp_verify_nonce($_POST['maw_form_nonce'], 'maw_save_form')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Fields
        $fields = isset($_POST['maw_fields']) ? (array) $_POST['maw_fields'] : [];
        $clean = [];
        foreach ($fields as $f) {
            $label = sanitize_text_field($f['label'] ?? '');
            $name  = sanitize_title($f['name'] ?? '');
            $type  = in_array(($f['type'] ?? 'text'), ['text','email','tel','textarea']) ? $f['type'] : 'text';
            $req   = !empty($f['required']) ? 1 : 0;
            if ($name) {
                $clean[] = compact('label','name','type','req');
            }
        }
        update_post_meta($post_id, '_maw_fields', $clean);

        // Settings
        $email = sanitize_email($_POST['maw_notify_email'] ?? '');
        $success = sanitize_textarea_field($_POST['maw_success_message'] ?? '');
        update_post_meta($post_id, '_maw_notify_email', $email);
        update_post_meta($post_id, '_maw_success_message', $success);
    }

    /** Shortcode (also used by block render) */
    public function shortcode_form($atts) {
        $atts = shortcode_atts(['id' => 0, 'slug' => ''], $atts, 'maw_form');
        $form_id = 0;
        if ($atts['id']) {
            $form_id = intval($atts['id']);
        } elseif ($atts['slug']) {
            $post = get_page_by_path(sanitize_title($atts['slug']), OBJECT, 'maw_form');
            if ($post) $form_id = $post->ID;
        }
        if (!$form_id) return '<div class="maw-form-error">'.esc_html__('Form not found.', 'maw-simple-forms').'</div>';

        $fields = get_post_meta($form_id, '_maw_fields', true);
        if (empty($fields)) return '<div class="maw-form-error">'.esc_html__('This form has no fields yet.', 'maw-simple-forms').'</div>';

        $success = isset($_GET['maw_success']) && intval($_GET['maw_success']) === $form_id;

        // Optional: allow themes/plugins to enqueue Bootstrap automatically here.
        if (apply_filters('maw_forms_enqueue_bootstrap', false) && !wp_style_is('bootstrap-5', 'enqueued')) {
            wp_register_style('bootstrap-5', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css', [], '5.3.3');
            wp_enqueue_style('bootstrap-5');
        }
        
        $wrapper_id = 'maw-form-' . $form_id;

        ob_start();
        if ($success) {
            $msg = get_post_meta($form_id, '_maw_success_message', true);
            echo '<div class="alert alert-success mb-4">'.esc_html($msg ?: __('Thanks! Your message has been sent.', 'maw-simple-forms')).'</div>';
        }
        $current_url = ( is_ssl() ? 'https://' : 'http://' ) . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');

        echo '<form method="post" id="'.esc_attr($wrapper_id).'" class="maw-form">';
        wp_nonce_field('maw_submit_'.$form_id, '_maw_nonce');
        echo '<input type="hidden" name="maw_form_id" value="'.intval($form_id).'">';
        echo '<input type="hidden" name="maw_redirect" value="'.esc_url($current_url).'">';
        // Honeypot
        echo '<div class="position-absolute" style="left:-9999px;top:-9999px;">';
        echo '<label>Do not fill this field</label>';
        echo '<input type="text" name="company" value="">';
        echo '</div>';

        foreach ($fields as $f) {
            $label = esc_html($f['label']);
            $name  = esc_attr($f['name']);
            $type  = $f['type'];
            $req   = !empty($f['req']);
            $required_attr = $req ? 'required' : '';

            echo '<div class="mb-3">';
            echo '<label for="maw_'.$name.'" class="form-label">'.$label.($req ? ' <span class="text-danger">*</span>' : '').'</label>';

            if ($type === 'textarea') {
                echo '<textarea id="maw_'.$name.'" name="'.$name.'" '.$required_attr.' rows="5" class="form-control"></textarea>';
            } else {
                $html_type = in_array($type,['text','email','tel']) ? $type : 'text';
                echo '<input id="maw_'.$name.'" type="'.$html_type.'" name="'.$name.'" '.$required_attr.' class="form-control">';
            }

            echo '</div>';
        }

        echo '<button type="submit" class="btn btn-primary">'.esc_html__('Send', 'maw-simple-forms').'</button>';
        echo '</form>';

        return ob_get_clean();
    }

    /** Handle submission */
    public function maybe_handle_submission() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        $form_id = isset($_POST['maw_form_id']) ? intval($_POST['maw_form_id']) : 0;
        if (!$form_id) return;
        if (!isset($_POST['_maw_nonce']) || !wp_verify_nonce($_POST['_maw_nonce'], 'maw_submit_'.$form_id)) return;

        // Honeypot
        if (!empty($_POST['company'])) return;

        $fields = get_post_meta($form_id, '_maw_fields', true);
        if (empty($fields)) return;

        $clean = [];
        foreach ($fields as $f) {
            $name = $f['name'];
            $req  = !empty($f['req']);
            $type = $f['type'];
            $val  = isset($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
            $val  = is_string($val) ? trim($val) : '';

            if ($type === 'email') {
                $val = sanitize_email($val);
                if ($req && !is_email($val)) {
                    wp_die(__('Invalid email address.', 'maw-simple-forms'));
                }
            } else {
                $val = sanitize_textarea_field($val);
                if ($req && $val === '') {
                    wp_die(__('A required field is missing.', 'maw-simple-forms'));
                }
            }
            $clean[$name] = $val;
        }

        // Save entry
        global $wpdb;
        $wpdb->insert($this->table, [
            'form_id' => $form_id,
            'status'  => 'new',
            'data'    => wp_json_encode($clean),
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql'),
        ], ['%d','%s','%s','%s','%s','%s']);

        // Send email
        $to = get_post_meta($form_id, '_maw_notify_email', true);
        if ($to && is_email($to)) {
            /* translators: %s: form title */
            $subject = sprintf(__('New submission: %s', 'maw-simple-forms'), get_the_title($form_id));
            $fields_conf = get_post_meta($form_id, '_maw_fields', true);
            $lines = [];
            foreach ($fields_conf as $f) {
                $label = $f['label'];
                $name  = $f['name'];
                $lines[] = $label . ': ' . (isset($clean[$name]) ? $clean[$name] : '');
            }
            $message = implode("\n", $lines) . "\n\nIP: ".($_SERVER['REMOTE_ADDR'] ?? '');
            $headers = ['Content-Type: text/plain; charset=UTF-8'];
            wp_mail($to, $subject, $message, $headers);
        }

        // Redirect with success
        $redirect = isset($_POST['maw_redirect']) ? esc_url_raw($_POST['maw_redirect']) : '';
        if (!$redirect) {
            $redirect = wp_get_referer();
        }
        if (!$redirect) {
            // last fallback if nothing else was available
            $redirect = home_url('/');
        }

        //Clear any old param/anchor, add new one
        $redirect = remove_query_arg('maw_success', $redirect);
        $redirect = explode('#', $redirect, 2)[0]; //remove old hash
        $redirect = add_query_arg(['maw_success' => $form_id], $redirect);
        // Add anchors so the page jumps to the form after redirect
        $redirect .= '#maw-form-' . $form_id;

        // Allow devs to adjust via filters if they want
        $redirect = apply_filters('maw_forms_redirect', $redirect, $form_id);

        wp_safe_redirect($redirect);
        exit;
    }

    /** Admin – Entries */
    public function render_entries_page() {
        if (!current_user_can('manage_options')) return;
        global $wpdb;

        $status = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'new';
        if (!in_array($status, ['new','completed','trash','all'])) $status = 'new';

        $where = '1=1';
        if ($status !== 'all') {
            $where .= $wpdb->prepare(' AND status = %s', $status);
        }

        $entries = $wpdb->get_results("SELECT * FROM {$this->table} WHERE $where ORDER BY created_at DESC LIMIT 200");

        $counts = [
            'all'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}"),
            'new'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status='new'"),
            'completed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status='completed'"),
            'trash'     => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table} WHERE status='trash'"),
        ];

        ?>
        <div class="wrap">
            <h1><?php _e('Entries', 'maw-simple-forms'); ?></h1>
            <ul class="subsubsub">
                <?php
                $links = [
                    'all' => __('All', 'maw-simple-forms'),
                    'new' => __('New', 'maw-simple-forms'),
                    'completed' => __('Completed', 'maw-simple-forms'),
                    'trash' => __('Trash', 'maw-simple-forms'),
                ];
                $base = admin_url('admin.php?page=maw_entries');
                $i=0;
                foreach ($links as $st => $label) {
                    $url = add_query_arg(['status'=>$st], $base);
                    $class = $status===$st ? 'class="current"' : '';
                    echo "<li><a $class href='".esc_url($url)."'>".esc_html($label)." <span class='count'>({$counts[$st]})</span></a>";
                    echo (++$i<count($links)) ? ' | ' : '';
                    echo "</li>";
                }
                ?>
            </ul>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'maw-simple-forms'); ?></th>
                        <th><?php _e('Date', 'maw-simple-forms'); ?></th>
                        <th><?php _e('Form', 'maw-simple-forms'); ?></th>
                        <th><?php _e('Data', 'maw-simple-forms'); ?></th>
                        <th><?php _e('Status', 'maw-simple-forms'); ?></th>
                        <th><?php _e('Actions', 'maw-simple-forms'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($entries): foreach ($entries as $e):
                        $data = json_decode($e->data, true) ?: [];
                        $form_title = get_the_title($e->form_id) ?: ('#'.$e->form_id);
                        ?>
                        <tr>
                            <td><?php echo intval($e->id); ?></td>
                            <td><?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($e->created_at))); ?></td>
                            <td><?php echo esc_html($form_title); ?></td>
                            <td>
                                <?php
                                echo '<details><summary>'.esc_html__('Show','maw-simple-forms').'</summary><pre style="white-space:pre-wrap">';
                                foreach ($data as $k=>$v) {
                                    echo esc_html("$k: $v") . "\n";
                                }
                                echo '</pre></details>';
                                ?>
                            </td>
                            <td><?php echo esc_html($e->status); ?></td>
                            <td>
                                <?php
                                $nonce = wp_create_nonce('maw_entry_action_'.$e->id);
                                $base = admin_url('admin-post.php');
                                if ($e->status !== 'completed') {
                                    $url1 = add_query_arg(['action'=>'maw_entry_action','id'=>$e->id,'do'=>'completed','_wpnonce'=>$nonce], $base);
                                    echo '<a class="button" href="'.esc_url($url1).'">'.__('Completed','maw-simple-forms').'</a> ';
                                }
                                if ($e->status !== 'trash') {
                                    $url2 = add_query_arg(['action'=>'maw_entry_action','id'=>$e->id,'do'=>'trash','_wpnonce'=>$nonce], $base);
                                    echo '<a class="button" href="'.esc_url($url2).'">'.__('Move to Trash','maw-simple-forms').'</a> ';
                                }
                                $url3 = add_query_arg(['action'=>'maw_entry_action','id'=>$e->id,'do'=>'delete','_wpnonce'=>$nonce], $base);
                                echo '<a class="button button-link-delete" href="'.esc_url($url3).'" onclick="return confirm(\''.esc_js(__('Delete permanently?', 'maw-simple-forms')).'\')">'.__('Delete','maw-simple-forms').'</a>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6"><?php _e('No entries.', 'maw-simple-forms'); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /** Admin actions on entries */
    public function handle_admin_entry_action() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $do = isset($_GET['do']) ? sanitize_key($_GET['do']) : '';
        if (!$id || !in_array($do,['completed','trash','delete'])) wp_die('Bad request');
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'maw_entry_action_'.$id)) wp_die('Invalid nonce');

        global $wpdb;
        if ($do === 'delete') {
            $wpdb->delete($this->table, ['id'=>$id], ['%d']);
        } else {
            $wpdb->update($this->table, ['status'=>$do], ['id'=>$id], ['%s'], ['%d']);
        }
        wp_safe_redirect(admin_url('admin.php?page=maw_entries'));
        exit;
    }

    /** Admin CSS (minor) */
    public function admin_assets($hook) {
        $css = '.widefat pre{margin:0}.subsubsub .count{color:#666}';
        if (strpos($hook, 'maw_entries') !== false) {
            wp_add_inline_style('wp-admin', '.widefat pre{margin:0}.subsubsub .count{color:#666}');
        }
        if (function_exists('get_current_screen')) {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'maw_form') {
            $css .= '
            
            #maw_form_settings .inside input.regular-text,
            #maw_form_settings .inside textarea.large-text {
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box;
            }
           
            #maw_form_fields .maw-table input[type="text"],
            #maw_form_fields .maw-table select {
                width: 100%;
                box-sizing: border-box;
            }';
        }
    }

    // Add CSS in admin
    wp_add_inline_style('wp-admin', $css);
    }

    /** Gutenberg block: manual registration (no build) */
    public function register_blocks() {
        $js  = plugins_url('blocks/maw-form/index.js', __FILE__);
        $css = plugins_url('blocks/maw-form/editor.css', __FILE__);

        wp_register_script(
            'maw-form-block',
            $js,
            ['wp-blocks','wp-i18n','wp-element','wp-block-editor','wp-components','wp-data'],
            filemtime(plugin_dir_path(__FILE__) . 'blocks/maw-form/index.js'),
            true
        );

        // JS translations
        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('maw-form-block', 'maw-simple-forms', plugin_dir_path(__FILE__) . 'languages');
        }

        wp_register_style(
            'maw-form-editor',
            $css,
            [],
            filemtime(plugin_dir_path(__FILE__) . 'blocks/maw-form/editor.css')
        );

        register_block_type('mawebb/maw-form', [
            'api_version'     => 3,
            'editor_script'   => 'maw-form-block',
            'editor_style'    => 'maw-form-editor',
            'render_callback' => function($attributes) {
                $form_id = isset($attributes['formId']) ? intval($attributes['formId']) : 0;
                if ($form_id <= 0) {
                    return '<div class="maw-form-error">'.esc_html__('No form selected.', 'maw-simple-forms').'</div>';
                }
                return $this->shortcode_form(['id' => $form_id]);
            },
            'attributes'      => [
                'formId' => ['type'=>'number','default'=>0],
            ],
        ]);
    }
}

MAW_Simple_Forms::instance();
