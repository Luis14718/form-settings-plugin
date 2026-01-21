<?php
/**
 * Form Scanner Class
 * 
 * Scans all Contact Form 7 forms and extracts field information
 */

if (!defined('WPINC')) {
    die;
}

class Form_Settings_Form_Scanner
{

    /**
     * Get all Contact Form 7 forms
     * 
     * @return array Array of CF7 form objects
     */
    public function get_all_forms()
    {
        $args = array(
            'post_type' => 'wpcf7_contact_form',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        );

        return get_posts($args);
    }

    /**
     * Scan all forms and extract fields
     * 
     * @return array Array of fields with metadata
     */
    public function scan_all_forms()
    {
        $forms = $this->get_all_forms();
        $all_fields = array();

        foreach ($forms as $form) {
            $form_id = $form->ID;
            $form_title = $form->post_title;

            // Get form content
            $form_content = $form->post_content;

            // Extract fields from form content
            $fields = $this->extract_fields($form_content);

            foreach ($fields as $field) {
                $field_name = $field['name'];

                // Initialize field if not exists
                if (!isset($all_fields[$field_name])) {
                    $all_fields[$field_name] = array(
                        'name' => $field_name,
                        'type' => $field['type'],
                        'required' => $field['required'],
                        'forms' => array()
                    );
                }

                // Add form information
                $all_fields[$field_name]['forms'][] = array(
                    'id' => $form_id,
                    'title' => $form_title
                );

                // Update type if different (in case same field has different types in different forms)
                if ($all_fields[$field_name]['type'] !== $field['type']) {
                    $all_fields[$field_name]['type'] = 'mixed';
                }
            }
        }

        return array_values($all_fields);
    }

    /**
     * Extract fields from form content
     * 
     * @param string $content Form content
     * @return array Array of fields
     */
    private function extract_fields($content)
    {
        $fields = array();

        // Pattern to match CF7 form tags
        // Matches patterns like [text* your-name], [email your-email], [tel your-phone], etc.
        $pattern = '/\[([a-zA-Z0-9_-]+)(\*?)\s+([a-zA-Z0-9_-]+)([^\]]*)\]/';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $type = $match[1];
            $required = !empty($match[2]);
            $name = $match[3];
            $attributes = isset($match[4]) ? $match[4] : '';

            // Skip submit buttons and other non-input fields
            if (in_array($type, array('submit', 'acceptance'))) {
                continue;
            }

            $fields[] = array(
                'type' => $type,
                'name' => $name,
                'required' => $required,
                'attributes' => trim($attributes)
            );
        }

        return $fields;
    }

    /**
     * Get field statistics
     * 
     * @return array Statistics about forms and fields
     */
    public function get_statistics()
    {
        $forms = $this->get_all_forms();
        $fields = $this->scan_all_forms();

        return array(
            'total_forms' => count($forms),
            'total_unique_fields' => count($fields),
            'last_scan' => current_time('mysql')
        );
    }
}
