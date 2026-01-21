<?php
/**
 * JavaScript Conflict Scanner
 * Scans theme JS files for code that may interfere with Contact Form 7
 */

if (!defined('ABSPATH')) {
    exit;
}

$conflicts = array();
$theme_dir = get_stylesheet_directory();
$js_files = array();

// Scan theme JS files
if (file_exists($theme_dir . '/assets/js')) {
    $js_files = glob($theme_dir . '/assets/js/*.js');
}

foreach ($js_files as $file) {
    $content = file_get_contents($file);
    $filename = basename($file);

    // Check for jQuery Validate
    if (stripos($content, 'jquery.validate') !== false || stripos($filename, 'validate') !== false) {
        $conflicts[] = array(
            'type' => 'warning',
            'file' => $filename,
            'issue' => 'jQuery Validate library detected',
            'description' => 'This library may conflict with Contact Form 7 validation.',
            'recommendation' => 'Consider removing jQuery Validate or excluding CF7 forms from validation.',
            'severity' => 'medium'
        );
    }

    // Check for preventDefault on invalid events
    if (preg_match("/bind\s*\(\s*['\"]invalid['\"]\s*,/i", $content)) {
        $conflicts[] = array(
            'type' => 'error',
            'file' => $filename,
            'issue' => 'preventDefault on invalid event',
            'description' => 'Code is blocking browser validation with bind(\'invalid\', function() { return false; })',
            'recommendation' => 'Remove lines that use .bind(\'invalid\', function() { return false; })',
            'severity' => 'high'
        );
    }

    // Check for form submit preventDefault
    if (preg_match("/\.submit\s*\(\s*function.*preventDefault/is", $content)) {
        $conflicts[] = array(
            'type' => 'warning',
            'file' => $filename,
            'issue' => 'Form submit preventDefault detected',
            'description' => 'Custom form submission handling may interfere with CF7.',
            'recommendation' => 'Ensure CF7 forms (class .wpcf7-form) are excluded from custom submit handlers.',
            'severity' => 'medium'
        );
    }
}

// Check enqueued scripts
global $wp_scripts;
if (isset($wp_scripts->registered)) {
    foreach ($wp_scripts->registered as $handle => $script) {
        if (stripos($handle, 'validate') !== false || stripos($handle, 'validator') !== false) {
            $conflicts[] = array(
                'type' => 'info',
                'file' => 'Enqueued Script',
                'issue' => 'Validation script: ' . $handle,
                'description' => 'A validation library is enqueued.',
                'recommendation' => 'Verify it doesn\'t conflict with CF7 forms.',
                'severity' => 'low'
            );
        }
    }
}
?>

<div class="js-conflict-scanner"
    style="margin-top: 30px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
    <h3>
        <?php _e('JavaScript Conflict Scanner', 'form-settings'); ?>
    </h3>
    <p>
        <?php _e('This scanner checks your theme\'s JavaScript files for code that may interfere with Contact Form 7 validation.', 'form-settings'); ?>
    </p>

    <?php if (empty($conflicts)): ?>
        <div class="notice notice-success inline">
            <p><strong>
                    <?php _e('✓ No conflicts detected!', 'form-settings'); ?>
                </strong></p>
            <p>
                <?php _e('Your theme\'s JavaScript appears to be compatible with Contact Form 7.', 'form-settings'); ?>
            </p>
        </div>
    <?php else: ?>
        <div class="notice notice-warning inline">
            <p><strong>
                    <?php printf(__('Found %d potential conflict(s)', 'form-settings'), count($conflicts)); ?>
                </strong></p>
        </div>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
            <thead>
                <tr>
                    <th style="width: 15%;">
                        <?php _e('Severity', 'form-settings'); ?>
                    </th>
                    <th style="width: 20%;">
                        <?php _e('File', 'form-settings'); ?>
                    </th>
                    <th style="width: 25%;">
                        <?php _e('Issue', 'form-settings'); ?>
                    </th>
                    <th>
                        <?php _e('Recommendation', 'form-settings'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conflicts as $conflict): ?>
                    <tr>
                        <td>
                            <?php
                            $severity_class = '';
                            $severity_text = '';
                            switch ($conflict['severity']) {
                                case 'high':
                                    $severity_class = 'error';
                                    $severity_text = '🔴 High';
                                    break;
                                case 'medium':
                                    $severity_class = 'warning';
                                    $severity_text = '🟡 Medium';
                                    break;
                                default:
                                    $severity_class = 'info';
                                    $severity_text = '🔵 Low';
                            }
                            ?>
                            <span class="notice-<?php echo esc_attr($severity_class); ?>"
                                style="display: inline-block; padding: 4px 8px; border-radius: 3px;">
                                <?php echo esc_html($severity_text); ?>
                            </span>
                        </td>
                        <td><code><?php echo esc_html($conflict['file']); ?></code></td>
                        <td><strong>
                                <?php echo esc_html($conflict['issue']); ?>
                            </strong><br>
                            <small>
                                <?php echo esc_html($conflict['description']); ?>
                            </small>
                        </td>
                        <td>
                            <?php echo esc_html($conflict['recommendation']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 20px; padding: 15px; background: #fff; border-left: 4px solid #2271b1;">
            <h4>
                <?php _e('How to Fix', 'form-settings'); ?>
            </h4>
            <ol>
                <li>
                    <?php _e('Edit the JavaScript file mentioned above', 'form-settings'); ?>
                </li>
                <li>
                    <?php _e('Remove or modify the problematic code', 'form-settings'); ?>
                </li>
                <li>
                    <?php _e('If using jQuery Validate, exclude CF7 forms: $("form:not(.wpcf7-form)").validate()', 'form-settings'); ?>
                </li>
                <li>
                    <?php _e('Clear your browser cache and test the form again', 'form-settings'); ?>
                </li>
            </ol>
        </div>
    <?php endif; ?>
</div>