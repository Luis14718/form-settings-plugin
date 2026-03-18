<?php
/**
 * Admin Interface Class
 * 
 * Handles the admin settings page and AJAX operations
 */

if (!defined('WPINC')) {
    die;
}

class Form_Settings_Admin
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // AJAX handlers
        add_action('wp_ajax_fs_add_recipient', array($this, 'ajax_add_recipient'));
        add_action('wp_ajax_fs_remove_recipient', array($this, 'ajax_remove_recipient'));
        add_action('wp_ajax_fs_scan_form_recipients', array($this, 'ajax_scan_form_recipients'));
        add_action('wp_ajax_fs_remove_form_recipient', array($this, 'ajax_remove_form_recipient'));
        add_action('wp_ajax_fs_scan_forms', array($this, 'ajax_scan_forms'));
        add_action('wp_ajax_fs_save_validation_rules', array($this, 'ajax_save_validation_rules'));
        add_action('wp_ajax_fs_save_settings', array($this, 'ajax_save_settings'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('Form Settings', 'form-settings'),
            __('Form Settings', 'form-settings'),
            'manage_options',
            'form-settings',
            array($this, 'render_admin_page'),
            'dashicons-forms',
            30
        );

        // Add Error Logs submenu
        add_submenu_page(
            'form-settings',
            __('Error Logs', 'form-settings'),
            __('Error Logs', 'form-settings'),
            'manage_options',
            'form-settings-error-logs',
            array($this, 'render_error_logs_page')
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        if ('toplevel_page_form-settings' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'form-settings-admin',
            FORM_SETTINGS_PLUGIN_URL . 'admin/css/form-settings-admin.css',
            array(),
            FORM_SETTINGS_VERSION
        );

        wp_enqueue_script(
            'form-settings-admin',
            FORM_SETTINGS_PLUGIN_URL . 'admin/js/form-settings-admin.js',
            array('jquery'),
            FORM_SETTINGS_VERSION,
            true
        );

        wp_localize_script(
            'form-settings-admin',
            'formSettingsAjax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('form_settings_nonce'),
                'validation_rules' => get_option('form_settings_validation_rules', array()),
            )
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'recipients';

        ?>
        <div class="wrap form-settings-wrap">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=form-settings&tab=recipients"
                    class="nav-tab <?php echo $active_tab === 'recipients' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Recipients', 'form-settings'); ?>
                </a>
                <a href="?page=form-settings&tab=validation"
                    class="nav-tab <?php echo $active_tab === 'validation' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Validation Rules', 'form-settings'); ?>
                </a>
                <a href="?page=form-settings&tab=scanner"
                    class="nav-tab <?php echo $active_tab === 'scanner' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Form Scanner', 'form-settings'); ?>
                </a>

                <a href="?page=form-settings&tab=settings"
                    class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'form-settings'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'recipients':
                        $this->render_recipients_tab();
                        break;
                    case 'validation':
                        $this->render_validation_tab();
                        break;
                    case 'scanner':
                        $this->render_scanner_tab();
                        break;

                    case 'settings':
                        $this->render_settings_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Recipients tab
     */
    private function render_recipients_tab()
    {
        $recipients_manager = new Form_Settings_Recipients_Manager();
        $recipients = $recipients_manager->get_recipients();
        ?>
        <div class="fs-tab-content">
            <h2>
                <?php _e('Global Recipients', 'form-settings'); ?>
            </h2>
            <p>
                <?php _e('Add email addresses that will be automatically added to all Contact Form 7 forms on your site.', 'form-settings'); ?>
            </p>

            <div class="fs-add-recipient">
                <input type="email" id="fs-new-recipient"
                    placeholder="<?php esc_attr_e('Enter email address', 'form-settings'); ?>" />
                <button type="button" class="button button-primary" id="fs-add-recipient-btn">
                    <?php _e('Add Recipient', 'form-settings'); ?>
                </button>
            </div>

            <div id="fs-recipient-message" class="fs-message"></div>

            <div class="fs-recipients-list">
                <h3>
                    <?php _e('Current Global Recipients', 'form-settings'); ?>
                </h3>
                <ul id="fs-recipients-container">
                    <?php if (empty($recipients)): ?>
                        <li class="fs-no-recipients">
                            <?php _e('No recipients added yet.', 'form-settings'); ?>
                        </li>
                    <?php else: ?>
                        <?php foreach ($recipients as $recipient): ?>
                            <li class="fs-recipient-item">
                                <span class="fs-recipient-email">
                                    <?php echo esc_html($recipient); ?>
                                </span>
                                <button type="button" class="button button-small fs-remove-recipient"
                                    data-email="<?php echo esc_attr($recipient); ?>">
                                    <?php _e('Remove', 'form-settings'); ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>

            <hr style="margin: 40px 0;" />

            <h2>
                <?php _e('Form-Specific Recipients', 'form-settings'); ?>
            </h2>
            <p>
                <?php _e('Scan all Contact Form 7 forms to see and manage recipients configured directly in each form.', 'form-settings'); ?>
            </p>

            <button type="button" class="button button-primary" id="fs-scan-recipients-btn">
                <?php _e('Scan Form Recipients', 'form-settings'); ?>
            </button>

            <div id="fs-form-recipients-results" class="fs-form-recipients-results"></div>
        </div>
        <?php
    }

    /**
     * Render Validation tab
     */
    private function render_validation_tab()
    {
        $validation_manager = new Form_Settings_Validation_Manager();
        $rules = $validation_manager->get_validation_rules();
        ?>
        <div class="fs-tab-content">
            <h2>
                <?php _e('Validation Rules', 'form-settings'); ?>
            </h2>
            <p>
                <?php _e('Set validation rules for form fields. First, scan your forms to see available fields.', 'form-settings'); ?>
            </p>

            <div id="fs-validation-message" class="fs-message"></div>

            <div class="fs-validation-rules">
                <button type="button" class="button" id="fs-load-fields-btn">
                    <?php _e('Load Form Fields', 'form-settings'); ?>
                </button>

                <div id="fs-validation-fields" class="fs-validation-fields-container">
                    <?php if (!empty($rules)): ?>
                        <form id="fs-validation-form">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Field Name', 'form-settings'); ?></th>
                                        <th><?php _e('Display Name', 'form-settings'); ?></th>
                                        <th><?php _e('Required', 'form-settings'); ?></th>
                                        <th><?php _e('Min Length', 'form-settings'); ?></th>
                                        <th><?php _e('Max Length', 'form-settings'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rules as $field_name => $rule): ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($field_name); ?></strong></td>
                                            <td>
                                                <input type="text" name="rules[<?php echo esc_attr($field_name); ?>][display_name]"
                                                    value="<?php echo isset($rule['display_name']) ? esc_attr($rule['display_name']) : ''; ?>"
                                                    placeholder="e.g. Full Name" class="regular-text" />
                                            </td>
                                            <td>
                                                <label class="fs-toggle">
                                                    <input type="checkbox" name="rules[<?php echo esc_attr($field_name); ?>][required]"
                                                        value="1" <?php checked(isset($rule['required']) && $rule['required']); ?> />
                                                    <span class="fs-toggle-slider"></span>
                                                </label>
                                            </td>
                                            <td>
                                                <input type="number" name="rules[<?php echo esc_attr($field_name); ?>][min_length]"
                                                    value="<?php echo isset($rule['min_length']) ? esc_attr($rule['min_length']) : ''; ?>"
                                                    min="0" class="small-text" />
                                            </td>
                                            <td>
                                                <input type="number" name="rules[<?php echo esc_attr($field_name); ?>][max_length]"
                                                    value="<?php echo isset($rule['max_length']) ? esc_attr($rule['max_length']) : ''; ?>"
                                                    min="0" class="small-text" />
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary">
                                    <?php _e('Save Validation Rules', 'form-settings'); ?>
                                </button>
                            </p>
                        </form>
                    <?php else: ?>
                        <p>
                            <?php _e('Click "Load Form Fields" to scan your forms and set up validation rules.', 'form-settings'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Scanner tab
     */
    private function render_scanner_tab()
    {
        ?>
        <div class="fs-tab-content">
            <h2>
                <?php _e('Form Field Scanner', 'form-settings'); ?>
            </h2>
            <p>
                <?php _e('Scan all Contact Form 7 forms to see what fields are being used across your site.', 'form-settings'); ?>
            </p>

            <button type="button" class="button button-primary" id="fs-scan-forms-btn">
                <?php _e('Scan All Forms', 'form-settings'); ?>
            </button>

            <div id="fs-scan-message" class="fs-message"></div>

            <div id="fs-scan-results" class="fs-scan-results"></div>
        </div>
        <?php
    }

    /**
     * Render Templates tab
     */
    /**
     * Render Settings tab
     */
    private function render_settings_tab()
    {
        $options = get_option('form_settings_options', array());
        $disable_copy_paste = isset($options['disable_copy_paste']) ? $options['disable_copy_paste'] : false;
        $validation_error_style = isset($options['validation_error_style']) ? $options['validation_error_style'] : 'tooltip';
        $disable_submit_on_loading = isset($options['disable_submit_on_loading']) ? $options['disable_submit_on_loading'] : false;
        ?>
        <div class="fs-tab-content">
            <h2>
                <?php _e('General Settings', 'form-settings'); ?>
            </h2>
            <p>
                <?php _e('Configure general settings for all Contact Form 7 forms.', 'form-settings'); ?>
            </p>

            <div id="fs-settings-message" class="fs-message"></div>

            <form id="fs-settings-form" method="post">
                <?php wp_nonce_field('form_settings_save_settings', 'form_settings_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fs-disable-copy-paste">
                                <?php _e('Disable Copy & Paste', 'form-settings'); ?>
                            </label>
                        </th>
                        <td>
                            <label class="fs-toggle">
                                <input type="checkbox" id="fs-disable-copy-paste" name="disable_copy_paste" value="1" <?php checked($disable_copy_paste, true); ?> />
                                <span class="fs-toggle-slider"></span>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, users will not be able to copy, paste, or cut text in form fields. This applies to all Contact Form 7 forms on your site.', 'form-settings'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fs-validation-error-style">
                                <?php _e('Validation Error Style', 'form-settings'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="fs-validation-error-style" name="validation_error_style">
                                <option value="tooltip" <?php selected($validation_error_style, 'tooltip'); ?>>
                                    <?php _e('Tooltip on Submit Button (Default)', 'form-settings'); ?>
                                </option>
                                <option value="inline" <?php selected($validation_error_style, 'inline'); ?>>
                                    <?php _e('Inline Under Each Field', 'form-settings'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Choose how to display missing required fields and validation errors when the submit button is disabled.', 'form-settings'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="fs-disable-submit-loading">
                                <?php _e('Disable Submit Button While Loading', 'form-settings'); ?>
                            </label>
                        </th>
                        <td>
                            <label class="fs-toggle">
                                <input type="checkbox" id="fs-disable-submit-loading" name="disable_submit_on_loading" value="1" <?php checked($disable_submit_on_loading, true); ?> />
                                <span class="fs-toggle-slider"></span>
                            </label>
                            <p class="description">
                                <?php _e('When enabled, the submit button is disabled and shows "Sending&hellip;" while the form is being processed. It re-enables automatically if validation fails, so the user can correct and resubmit.', 'form-settings'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Save Settings', 'form-settings'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX: Add recipient
     */
    public function ajax_add_recipient()
    {
        check_ajax_referer('form_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'form-settings')));
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($email)) {
            wp_send_json_error(array('message' => __('Email address is required.', 'form-settings')));
        }

        $recipients_manager = new Form_Settings_Recipients_Manager();
        $result = $recipients_manager->add_recipient($email);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Recipient added successfully.', 'form-settings'),
            'email' => $email
        ));
    }

    /**
     * AJAX: Remove recipient
     */
    public function ajax_remove_recipient()
    {
        check_ajax_referer('form_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'form-settings')));
        }

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        $recipients_manager = new Form_Settings_Recipients_Manager();
        $result = $recipients_manager->remove_recipient($email);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to remove recipient.', 'form-settings')));
        }

        wp_send_json_success(array('message' => __('Recipient removed successfully.', 'form-settings')));
    }

    /**
     * AJAX: Scan forms
     */
    public function ajax_scan_forms()
    {
        check_ajax_referer('form_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'form-settings')));
        }

        $scanner = new Form_Settings_Form_Scanner();
        $fields = $scanner->scan_all_forms();
        $stats = $scanner->get_statistics();

        wp_send_json_success(array(
            'fields' => $fields,
            'stats' => $stats
        ));
    }

    /**
     * AJAX: Save validation rules
     */
    public function ajax_save_validation_rules()
    {
        check_ajax_referer('form_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'form-settings')));
        }

        $rules = isset($_POST['rules']) ? $_POST['rules'] : array();

        // Sanitize rules
        $sanitized_rules = array();
        foreach ($rules as $field_name => $rule) {
            $sanitized_rules[sanitize_text_field($field_name)] = array(
                'required' => isset($rule['required']) && $rule['required'] === '1',
                'display_name' => isset($rule['display_name']) ? sanitize_text_field($rule['display_name']) : '',
                'min_length' => isset($rule['min_length']) && !empty($rule['min_length']) ? absint($rule['min_length']) : null,
                'max_length' => isset($rule['max_length']) && !empty($rule['max_length']) ? absint($rule['max_length']) : null,
            );
        }

        $validation_manager = new Form_Settings_Validation_Manager();
        $validation_manager->update_validation_rules($sanitized_rules);

        wp_send_json_success(array('message' => __('Validation rules saved successfully.', 'form-settings')));
    }



    /**
     * AJAX: Scan form recipients
     */
    public function ajax_scan_form_recipients()
    {
        check_ajax_referer('form_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'form-settings')));
        }

        $recipients_manager = new Form_Settings_Recipients_Manager();
        $form_recipients = $recipients_manager->scan_form_recipients();

        wp_send_json_success(array(
            'form_recipients' => $form_recipients
        ));
    }

    /**
     * AJAX: Remove recipient from specific form
     */
    public function ajax_remove_form_recipient()
    {
        check_ajax_referer('form_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'form-settings')));
        }

        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

        if (empty($form_id) || empty($email)) {
            wp_send_json_error(array('message' => __('Form ID and email are required.', 'form-settings')));
        }

        $recipients_manager = new Form_Settings_Recipients_Manager();
        $result = $recipients_manager->remove_form_recipient($form_id, $email);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => __('Recipient removed from form successfully.', 'form-settings')));
    }

    /**
     * AJAX: Save general settings
     */
    public function ajax_save_settings()
    {
        // Verify the nonce generated by wp_nonce_field('form_settings_save_settings', 'form_settings_nonce')
        check_ajax_referer('form_settings_save_settings', 'form_settings_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'form-settings')));
        }

        $options = get_option('form_settings_options', array());
        $options['disable_copy_paste'] = isset($_POST['disable_copy_paste']) && $_POST['disable_copy_paste'] === '1';
        $options['validation_error_style'] = isset($_POST['validation_error_style']) ? sanitize_text_field($_POST['validation_error_style']) : 'tooltip';
        $options['disable_submit_on_loading'] = isset($_POST['disable_submit_on_loading']) && $_POST['disable_submit_on_loading'] === '1';

        update_option('form_settings_options', $options);

        wp_send_json_success(array('message' => __('Settings saved successfully.', 'form-settings')));
    }

    /**
     * Render Error Logs page
     */
    public function render_error_logs_page()
    {
        include FORM_SETTINGS_PLUGIN_DIR . 'admin/error-log-viewer.php';
    }
}
