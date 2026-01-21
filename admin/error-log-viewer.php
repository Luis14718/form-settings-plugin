<?php
/**
 * Error Log Viewer Page
 * 
 * Simple page to view form submission error logs
 * Access via: /wp-admin/admin.php?page=form-settings-error-logs
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Get error logger
$error_logger = new Form_Settings_Error_Logger();
$logs = $error_logger->get_logs();
$stats = $error_logger->get_statistics();

// Handle clear all action
if (isset($_POST['clear_all_logs']) && check_admin_referer('fs_clear_logs')) {
    $error_logger->clear_logs();
    echo '<div class="notice notice-success"><p>' . __('All error logs cleared.', 'form-settings') . '</p></div>';
    $logs = array();
    $stats = $error_logger->get_statistics();
}

?>
<div class="wrap">
    <h1><?php _e('Form Submission Error Logs', 'form-settings'); ?></h1>
    
    <p><?php _e('This page shows all form submission errors including validation failures, spam detections, and mail errors.', 'form-settings'); ?></p>
    
    <!-- Statistics -->
    <div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-radius: 5px;">
        <h2><?php _e('Statistics', 'form-settings'); ?></h2>
        <ul style="list-style: none; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <li><strong><?php _e('Total Errors:', 'form-settings'); ?></strong> <span style="font-size: 24px; color: #d63638;"><?php echo esc_html($stats['total']); ?></span></li>
            <li><strong><?php _e('Validation:', 'form-settings'); ?></strong> <span style="font-size: 24px; color: #f0ad4e;"><?php echo esc_html($stats['by_type']['validation']); ?></span></li>
            <li><strong><?php _e('Spam:', 'form-settings'); ?></strong> <span style="font-size: 24px; color: #d9534f;"><?php echo esc_html($stats['by_type']['spam']); ?></span></li>
            <li><strong><?php _e('Mail Errors:', 'form-settings'); ?></strong> <span style="font-size: 24px; color: #5bc0de;"><?php echo esc_html($stats['by_type']['mail']); ?></span></li>
            <li><strong><?php _e('Field Errors:', 'form-settings'); ?></strong> <span style="font-size: 24px; color: #5cb85c;"><?php echo esc_html($stats['by_type']['field']); ?></span></li>
        </ul>
    </div>
    
    <!-- Actions -->
    <div style="margin: 20px 0;">
        <form method="post" style="display: inline;">
            <?php wp_nonce_field('fs_clear_logs'); ?>
            <button type="submit" name="clear_all_logs" class="button button-secondary" onclick="return confirm('Are you sure you want to clear all error logs?');">
                <?php _e('Clear All Logs', 'form-settings'); ?>
            </button>
        </form>
    </div>
    
    <!-- Error Logs Table -->
    <?php if (empty($logs)): ?>
        <p><?php _e('No errors logged yet.', 'form-settings'); ?></p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;"><?php _e('Date/Time', 'form-settings'); ?></th>
                    <th style="width: 80px;"><?php _e('Type', 'form-settings'); ?></th>
                    <th><?php _e('Form', 'form-settings'); ?></th>
                    <th><?php _e('Message', 'form-settings'); ?></th>
                    <th style="width: 120px;"><?php _e('IP Address', 'form-settings'); ?></th>
                    <th style="width: 80px;"><?php _e('Details', 'form-settings'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log['timestamp']))); ?></td>
                        <td>
                            <?php
                            $badge_colors = array(
                                'validation' => '#f0ad4e',
                                'spam' => '#d9534f',
                                'mail' => '#5bc0de',
                                'field' => '#5cb85c'
                            );
                            $color = isset($badge_colors[$log['type']]) ? $badge_colors[$log['type']] : '#999';
                            ?>
                            <span style="background: <?php echo esc_attr($color); ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase; display: inline-block;">
                                <?php echo esc_html($log['type']); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log['form_title']); ?></td>
                        <td><?php echo esc_html($log['message']); ?></td>
                        <td><?php echo esc_html($log['ip_address']); ?></td>
                        <td>
                            <button type="button" class="button button-small" onclick="showErrorDetails<?php echo esc_attr($log['id']); ?>()" style="font-size: 11px;">
                                <?php _e('View', 'form-settings'); ?>
                            </button>
                            
                            <!-- Hidden details div -->
                            <div id="details-<?php echo esc_attr($log['id']); ?>" style="display: none;">
                                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000; display: flex; align-items: center; justify-content: center;">
                                    <div style="background: white; padding: 30px; border-radius: 8px; max-width: 800px; max-height: 80vh; overflow-y: auto; position: relative;">
                                        <button onclick="hideErrorDetails<?php echo esc_attr($log['id']); ?>()" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                                        <h2><?php _e('Error Details', 'form-settings'); ?></h2>
                                        
                                        <p><strong><?php _e('Date/Time:', 'form-settings'); ?></strong> <?php echo esc_html($log['timestamp']); ?></p>
                                        <p><strong><?php _e('Form:', 'form-settings'); ?></strong> <?php echo esc_html($log['form_title']); ?> (ID: <?php echo esc_html($log['form_id']); ?>)</p>
                                        <p><strong><?php _e('Type:', 'form-settings'); ?></strong> <?php echo esc_html($log['type']); ?></p>
                                        <p><strong><?php _e('IP Address:', 'form-settings'); ?></strong> <?php echo esc_html($log['ip_address']); ?></p>
                                        <p><strong><?php _e('User Agent:', 'form-settings'); ?></strong> <?php echo esc_html($log['user_agent']); ?></p>
                                        
                                        <h3><?php _e('Submitted Data', 'form-settings'); ?></h3>
                                        <?php if (!empty($log['details']['submission_data'])): ?>
                                            <table class="widefat" style="margin-top: 10px;">
                                                <thead>
                                                    <tr>
                                                        <th><?php _e('Field', 'form-settings'); ?></th>
                                                        <th><?php _e('Value', 'form-settings'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($log['details']['submission_data'] as $field => $value): ?>
                                                        <tr>
                                                            <td><strong><?php echo esc_html($field); ?></strong></td>
                                                            <td><?php echo esc_html($value); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <p><?php _e('No submission data available.', 'form-settings'); ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($log['details']['invalid_fields'])): ?>
                                            <h3><?php _e('Invalid Fields', 'form-settings'); ?></h3>
                                            <ul>
                                                <?php foreach ($log['details']['invalid_fields'] as $invalid): ?>
                                                    <li><strong><?php echo esc_html($invalid['field']); ?>:</strong> <?php echo esc_html($invalid['reason']); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                                function showErrorDetails<?php echo esc_attr($log['id']); ?>() {
                                    document.getElementById('details-<?php echo esc_attr($log['id']); ?>').style.display = 'flex';
                                }
                                function hideErrorDetails<?php echo esc_attr($log['id']); ?>() {
                                    document.getElementById('details-<?php echo esc_attr($log['id']); ?>').style.display = 'none';
                                }
                            </script>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
    .wrap h1 {
        margin-bottom: 10px;
    }
    .wrap > p {
        font-size: 14px;
        color: #666;
    }
</style>
