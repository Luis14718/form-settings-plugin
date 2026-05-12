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
     * Extract CF7 tag name safely across CF7 versions.
     *
     * @param mixed $tag
     * @return string
     */
    private function get_tag_name($tag)
    {
        if (is_array($tag) && isset($tag['name'])) {
            return (string) $tag['name'];
        }
        if (is_object($tag) && isset($tag->name)) {
            return (string) $tag->name;
        }
        return '';
    }

    /**
     * Extract CF7 tag type safely across CF7 versions.
     *
     * @param mixed $tag
     * @return string
     */
    private function get_tag_type($tag)
    {
        if (is_array($tag) && isset($tag['type'])) {
            return (string) $tag['type'];
        }
        if (is_object($tag) && isset($tag->type)) {
            return (string) $tag->type;
        }
        return '';
    }

    /**
     * Determine whether the field name looks like a "name" field.
     *
     * Note: the plugin stores rules by field name only (not CF7 tag type),
     * so we use a conservative heuristic to avoid applying name validation
     * to unrelated text inputs.
     *
     * @param string $field_name
     * @return bool
     */
    private function is_likely_name_field($field_name)
    {
        $f = strtolower((string) $field_name);

        // Common CF7 conventions: your-name, first-name, last-name, full-name.
        if ($f === 'your-name' || $f === 'first-name' || $f === 'last-name' || $f === 'full-name') {
            return true;
        }

        // Ends with "-name" or contains "-name" or suffix "name".
        if (strpos($f, '-name') !== false) {
            return true;
        }

        return substr($f, -4) === 'name';
    }

    /**
     * Strict-ish email validation:
     * - no whitespace
     * - no leading/trailing dots
     * - no consecutive dots
     * - domain must look like a valid hostname
     *
     * @param string $email
     * @return bool
     */
    private function is_valid_email_format($email)
    {
        $email = (string) $email;

        if ($email === '') {
            return false;
        }

        // Reject any whitespace anywhere.
        if (preg_match('/\s/u', $email)) {
            return false;
        }

        // No leading/trailing dots, and no consecutive dots.
        if ($email[0] === '.' || substr($email, -1) === '.' || strpos($email, '..') !== false) {
            return false;
        }

        // Must contain exactly one '@'
        $first_at = strpos($email, '@');
        $last_at  = strrpos($email, '@');
        if ($first_at === false || $first_at !== $last_at) {
            return false;
        }

        $local  = substr($email, 0, $first_at);
        $domain = substr($email, $first_at + 1);

        if ($local === '' || $domain === '') {
            return false;
        }

        if ($local[0] === '.' || substr($local, -1) === '.' || strpos($local, '..') !== false) {
            return false;
        }

        if ($domain[0] === '.' || substr($domain, -1) === '.' || strpos($domain, '..') !== false) {
            return false;
        }

        // Local part: allow a pragmatic set of RFC-like characters (no spaces).
        if (!preg_match("/^[A-Za-z0-9.!#$%&'*+\\/=?^_`{|}~-]+$/", $local)) {
            return false;
        }

        // Domain: each label 1-63 chars, alnum + hyphens (no leading/trailing hyphen).
        if (substr_count($domain, '.') < 1) {
            return false;
        }

        if (!preg_match('/^(?=.{1,253}$)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(?:\.(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?))*$/', $domain)) {
            return false;
        }

        // Require a "real" TLD length.
        $tld = substr($domain, strrpos($domain, '.') + 1);
        if (strlen($tld) < 2) {
            return false;
        }

        return true;
    }

    /**
     * Phone validation:
     * - no whitespace
     * - optional leading '+'
     * - digits with optional hyphens between groups (no consecutive/trailing hyphens)
     *
     * Examples:
     *  +1-202-555-0123
     *  202-555-0123
     *  +44987654321
     *
     * @param string $phone
     * @return bool
     */
    private function is_valid_phone_format($phone)
    {
        $phone = (string) $phone;

        if ($phone === '') {
            return false;
        }

        if (preg_match('/\s/u', $phone)) {
            return false;
        }

        // Only allow: optional leading '+', digits, and hyphen separators.
        if (!preg_match('/^\+?\d+(?:-\d+)*$/', $phone)) {
            return false;
        }

        return true;
    }

    /**
     * Name validation:
     * - letters only (unicode letters)
     * - single spaces between words (no leading/trailing/multiple spaces)
     * - optional internal hyphens for hyphenated names
     *
     * @param string $name
     * @return bool
     */
    private function is_valid_name_format($name)
    {
        $name = (string) $name;

        if ($name === '') {
            return false;
        }

        // Reject any whitespace characters other than a plain single space.
        // (CF7 fields are often submitted with copy/paste including tabs/newlines.)
        if (preg_match('/[\t\r\n\v\f]/u', $name)) {
            return false;
        }

        // Letters with optional hyphens within each word, words separated by a single space.
        // Examples accepted: "John", "Anne-Marie", "Mary Jane", "Jean-Paul Sartre"
        // Examples rejected: " John", "John ", "Mary  Jane", "John3", "O'Neil", "John-"
        $pattern = '/^\p{L}+(?:-\p{L}+)*(?: \p{L}+(?:-\p{L}+)*)*$/u';
        return preg_match($pattern, $name) === 1;
    }

    /**
     * A: No leading/trailing spaces and no multiple consecutive spaces.
     *
     * @param string $value
     * @return bool
     */
    private function is_valid_no_extra_spaces($value)
    {
        $value = (string) $value;

        if ($value === '') {
            return true; // empty is handled by "required" validation
        }

        // Reject tabs/newlines/etc (only plain spaces are allowed).
        if (preg_match('/[\t\r\n\v\f]/u', $value)) {
            return false;
        }

        // Reject leading/trailing whitespace.
        if ($value !== trim($value)) {
            return false;
        }

        // Reject multiple consecutive spaces.
        if (preg_match('/ {2,}/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * B: Valid URL validation (http/https only).
     *
     * @param string $url
     * @return bool
     */
    private function is_valid_url_format($url)
    {
        $url = (string) $url;

        if ($url === '') {
            return false;
        }

        if (preg_match('/\s/u', $url)) {
            return false;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = wp_parse_url($url);
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $host   = isset($parts['host']) ? strtolower($parts['host']) : '';

        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        if ($host === '') {
            return false;
        }

        // Basic domain sanity: require a dot for non-localhost.
        if ($host !== 'localhost' && substr_count($host, '.') < 1) {
            return false;
        }

        return true;
    }

    /**
     * C: Numeric only (digits only).
     *
     * @param string $value
     * @return bool
     */
    private function is_valid_numeric_format($value)
    {
        $value = (string) $value;

        if ($value === '') {
            return false;
        }

        if (preg_match('/\s/u', $value)) {
            return false;
        }

        return preg_match('/^\d+$/', $value) === 1;
    }

    /**
     * D: Date validation (YYYY-MM-DD).
     *
     * @param string $value
     * @return bool
     */
    private function is_valid_date_format($value)
    {
        $value = (string) $value;

        if ($value === '') {
            return false;
        }

        if (preg_match('/\s/u', $value)) {
            return false;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$y, $m, $d] = array_map('intval', explode('-', $value));
        return checkdate($m, $d, $y);
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        // Hook into CF7 validation
        add_filter('wpcf7_validate', array($this, 'apply_validation_rules'), 10, 2);
        // Run format validation on CF7's dedicated hooks (more reliable than using
        // $tag['type'] inside wpcf7_validate).
        add_filter('wpcf7_validate_email', array($this, 'validate_email'), 10, 2);
        add_filter('wpcf7_validate_email*', array($this, 'validate_email'), 10, 2);
        add_filter('wpcf7_validate_text', array($this, 'validate_text'), 10, 2);
        add_filter('wpcf7_validate_text*', array($this, 'validate_text'), 10, 2);
        add_filter('wpcf7_validate_tel', array($this, 'validate_phone'), 10, 2);
        add_filter('wpcf7_validate_tel*', array($this, 'validate_phone'), 10, 2);

        // URL / number / date per-field toggles.
        add_filter('wpcf7_validate_url', array($this, 'validate_url'), 10, 2);
        add_filter('wpcf7_validate_url*', array($this, 'validate_url'), 10, 2);
        add_filter('wpcf7_validate_number', array($this, 'validate_number'), 10, 2);
        add_filter('wpcf7_validate_number*', array($this, 'validate_number'), 10, 2);
        add_filter('wpcf7_validate_date', array($this, 'validate_date'), 10, 2);
        add_filter('wpcf7_validate_date*', array($this, 'validate_date'), 10, 2);
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

        $name = $this->get_tag_name($tag);
        $tag_type = $this->get_tag_type($tag);

        if (empty($name) || !isset($rules[$name])) {
            return $result;
        }

        $rule = $rules[$name];
        $raw_value = isset($_POST[$name]) ? (string) $_POST[$name] : '';
        $value = trim($raw_value);

        // Check if field is required
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $result->invalidate($tag, __('This field is required.', 'form-settings'));
        }

        // Strict phone format validation for CF7 tel tags.
        // This guarantees we reject alphabetic characters even if CF7 filter
        // ordering/hook triggering differs across versions.
        if ($tag_type === 'tel' && !empty($value) && !$this->is_valid_phone_format($raw_value)) {
            $result->invalidate($tag, __('Please enter a valid phone number.', 'form-settings'));
            return $result;
        }

        // Check min length
        if ($tag_type !== 'tel') {
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
        }

        return $result;
    }

    /**
     * Validate email format for CF7 email fields.
     *
     * @param object $result
     * @param object $tag
     * @return object
     */
    public function validate_email($result, $tag)
    {
        $name = $this->get_tag_name($tag);
        if (empty($name)) {
            return $result;
        }

        $rule = $this->get_field_rule($name);
        if (!$rule) {
            return $result;
        }

        $raw_value = isset($_POST[$name]) ? (string) $_POST[$name] : '';
        $value = trim($raw_value);

        // Skip when empty; required is handled by apply_validation_rules.
        if ($value === '') {
            return $result;
        }

        if (!$this->is_valid_email_format($raw_value)) {
            $result->invalidate($tag, __('Please enter a valid email address.', 'form-settings'));
        }

        return $result;
    }

    /**
     * Validate name format for CF7 text fields.
     *
     * @param object $result
     * @param object $tag
     * @return object
     */
    public function validate_text($result, $tag)
    {
        $name = $this->get_tag_name($tag);
        if (empty($name)) {
            return $result;
        }

        $rule = $this->get_field_rule($name);
        if (!$rule) {
            return $result;
        }

        $raw_value = isset($_POST[$name]) ? (string) $_POST[$name] : '';
        $value = trim($raw_value);

        // Skip when empty; required is handled by apply_validation_rules.
        if ($value === '') {
            return $result;
        }

        if (!$this->is_valid_name_format($raw_value)) {
            $result->invalidate($tag, __('Please enter a valid name.', 'form-settings'));
        }

        // A/B/C/D per-field toggles (works on CF7 text tags too).
        if (!empty($rule['no_extra_spaces']) && !$this->is_valid_no_extra_spaces($raw_value)) {
            $result->invalidate(
                $tag,
                __('Please remove leading/trailing spaces and avoid multiple consecutive spaces.', 'form-settings')
            );
        }

        if (!empty($rule['url_format']) && !$this->is_valid_url_format($raw_value)) {
            $result->invalidate($tag, __('Please enter a valid URL.', 'form-settings'));
        }

        if (!empty($rule['numeric_only']) && !$this->is_valid_numeric_format($raw_value)) {
            $result->invalidate($tag, __('Please enter digits only.', 'form-settings'));
        }

        if (!empty($rule['date_format']) && !$this->is_valid_date_format($raw_value)) {
            $result->invalidate($tag, __('Please enter a valid date (YYYY-MM-DD).', 'form-settings'));
        }

        return $result;
    }

    /**
     * Validate URL format (B) when enabled for this field.
     *
     * @param object $result
     * @param object $tag
     * @return object
     */
    public function validate_url($result, $tag)
    {
        $name = $this->get_tag_name($tag);
        if (empty($name)) {
            return $result;
        }

        $rule = $this->get_field_rule($name);
        if (!$rule || empty($rule['url_format'])) {
            return $result;
        }

        $raw_value = isset($_POST[$name]) ? (string) $_POST[$name] : '';
        $value = trim($raw_value);
        if ($value === '') {
            return $result;
        }

        if (!$this->is_valid_url_format($raw_value)) {
            $result->invalidate($tag, __('Please enter a valid URL.', 'form-settings'));
        }

        return $result;
    }

    /**
     * Validate numeric-only format (C) when enabled for this field.
     *
     * @param object $result
     * @param object $tag
     * @return object
     */
    public function validate_number($result, $tag)
    {
        $name = $this->get_tag_name($tag);
        if (empty($name)) {
            return $result;
        }

        $rule = $this->get_field_rule($name);
        if (!$rule || empty($rule['numeric_only'])) {
            return $result;
        }

        $raw_value = isset($_POST[$name]) ? (string) $_POST[$name] : '';
        $value = trim($raw_value);
        if ($value === '') {
            return $result;
        }

        if (!$this->is_valid_numeric_format($raw_value)) {
            $result->invalidate($tag, __('Please enter digits only.', 'form-settings'));
        }

        return $result;
    }

    /**
     * Validate date format (D) when enabled for this field.
     *
     * @param object $result
     * @param object $tag
     * @return object
     */
    public function validate_date($result, $tag)
    {
        $name = $this->get_tag_name($tag);
        if (empty($name)) {
            return $result;
        }

        $rule = $this->get_field_rule($name);
        if (!$rule || empty($rule['date_format'])) {
            return $result;
        }

        $raw_value = isset($_POST[$name]) ? (string) $_POST[$name] : '';
        $value = trim($raw_value);
        if ($value === '') {
            return $result;
        }

        if (!$this->is_valid_date_format($raw_value)) {
            $result->invalidate($tag, __('Please enter a valid date (YYYY-MM-DD).', 'form-settings'));
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

        $raw_value = isset($_POST[$name]) ? (string) $_POST[$name] : '';
        $value = trim($raw_value);

        // If it's empty, required is handled in apply_validation_rules.
        if (empty($value)) {
            return $result;
        }

        // Strict format validation before digit-count checks.
        if (!$this->is_valid_phone_format($raw_value)) {
            $result->invalidate($tag, __('Please enter a valid phone number.', 'form-settings'));
            return $result;
        }

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
