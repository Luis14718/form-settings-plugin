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

    // Build validation rules for JS — required + min/max length
    $rules = get_option('form_settings_validation_rules', array());
    $js_rules = array(); // fields that have at least one active rule
    if (is_array($rules)) {
        foreach ($rules as $field_name => $rule) {
            $has_rule = !empty($rule['required'])
                || (!empty($rule['min_length']) && $rule['min_length'] > 0)
                || (!empty($rule['max_length']) && $rule['max_length'] > 0);

            if ($has_rule) {
                $js_rules[] = array(
                    'name' => $field_name,
                    'label' => !empty($rule['display_name']) ? $rule['display_name'] : $field_name,
                    'required' => !empty($rule['required']),
                    'min_length' => isset($rule['min_length']) && $rule['min_length'] > 0 ? (int) $rule['min_length'] : null,
                    'max_length' => isset($rule['max_length']) && $rule['max_length'] > 0 ? (int) $rule['max_length'] : null,
                );
            }
        }
    }

    // Only enqueue if there's something to do
    if (!$disable_copy_paste && empty($js_rules)) {
        return;
    }

    // Pass rules to JS
    wp_localize_script('jquery', 'formSettingsValidation', array(
        'rules' => $js_rules,
    ));

    $inline_js = "jQuery(document).ready(function($) {\n";

    // ── Required / length validation: disable submit + tooltip ────────────────
    if (!empty($js_rules)) {
        $inline_js .= "
        var fsRules = formSettingsValidation.rules; // [{name, label, required, min_length, max_length}]

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
            maxWidth: '280px',
            boxShadow: '0 2px 8px rgba(0,0,0,0.25)',
            display: 'none'
        }).appendTo('body');

        // ── Per-form setup ────────────────────────────────────────────────────
        function fsSetupForm(\$form) {
            var \$submit = \$form.find('input[type=\"submit\"], button[type=\"submit\"]');

            // Only include rules for fields that actually exist in this form
            var formRules = [];
            for (var i = 0; i < fsRules.length; i++) {
                if (\$form.find('[name=\"' + fsRules[i].name + '\"]').length) {
                    formRules.push(fsRules[i]);
                }
            }
            if (formRules.length === 0) return;

            // Returns an array of error message strings, one per problem found
            function getErrors() {
                var errors = [];
                for (var i = 0; i < formRules.length; i++) {
                    var rule  = formRules[i];
                    var \$field = \$form.find('[name=\"' + rule.name + '\"]');
                    var val   = \$field.val() || '';
                    var len   = val.trim().length;

                    if (rule.required && len === 0) {
                        errors.push(rule.label + ' is required');
                        continue; // skip length checks if empty and required
                    }

                    if (len > 0 && rule.min_length !== null && len < rule.min_length) {
                        errors.push(rule.label + ' must be at least ' + rule.min_length + ' characters (currently ' + len + ')');
                    }

                    if (len > 0 && rule.max_length !== null && len > rule.max_length) {
                        errors.push(rule.label + ' must not exceed ' + rule.max_length + ' characters (currently ' + len + ')');
                    }
                }
                return errors;
            }

            function checkForm() {
                var hasErrors = getErrors().length > 0;
                \$submit.prop('disabled', hasErrors);
                if (hasErrors) {
                    \$wrapper.css('cursor', 'not-allowed');
                    \$submit.css('pointerEvents', 'none');
                } else {
                    \$wrapper.css('cursor', '');
                    \$submit.css('pointerEvents', '');
                    \$fsTooltip.hide();
                }
            }

            // Wrap submit button so hover works even when button is disabled
            \$submit.wrap('<span class=\"fs-submit-wrapper\" style=\"display:inline-block;\"></span>');
            var \$wrapper = \$submit.parent('.fs-submit-wrapper');

            // Disable on load
            \$submit.prop('disabled', true);
            \$wrapper.css('cursor', 'not-allowed');
            \$submit.css('pointerEvents', 'none');

            // Re-check on every keystroke / change
            \$form.on('input change', function() { checkForm(); });

            // Tooltip: hover on the WRAPPER (fires even when button is disabled)
            \$wrapper.on('mouseenter', function() {
                if (!\$submit.prop('disabled')) return;
                var errors = getErrors();
                if (errors.length === 0) return;

                var msg = '';
                for (var i = 0; i < errors.length; i++) {
                    msg += '&bull; ' + errors[i] + '<br>';
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
