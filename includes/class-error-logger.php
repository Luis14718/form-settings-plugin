<?php
/**
 * Error Logger Class
 * 
 * Logs and manages Contact Form 7 submission errors
 */

if (!defined('WPINC')) {
    die;
}

class Form_Settings_Error_Logger
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Hook into CF7 events to log errors
        // These hooks fire AFTER form submission
        add_action('wpcf7_submit', array($this, 'log_submission_error'), 20, 2);
        add_action('wpcf7_mail_failed', array($this, 'log_mail_error'), 10, 1);
        add_action('wpcf7_spam', array($this, 'log_spam_error'), 10, 1);
    }

    /**
     * Log submission error (validation or other issues)
     * 
     * @param object $contact_form CF7 contact form object
     * @param array $result Submission result
     */
    public function log_submission_error($contact_form, $result)
    {
        // Only log if there was an error (not success)
        if ($result['status'] === 'validation_failed' || $result['status'] === 'acceptance_missing' || $result['status'] === 'invalid') {
            $this->log_error(array(
                'type' => 'validation',
                'form_id' => $contact_form->id(),
                'form_title' => $contact_form->title(),
                'message' => isset($result['message']) ? $result['message'] : 'Form validation failed',
                'details' => $this->get_validation_details($contact_form, $result)
            ));
        }
    }


    /**
     * Log spam error
     * 
     * @param object $contact_form CF7 contact form object
     */
    public function log_spam_error($contact_form)
    {
        $this->log_error(array(
            'type' => 'spam',
            'form_id' => $contact_form->id(),
            'form_title' => $contact_form->title(),
            'message' => 'Form submission marked as spam',
            'details' => $this->get_submission_data()
        ));
    }

    /**
     * Log mail error
     * 
     * @param object $contact_form CF7 contact form object
     */
    public function log_mail_error($contact_form)
    {
        $this->log_error(array(
            'type' => 'mail',
            'form_id' => $contact_form->id(),
            'form_title' => $contact_form->title(),
            'message' => 'Failed to send email',
            'details' => $this->get_submission_data()
        ));
    }


    /**
     * Log error to database
     * 
     * @param array $error_data Error data
     */
    private function log_error($error_data)
    {
        $logs = get_option('form_settings_error_logs', array());

        $error_entry = array(
            'id' => uniqid('error_'),
            'timestamp' => current_time('mysql'),
            'type' => $error_data['type'],
            'form_id' => $error_data['form_id'],
            'form_title' => $error_data['form_title'],
            'message' => $error_data['message'],
            'details' => $error_data['details'],
            'ip_address' => $this->get_user_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : ''
        );

        // Add to beginning of array (newest first)
        array_unshift($logs, $error_entry);

        // Keep only last 500 errors to prevent database bloat
        $logs = array_slice($logs, 0, 500);

        update_option('form_settings_error_logs', $logs);
    }

    /**
     * Get validation details from contact form
     * 
     * @param object $contact_form CF7 contact form object
     * @param array $result Submission result (optional)
     * @return array Validation details
     */
    private function get_validation_details($contact_form, $result = null)
    {
        $invalid_fields = array();

        // Get invalid fields from result if available
        if ($result && isset($result['invalid_fields'])) {
            foreach ($result['invalid_fields'] as $field) {
                $invalid_fields[] = array(
                    'field' => isset($field['into']) ? $field['into'] : 'unknown',
                    'reason' => isset($field['message']) ? $field['message'] : 'Invalid'
                );
            }
        }

        // Also try to get from contact form object
        if (method_exists($contact_form, 'get_invalid_fields')) {
            $invalid = $contact_form->get_invalid_fields();
            foreach ($invalid as $field_name => $field_data) {
                $invalid_fields[] = array(
                    'field' => $field_name,
                    'reason' => isset($field_data['reason']) ? $field_data['reason'] : 'Invalid'
                );
            }
        }

        return array(
            'invalid_fields' => $invalid_fields,
            'submission_data' => $this->get_submission_data(),
            'result_status' => $result ? $result['status'] : 'unknown'
        );
    }

    /**
     * Get submission data
     * 
     * @return array Submission data
     */
    private function get_submission_data()
    {
        $submission = WPCF7_Submission::get_instance();

        if (!$submission) {
            return array();
        }

        $posted_data = $submission->get_posted_data();

        // Remove sensitive data
        $safe_data = array();
        foreach ($posted_data as $key => $value) {
            // Skip password fields and other sensitive data
            if (stripos($key, 'password') === false && stripos($key, 'card') === false) {
                $safe_data[$key] = is_array($value) ? implode(', ', $value) : $value;
            }
        }

        return $safe_data;
    }

    /**
     * Get user IP address
     * 
     * @return string IP address
     */
    private function get_user_ip()
    {
        $ip = '';

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }

    /**
     * Get all error logs
     * 
     * @param array $filters Optional filters
     * @return array Error logs
     */
    public function get_logs($filters = array())
    {
        $logs = get_option('form_settings_error_logs', array());

        if (empty($filters)) {
            return $logs;
        }

        // Filter by type
        if (isset($filters['type']) && !empty($filters['type'])) {
            $logs = array_filter($logs, function ($log) use ($filters) {
                return $log['type'] === $filters['type'];
            });
        }

        // Filter by form ID
        if (isset($filters['form_id']) && !empty($filters['form_id'])) {
            $logs = array_filter($logs, function ($log) use ($filters) {
                return $log['form_id'] == $filters['form_id'];
            });
        }

        // Filter by date range
        if (isset($filters['date_from']) && !empty($filters['date_from'])) {
            $logs = array_filter($logs, function ($log) use ($filters) {
                return strtotime($log['timestamp']) >= strtotime($filters['date_from']);
            });
        }

        return array_values($logs);
    }

    /**
     * Clear all error logs
     * 
     * @return bool True on success
     */
    public function clear_logs()
    {
        return update_option('form_settings_error_logs', array());
    }

    /**
     * Delete specific error log
     * 
     * @param string $error_id Error ID
     * @return bool True on success
     */
    public function delete_log($error_id)
    {
        $logs = get_option('form_settings_error_logs', array());

        foreach ($logs as $key => $log) {
            if ($log['id'] === $error_id) {
                unset($logs[$key]);
                $logs = array_values($logs);
                return update_option('form_settings_error_logs', $logs);
            }
        }

        return false;
    }

    /**
     * Get error statistics
     * 
     * @return array Statistics
     */
    public function get_statistics()
    {
        $logs = get_option('form_settings_error_logs', array());

        $stats = array(
            'total' => count($logs),
            'by_type' => array(
                'validation' => 0,
                'spam' => 0,
                'mail' => 0,
                'field' => 0
            ),
            'by_form' => array()
        );

        foreach ($logs as $log) {
            // Count by type
            if (isset($stats['by_type'][$log['type']])) {
                $stats['by_type'][$log['type']]++;
            }

            // Count by form
            $form_key = $log['form_id'] . '|' . $log['form_title'];
            if (!isset($stats['by_form'][$form_key])) {
                $stats['by_form'][$form_key] = 0;
            }
            $stats['by_form'][$form_key]++;
        }

        return $stats;
    }
}
