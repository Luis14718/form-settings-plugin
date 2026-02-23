<?php
/**
 * Enqueue frontend scripts for copy/paste control and required field validation
 */

if (!defined('WPINC')) {
    die;
}

// Add frontend script to disable copy/paste and enforce required field button state
function form_settings_enqueue_frontend_scripts()
{
    $options = get_option('form_settings_options', array());
    $disable_copy_paste = isset($options['disable_copy_paste']) && $options['disable_copy_paste'];

    // Build required fields list with display names from validation rules
    $rules = get_option('form_settings_validation_rules', array());
    $required_fields = array(); // [ { name, label } ]
    if (is_array($rules)) {
        foreach ($rules as $field_name => $rule) {
            if (!empty($rule['required'])) {
                $required_fields[] = array(
                    'name' => $field_name,
                    'label' => !empty($rule['display_name']) ? $rule['display_name'] : $field_name,
                );
            }
        }
    }

    // Only enqueue if there's something to do
    if (!$disable_copy_paste && empty($required_fields)) {
        return;
    }

    // Pass required fields (with labels) to JS
    wp_localize_script('jquery', 'formSettingsValidation', array(
        'required_fields' => $required_fields,
    ));

    $inline_js = "jQuery(document).ready(function($) {\n";

    // ── Required fields: disable submit until all filled + tooltip on click ───
    if (!empty($required_fields)) {
        $inline_js .= "
        var fsRequired = formSettingsValidation.required_fields; // [{name, label}]

        // ── Tooltip element (shared, appended once) ───────────────────────────
        var \$fsTooltip = $('<div id=\"fs-required-tooltip\"></div>').css({
            position: 'absolute',
            background: '#333',
            color: '#fff',
            padding: '8px 12px',
            borderRadius: '4px',
            fontSize: '13px',
            lineHeight: '1.5',
            zIndex: 99999,
            pointerEvents: 'none',
            maxWidth: '260px',
            boxShadow: '0 2px 8px rgba(0,0,0,0.25)',
            display: 'none'
        }).appendTo('body');

        // ── Per-form setup ────────────────────────────────────────────────────
        function fsSetupForm(\$form) {
            var \$submit = \$form.find('input[type=\"submit\"], button[type=\"submit\"]');

            // Determine which required fields exist in this form
            var formRequired = [];
            for (var i = 0; i < fsRequired.length; i++) {
                if (\$form.find('[name=\"' + fsRequired[i].name + '\"]').length) {
                    formRequired.push(fsRequired[i]);
                }
            }
            if (formRequired.length === 0) return;

            function getMissing() {
                var missing = [];
                for (var i = 0; i < formRequired.length; i++) {
                    var val = \$form.find('[name=\"' + formRequired[i].name + '\"]').val();
                    if (!val || val.trim() === '') {
                        missing.push(formRequired[i].label);
                    }
                }
                return missing;
            }

            function checkForm() {
                \$submit.prop('disabled', getMissing().length > 0);
                // Sync cursor style on wrapper so it feels right
                if (\$submit.prop('disabled')) {
                    \$wrapper.css('cursor', 'not-allowed');
                    \$submit.css('pointerEvents', 'none');
                } else {
                    \$wrapper.css('cursor', '');
                    \$submit.css('pointerEvents', '');
                }
            }

            // Wrap the submit button so we can receive hover events even when it's disabled
            \$submit.wrap('<span class=\"fs-submit-wrapper\" style=\"display:inline-block;\"></span>');
            var \$wrapper = \$submit.parent('.fs-submit-wrapper');

            // Disable on load
            \$submit.prop('disabled', true);
            \$wrapper.css('cursor', 'not-allowed');
            \$submit.css('pointerEvents', 'none');

            // Re-check on input / change
            \$form.on('input change', function() { checkForm(); });

            // Tooltip: listen on the WRAPPER (receives mouse events even when button is disabled)
            \$wrapper.on('mouseenter', function() {
                if (!\$submit.prop('disabled')) return;
                var missing = getMissing();
                if (missing.length === 0) return;

                var msg = 'Please fill in:<br>';
                for (var i = 0; i < missing.length; i++) {
                    msg += '&bull; ' + missing[i] + '<br>';
                }

                \$fsTooltip.html(msg);
                var btnOffset = \$wrapper.offset();
                \$fsTooltip.css({
                    top: (btnOffset.top - \$fsTooltip.outerHeight(true) - 10) + 'px',
                    left: btnOffset.left + 'px'
                }).fadeIn(150);
            });

            \$wrapper.on('mouseleave', function() {
                \$fsTooltip.fadeOut(200);
            });
        }

        // Init all forms on load
        \$('.wpcf7-form').each(function() { fsSetupForm(\$(this)); });

        // Re-disable after CF7 resets the form (successful submission)
        \$(document).on('wpcf7reset', function(e) {
            var \$form = \$(e.target).find('.wpcf7-form');
            \$form.find('input[type=\"submit\"], button[type=\"submit\"]').prop('disabled', true);
        });
";
    }

    // ── Copy / paste control ───────────────────────────────────────────────────
    if ($disable_copy_paste) {
        $inline_js .= "
        // Disable copy, paste, and cut on all CF7 form fields
        \$(document).on('paste cut copy', '.wpcf7-form input, .wpcf7-form textarea, .wpcf7-form select', function(e) {
            e.preventDefault();
            return false;
        });

        // Also prevent right-click context menu on form fields
        \$(document).on('contextmenu', '.wpcf7-form input, .wpcf7-form textarea, .wpcf7-form select', function(e) {
            e.preventDefault();
            return false;
        });
";
    }

    $inline_js .= "    });\n";

    wp_add_inline_script('jquery', $inline_js);
}
add_action('wp_enqueue_scripts', 'form_settings_enqueue_frontend_scripts');
