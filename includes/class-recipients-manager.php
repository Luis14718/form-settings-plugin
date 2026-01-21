<?php
/**
 * Recipients Manager Class
 * 
 * Manages global recipients for Contact Form 7 forms
 */

if (!defined('WPINC')) {
    die;
}

class Form_Settings_Recipients_Manager
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Hook into CF7 mail components to add global recipients
        add_filter('wpcf7_mail_components', array($this, 'add_global_recipients'), 10, 3);
    }

    /**
     * Get all global recipients
     * 
     * @return array Array of email addresses
     */
    public function get_recipients()
    {
        $recipients = get_option('form_settings_recipients', array());
        return is_array($recipients) ? $recipients : array();
    }

    /**
     * Add a recipient
     * 
     * @param string $email Email address to add
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function add_recipient($email)
    {
        // Validate email
        if (!is_email($email)) {
            return new WP_Error('invalid_email', __('Invalid email address.', 'form-settings'));
        }

        $recipients = $this->get_recipients();

        // Check if email already exists
        if (in_array($email, $recipients)) {
            return new WP_Error('duplicate_email', __('Email address already exists.', 'form-settings'));
        }

        $recipients[] = $email;
        update_option('form_settings_recipients', $recipients);

        return true;
    }

    /**
     * Remove a recipient
     * 
     * @param string $email Email address to remove
     * @return bool True on success, false on failure
     */
    public function remove_recipient($email)
    {
        $recipients = $this->get_recipients();
        $key = array_search($email, $recipients);

        if ($key !== false) {
            unset($recipients[$key]);
            $recipients = array_values($recipients); // Re-index array
            update_option('form_settings_recipients', $recipients);
            return true;
        }

        return false;
    }

    /**
     * Add global recipients to CF7 mail
     * 
     * @param array $components Mail components
     * @param object $cf7 Contact Form 7 object
     * @param object $mail_object Mail object
     * @return array Modified mail components
     */
    public function add_global_recipients($components, $cf7, $mail_object)
    {
        $global_recipients = $this->get_recipients();

        if (empty($global_recipients)) {
            return $components;
        }

        // Get existing recipients
        $existing_recipients = isset($components['recipient']) ? $components['recipient'] : '';

        // Parse existing recipients (they might be comma-separated)
        $existing_array = array_map('trim', explode(',', $existing_recipients));

        // Merge with global recipients and remove duplicates
        $all_recipients = array_unique(array_merge($existing_array, $global_recipients));

        // Filter out empty values
        $all_recipients = array_filter($all_recipients);

        // Update components
        $components['recipient'] = implode(', ', $all_recipients);

        return $components;
    }

    /**
     * Scan all CF7 forms and get their recipients
     * 
     * @return array Array of forms with their recipients
     */
    public function scan_form_recipients()
    {
        $args = array(
            'post_type' => 'wpcf7_contact_form',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        );

        $forms = get_posts($args);
        $form_recipients = array();

        foreach ($forms as $form) {
            $contact_form = wpcf7_contact_form($form->ID);

            if (!$contact_form) {
                continue;
            }

            // Get mail properties
            $properties = $contact_form->get_properties();
            $mail_properties = isset($properties['mail']) ? $properties['mail'] : array();

            if (isset($mail_properties['recipient']) && !empty($mail_properties['recipient'])) {
                $recipients_string = $mail_properties['recipient'];

                // Parse recipients (they might be comma-separated or use mail tags)
                $recipients = array_map('trim', explode(',', $recipients_string));

                // Filter out mail tags and keep only actual email addresses
                $email_recipients = array();
                foreach ($recipients as $recipient) {
                    // Skip mail tags like [your-email]
                    if (strpos($recipient, '[') === false && is_email($recipient)) {
                        $email_recipients[] = $recipient;
                    }
                }

                if (!empty($email_recipients)) {
                    $form_recipients[] = array(
                        'form_id' => $form->ID,
                        'form_title' => $form->post_title,
                        'recipients' => $email_recipients
                    );
                }
            }
        }

        return $form_recipients;
    }

    /**
     * Remove a recipient from a specific form
     * 
     * @param int $form_id Form ID
     * @param string $email Email address to remove
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function remove_form_recipient($form_id, $email)
    {
        $contact_form = wpcf7_contact_form($form_id);

        if (!$contact_form) {
            return new WP_Error('invalid_form', __('Invalid form ID.', 'form-settings'));
        }


        // Get current mail properties
        $properties = $contact_form->get_properties();
        $mail_properties = isset($properties['mail']) ? $properties['mail'] : array();

        if (!isset($mail_properties['recipient'])) {
            return new WP_Error('no_recipients', __('Form has no recipients configured.', 'form-settings'));
        }

        // Parse current recipients
        $recipients_string = $mail_properties['recipient'];
        $recipients = array_map('trim', explode(',', $recipients_string));

        // Remove the specified email
        $key = array_search($email, $recipients);

        if ($key === false) {
            return new WP_Error('recipient_not_found', __('Recipient not found in this form.', 'form-settings'));
        }

        unset($recipients[$key]);
        $recipients = array_values($recipients); // Re-index

        // Update form properties
        $mail_properties['recipient'] = implode(', ', $recipients);
        $properties['mail'] = $mail_properties;
        $contact_form->set_properties($properties);
        $contact_form->save();

        return true;
    }

    /**
     * Get all unique recipients across all forms
     * 
     * @return array Array of unique email addresses with form counts
     */
    public function get_all_unique_recipients()
    {
        $form_recipients = $this->scan_form_recipients();
        $unique_recipients = array();

        foreach ($form_recipients as $form_data) {
            foreach ($form_data['recipients'] as $email) {
                if (!isset($unique_recipients[$email])) {
                    $unique_recipients[$email] = array(
                        'email' => $email,
                        'forms' => array()
                    );
                }

                $unique_recipients[$email]['forms'][] = array(
                    'id' => $form_data['form_id'],
                    'title' => $form_data['form_title']
                );
            }
        }

        return array_values($unique_recipients);
    }
}
