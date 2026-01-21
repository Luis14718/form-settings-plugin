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
        add_action('wp_ajax_fs_save_email_template', array($this, 'ajax_save_email_template'));
        add_action('wp_ajax_fs_delete_email_template', array($this, 'ajax_delete_email_template'));
        add_action('wp_ajax_fs_set_active_template', array($this, 'ajax_set_active_template'));
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
                'nonce' => wp_create_nonce('form_settings_nonce')
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
                <a href="?page=form-settings&tab=templates"
                    class="nav-tab <?php echo $active_tab === 'templates' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Email Templates', 'form-settings'); ?>
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
                    case 'templates':
                        $this->render_templates_tab();
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
                                        <th>
                                            <?php _e('Field Name', 'form-settings'); ?>
                                        </th>
                                        <th>
                                            <?php _e('Required', 'form-settings'); ?>
                                        </th>
                                        <th>
                                            <?php _e('Min Length', 'form-settings'); ?>
                                        </th>
                                        <th>
                                            <?php _e('Max Length', 'form-settings'); ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rules as $field_name => $rule): ?>
                                        <tr>
                                            <td><strong>
                                                    <?php echo esc_html($field_name); ?>
                                                </strong></td>
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
    private function render_templates_tab()
    {
        $template_manager = new Form_Settings_Email_Template_Manager();
        $templates = $template_manager->get_templates();
        $active_template = $template_manager->get_active_template();
        $mail_tags = $template_manager->get_available_mail_tags();
        ?>
        <div class="fs-tab-content">
            <h2>
                <?php _e('Email Templates', 'form-settings'); ?>
            </h2>
            <p>
                <?php _e('Create email templates that will be applied to all Contact Form 7 forms.', 'form-settings'); ?>
            </p>

            <div id="fs-template-message" class="fs-message"></div>

            <div class="fs-template-editor">
                <h3>
                    <?php _e('Create/Edit Template', 'form-settings'); ?>
                </h3>
                <form id="fs-template-form">
                    <input type="hidden" id="fs-template-id" name="template_id" value="" />

                    <table class="form-table">
                        <tr>
                            <th><label for="fs-template-name">
                                    <?php _e('Template Name', 'form-settings'); ?>
                                </label></th>
                            <td><input type="text" id="fs-template-name" name="template_name" class="regular-text" required />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="fs-template-subject">
                                    <?php _e('Email Subject', 'form-settings'); ?>
                                </label></th>
                            <td><input type="text" id="fs-template-subject" name="template_subject" class="large-text" /></td>
                        </tr>
                        <tr>
                            <th><label for="fs-template-body">
                                    <?php _e('Email Body', 'form-settings'); ?>
                                </label></th>
                            <td>
                                <textarea id="fs-template-body" name="template_body" rows="10" class="large-text"></textarea>
                                <p class="description">
                                    <?php _e('Available mail tags:', 'form-settings'); ?>
                                    <?php foreach ($mail_tags as $tag => $description): ?>
                                        <code><?php echo esc_html($tag); ?></code>
                                    <?php endforeach; ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="fs-template-active">
                                    <?php _e('Set as Active', 'form-settings'); ?>
                                </label></th>
                            <td>
                                <label class="fs-toggle">
                                    <input type="checkbox" id="fs-template-active" name="template_active" value="1" />
                                    <span class="fs-toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php _e('Save Template', 'form-settings'); ?>
                        </button>
                        <button type="button" class="button" id="fs-reset-template-form">
                            <?php _e('Reset Form', 'form-settings'); ?>
                        </button>
                    </p>
                </form>
            </div>

            <div class="fs-templates-list">
                <h3>
                    <?php _e('Saved Templates', 'form-settings'); ?>
                </h3>
                <div id="fs-templates-container">
                    <?php if (empty($templates)): ?>
                        <p>
                            <?php _e('No templates created yet.', 'form-settings'); ?>
                        </p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>
                                        <?php _e('Template Name', 'form-settings'); ?>
                                    </th>
                                    <th>
                                        <?php _e('Status', 'form-settings'); ?>
                                    </th>
                                    <th>
                                        <?php _e('Modified', 'form-settings'); ?>
                                    </th>
                                    <th>
                                        <?php _e('Actions', 'form-settings'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $template): ?>
                                    <tr>
                                        <td><strong>
                                                <?php echo esc_html($template['name']); ?>
                                            </strong></td>
                                        <td>
                                            <?php if (isset($template['active']) && $template['active']): ?>
                                                <span class="fs-badge fs-badge-active">
                                                    <?php _e('Active', 'form-settings'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="fs-badge">
                                                    <?php _e('Inactive', 'form-settings'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo esc_html(isset($template['modified']) ? $template['modified'] : '-'); ?>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small fs-edit-template"
                                                data-template='<?php echo esc_attr(json_encode($template)); ?>'>
                                                <?php _e('Edit', 'form-settings'); ?>
                                            </button>
                                            <?php if (!isset($template['active']) || !$template['active']): ?>
                                                <button type="button" class="button button-small fs-activate-template"
                                                    data-id="<?php echo esc_attr($template['id']); ?>">
                                                    <?php _e('Activate', 'form-settings'); ?>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="button button-small fs-delete-template"
                                                data-id="<?php echo esc_attr($template['id']); ?>">
                                                <?php _e('Delete', 'form-settings'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render Settings tab
     */
    private function render_settings_tab()
    {
        $options = get_option('form_settings_options', array());
        $disable_copy_paste = isset($options['disable_copy_paste']) ? $options['disable_copy_paste'] : false;
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
                'min_length' => isset($rule['min_length']) && !empty($rule['min_length']) ? absint($rule['min_length']) : null,
                'max_length' => isset($rule['max_length']) && !empty($rule['max_length']) ? absint($rule['max_length']) : null
            );
        }

        $validation_manager = new Form_Settings_Validation_Manager();
        $validation_manager->update_validation_rules($sanitized_rules);

        wp_send_json_success(array('message' => __('Validation rules saved successfully.', 'form-settings')));
    }

    /**
     * AJAX: Save email template
     */
    public function ajax_save_email_template()
    {
        check_ajax_referer('form_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'form-settings')));
        }

        $template = array(
            'id' => isset($_POST['template_id']) && !empty($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : null,
            'name' => isset($_POST['template_name']) ? sanitize_text_field($_POST['template_name']) : '',
            'subject' => isset($_POST['template_subject']) ? sanitize_text_field($_POST['template_subject']) : '',
            'body' => isset($_POST['template_body']) ? wp_kses_post($_POST['template_body']) : '',
            'active' => isset($_POST['template_active']) && $_POST['template_active'] === '1'
        );

        if (empty($template['name'])) {
            wp_send_json_error(array('message' => __('Template name is required.', 'form-settings')));
        }

        $template_manager = new Form_Settings_Email_Template_Manager();
        $template_manager->save_template($template);

        wp_send_json_success(array('message' => __('Template saved successfully.', 'form-settings')));
    }

    /**
     * AJAX: Delete email template
     */
    public function ajax_delete_email_template()
    {
        check_ajax_referer('form_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'form-settings')));
        }

        $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '';

        $template_manager = new Form_Settings_Email_Template_Manager();
        $result = $template_manager->delete_template($template_id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to delete template.', 'form-settings')));
        }

        wp_send_json_success(array('message' => __('Template deleted successfully.', 'form-settings')));
    }

    /**
     * AJAX: Set active template
     */
    public function ajax_set_active_template()
    {
        check_ajax_referer('form_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'form-settings')));
        }

        $template_id = isset($_POST['template_id']) ? sanitize_text_field($_POST['template_id']) : '';

        $template_manager = new Form_Settings_Email_Template_Manager();
        $result = $template_manager->set_active_template($template_id);

        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to activate template.', 'form-settings')));
        }

        wp_send_json_success(array('message' => __('Template activated successfully.', 'form-settings')));
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
        check_ajax_referer('form_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'form-settings')));
        }

        $options = get_option('form_settings_options', array());
        $options['disable_copy_paste'] = isset($_POST['disable_copy_paste']) && $_POST['disable_copy_paste'] === '1';

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
