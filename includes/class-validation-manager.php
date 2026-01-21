<?php
/**
 * Validation Manager Class
 * 
 * Manages validation rules for Contact Form 7 forms
 */

if (!defined('WPINC')) {
    die;
}

class Form_Settings_Validation_Manager
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Hook into CF7 validation
        add_filter('wpcf7_validate', array($this, 'apply_validation_rules'), 10, 2);
        add_filter('wpcf7_validate_tel', array($this, 'validate_phone'), 10, 2);
        add_filter('wpcf7_validate_tel*', array($this, 'validate_phone'), 10, 2);
    }

    /**
     * Get all validation rules
     * 
     * @return array Array of validation rules
     */
    public function get_validation_rules()
    {
        $rules = get_option('form_settings_validation_rules', array());
        return is_array($rules) ? $rules : array();
    }

    /**
     * Update validation rules
     * 
     * @param array $rules Validation rules
     * @return bool True on success
     */
    public function update_validation_rules($rules)
    {
        return update_option('form_settings_validation_rules', $rules);
    }

    /**
     * Get rule for specific field
     * 
     * @param string $field_name Field name
     * @return array|null Rule array or null if not found
     */
    public function get_field_rule($field_name)
    {
        $rules = $this->get_validation_rules();
        return isset($rules[$field_name]) ? $rules[$field_name] : null;
    }

    /**
     * Set rule for specific field
     * 
     * @param string $field_name Field name
     * @param array $rule Rule configuration
     * @return bool True on success
     */
    public function set_field_rule($field_name, $rule)
    {
        $rules = $this->get_validation_rules();
        $rules[$field_name] = $rule;
        return $this->update_validation_rules($rules);
    }

    /**
     * Remove rule for specific field
     * 
     * @param string $field_name Field name
     * @return bool True on success
     */
    public function remove_field_rule($field_name)
    {
        $rules = $this->get_validation_rules();
        if (isset($rules[$field_name])) {
            unset($rules[$field_name]);
            return $this->update_validation_rules($rules);
        }
        return false;
    }

    /**
     * Apply validation rules to CF7 form
     * 
     * @param object $result Validation result object
     * @param object $tag Form tag object
     * @return object Modified validation result
     */
    public function apply_validation_rules($result, $tag)
    {
        $rules = $this->get_validation_rules();

        if (empty($rules)) {
            return $result;
        }

        $name = isset($tag['name']) ? $tag['name'] : '';

        if (empty($name) || !isset($rules[$name])) {
            return $result;
        }

        $rule = $rules[$name];
        $value = isset($_POST[$name]) ? trim($_POST[$name]) : '';

        // Check if field is required
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $result->invalidate($tag, __('This field is required.', 'form-settings'));
        }

        // Check min length
        if (isset($rule['min_length']) && !empty($value) && strlen($value) < $rule['min_length']) {
            $result->invalidate(
                $tag,
                sprintf(
                    __('This field must be at least %d characters long.', 'form-settings'),
                    $rule['min_length']
                )
            );
        }

        // Check max length
        if (isset($rule['max_length']) && !empty($value) && strlen($value) > $rule['max_length']) {
            $result->invalidate(
                $tag,
                sprintf(
                    __('This field must not exceed %d characters.', 'form-settings'),
                    $rule['max_length']
                )
            );
        }

        return $result;
    }

    /**
     * Validate phone number
     * 
     * @param object $result Validation result object
     * @param object $tag Form tag object
     * @return object Modified validation result
     */
    public function validate_phone($result, $tag)
    {
        $name = $tag->name;
        $rule = $this->get_field_rule($name);

        if (!$rule) {
            return $result;
        }

        $value = isset($_POST[$name]) ? trim($_POST[$name]) : '';

        // Remove non-numeric characters for length check
        $numeric_value = preg_replace('/[^0-9]/', '', $value);

        // Check min length for phone
        if (isset($rule['min_length']) && !empty($value) && strlen($numeric_value) < $rule['min_length']) {
            $result->invalidate(
                $tag,
                sprintf(
                    __('Phone number must be at least %d digits.', 'form-settings'),
                    $rule['min_length']
                )
            );
        }

        // Check max length for phone
        if (isset($rule['max_length']) && !empty($value) && strlen($numeric_value) > $rule['max_length']) {
            $result->invalidate(
                $tag,
                sprintf(
                    __('Phone number must not exceed %d digits.', 'form-settings'),
                    $rule['max_length']
                )
            );
        }

        return $result;
    }
}
