<?php
/**
 * Plugin Name: Form Settings
 * Plugin URI: https://bsd.com
 * Description: Centralized management for Contact Form 7 forms including global recipients, validation rules, form field scanning, and email templates.
 * Version: 1.0.0
 * Author: BSD
 * Author URI: https://bsd.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: form-settings
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('FORM_SETTINGS_VERSION', '1.0.0');
define('FORM_SETTINGS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FORM_SETTINGS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FORM_SETTINGS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if Contact Form 7 is active
 */
function form_settings_check_cf7_dependency()
{
    if (!is_plugin_active('contact-form-7/wp-contact-form-7.php') && !function_exists('wpcf7')) {
        deactivate_plugins(FORM_SETTINGS_PLUGIN_BASENAME);
        wp_die(
            __('Form Settings requires Contact Form 7 to be installed and activated. Please install Contact Form 7 first.', 'form-settings'),
            __('Plugin Dependency Error', 'form-settings'),
            array('back_link' => true)
        );
    }
}

/**
 * Activation hook
 */
function form_settings_activate()
{
    // Check for CF7 dependency
    if (!function_exists('wpcf7')) {
        deactivate_plugins(FORM_SETTINGS_PLUGIN_BASENAME);
        wp_die(
            __('Form Settings requires Contact Form 7 to be installed and activated. Please install Contact Form 7 first.', 'form-settings'),
            __('Plugin Dependency Error', 'form-settings'),
            array('back_link' => true)
        );
    }

    // Set default options
    add_option('form_settings_recipients', array());
    add_option('form_settings_validation_rules', array());
    add_option('form_settings_email_templates', array());
    add_option('form_settings_options', array(
        'version' => FORM_SETTINGS_VERSION,
        'installed_date' => current_time('mysql')
    ));
}
register_activation_hook(__FILE__, 'form_settings_activate');

/**
 * Deactivation hook
 */
function form_settings_deactivate()
{
    // Cleanup if needed (currently nothing to clean up)
}
register_deactivation_hook(__FILE__, 'form_settings_deactivate');

/**
 * Load plugin classes
 */
function form_settings_load_classes()
{
    require_once FORM_SETTINGS_PLUGIN_DIR . 'includes/class-recipients-manager.php';
    require_once FORM_SETTINGS_PLUGIN_DIR . 'includes/class-validation-manager.php';
    require_once FORM_SETTINGS_PLUGIN_DIR . 'includes/class-form-scanner.php';
    require_once FORM_SETTINGS_PLUGIN_DIR . 'includes/class-email-template-manager.php';
    require_once FORM_SETTINGS_PLUGIN_DIR . 'includes/class-error-logger.php';
    require_once FORM_SETTINGS_PLUGIN_DIR . 'includes/class-frontend-scripts.php';
    require_once FORM_SETTINGS_PLUGIN_DIR . 'admin/class-form-settings-admin.php';
}
add_action('plugins_loaded', 'form_settings_load_classes');

/**
 * Initialize plugin
 */
function form_settings_init()
{
    // Check CF7 dependency on every admin page load
    if (is_admin()) {
        form_settings_check_cf7_dependency();
    }

    // Initialize managers
    if (class_exists('Form_Settings_Recipients_Manager')) {
        new Form_Settings_Recipients_Manager();
    }

    if (class_exists('Form_Settings_Validation_Manager')) {
        new Form_Settings_Validation_Manager();
    }

    if (class_exists('Form_Settings_Email_Template_Manager')) {
        new Form_Settings_Email_Template_Manager();
    }

    if (class_exists('Form_Settings_Error_Logger')) {
        new Form_Settings_Error_Logger();
    }

    // Initialize admin interface
    if (is_admin() && class_exists('Form_Settings_Admin')) {
        new Form_Settings_Admin();
    }
}
add_action('plugins_loaded', 'form_settings_init');
