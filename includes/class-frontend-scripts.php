<?php
/**
 * Enqueue frontend scripts for copy/paste control
 */

if (!defined('WPINC')) {
    die;
}

// Add frontend script to disable copy/paste if option is enabled
function form_settings_enqueue_frontend_scripts()
{
    $options = get_option('form_settings_options', array());
    $disable_copy_paste = isset($options['disable_copy_paste']) && $options['disable_copy_paste'];

    if ($disable_copy_paste) {
        wp_add_inline_script('jquery', "
            jQuery(document).ready(function($) {
                // Disable copy, paste, and cut on all CF7 form fields
                $(document).on('paste cut copy', '.wpcf7-form input, .wpcf7-form textarea, .wpcf7-form select', function(e) {
                    e.preventDefault();
                    return false;
                });
                
                // Also prevent right-click context menu on form fields
                $(document).on('contextmenu', '.wpcf7-form input, .wpcf7-form textarea, .wpcf7-form select', function(e) {
                    e.preventDefault();
                    return false;
                });
            });
        ");
    }
}
add_action('wp_enqueue_scripts', 'form_settings_enqueue_frontend_scripts');
