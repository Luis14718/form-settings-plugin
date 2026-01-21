<?php
/**
 * Email Template Manager Class
 * 
 * Manages email templates for Contact Form 7 forms
 */

if (!defined('WPINC')) {
    die;
}

class Form_Settings_Email_Template_Manager
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Hook into CF7 mail components to apply templates
        add_filter('wpcf7_mail_components', array($this, 'apply_email_template'), 20, 3);
    }

    /**
     * Get all email templates
     * 
     * @return array Array of email templates
     */
    public function get_templates()
    {
        $templates = get_option('form_settings_email_templates', array());
        return is_array($templates) ? $templates : array();
    }

    /**
     * Get active template
     * 
     * @return array|null Active template or null
     */
    public function get_active_template()
    {
        $templates = $this->get_templates();

        foreach ($templates as $template) {
            if (isset($template['active']) && $template['active']) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Add or update template
     * 
     * @param array $template Template data
     * @return bool True on success
     */
    public function save_template($template)
    {
        $templates = $this->get_templates();

        // Generate ID if new template
        if (!isset($template['id'])) {
            $template['id'] = uniqid('template_');
            $template['created'] = current_time('mysql');
        }

        $template['modified'] = current_time('mysql');

        // If this template is being set as active, deactivate others
        if (isset($template['active']) && $template['active']) {
            foreach ($templates as $key => $existing_template) {
                $templates[$key]['active'] = false;
            }
        }

        // Find and update existing template or add new
        $found = false;
        foreach ($templates as $key => $existing_template) {
            if ($existing_template['id'] === $template['id']) {
                $templates[$key] = $template;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $templates[] = $template;
        }

        return update_option('form_settings_email_templates', $templates);
    }

    /**
     * Delete template
     * 
     * @param string $template_id Template ID
     * @return bool True on success
     */
    public function delete_template($template_id)
    {
        $templates = $this->get_templates();

        foreach ($templates as $key => $template) {
            if ($template['id'] === $template_id) {
                unset($templates[$key]);
                $templates = array_values($templates); // Re-index
                return update_option('form_settings_email_templates', $templates);
            }
        }

        return false;
    }

    /**
     * Set active template
     * 
     * @param string $template_id Template ID
     * @return bool True on success
     */
    public function set_active_template($template_id)
    {
        $templates = $this->get_templates();
        $found = false;

        foreach ($templates as $key => $template) {
            if ($template['id'] === $template_id) {
                $templates[$key]['active'] = true;
                $found = true;
            } else {
                $templates[$key]['active'] = false;
            }
        }

        if ($found) {
            return update_option('form_settings_email_templates', $templates);
        }

        return false;
    }

    /**
     * Apply email template to CF7 mail
     * 
     * @param array $components Mail components
     * @param object $cf7 Contact Form 7 object
     * @param object $mail_object Mail object
     * @return array Modified mail components
     */
    public function apply_email_template($components, $cf7, $mail_object)
    {
        $template = $this->get_active_template();

        if (!$template) {
            return $components;
        }

        // Apply subject template if set
        if (isset($template['subject']) && !empty($template['subject'])) {
            $components['subject'] = $template['subject'];
        }

        // Apply body template if set
        if (isset($template['body']) && !empty($template['body'])) {
            $components['body'] = $template['body'];
        }

        // Apply additional headers if set
        if (isset($template['additional_headers']) && !empty($template['additional_headers'])) {
            $existing_headers = isset($components['additional_headers']) ? $components['additional_headers'] : '';
            $components['additional_headers'] = $existing_headers . "\n" . $template['additional_headers'];
        }

        return $components;
    }

    /**
     * Get available mail tags for templates
     * 
     * @return array Array of available mail tags
     */
    public function get_available_mail_tags()
    {
        return array(
            '[your-name]' => 'Sender name',
            '[your-email]' => 'Sender email',
            '[your-subject]' => 'Email subject',
            '[your-message]' => 'Message content',
            '[your-phone]' => 'Phone number',
            '[_site_title]' => 'Site title',
            '[_site_url]' => 'Site URL',
            '[_site_admin_email]' => 'Admin email',
            '[_date]' => 'Submission date',
            '[_time]' => 'Submission time'
        );
    }
}
