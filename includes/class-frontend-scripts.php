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
    $validation_error_style = isset($options['validation_error_style']) ? $options['validation_error_style'] : 'tooltip';
    $disable_submit_on_loading = isset($options['disable_submit_on_loading']) && $options['disable_submit_on_loading'];

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
    if (!$disable_copy_paste && empty($js_rules) && !$disable_submit_on_loading) {
        return;
    }

    // Add inline CSS for the "inline" error style
    if (!empty($js_rules) && $validation_error_style === 'inline') {
        wp_add_inline_style('contact-form-7', '
            span.wpcf7-form-control-wrap {
                position: relative;
                display: block;
            }
            span.fs-inline-error {
                position: absolute;
                top: 100%;
                left: 0;
                background: #b30000;
                color: #fff;
                padding: 2px 6px;
                font-size: 11px;
                margin-top: 2px;
                border-radius: 2px;
                line-height: 1.2;
                white-space: nowrap;
                z-index: 10;
            }
        ');
    }

    // Pass rules and options to JS
    wp_localize_script('jquery', 'formSettingsValidation', array(
        'rules'                   => $js_rules,
        'style'                   => $validation_error_style,
        'disable_submit_loading'  => $disable_submit_on_loading ? '1' : '0',
    ));

    $inline_js = "jQuery(document).ready(function($) {\n";

    // ── Required / length validation: disable submit + show errors ────────────────
    if (!empty($js_rules)) {
        $inline_js .= "
        var fsRules = formSettingsValidation.rules; // [{name, label, required, min_length, max_length}]
        var fsStyle = formSettingsValidation.style; // 'tooltip' or 'inline'

        // ── Tooltip element (only used if style === 'tooltip') ───────────────────
        var \$fsTooltip = null;
        if (fsStyle === 'tooltip') {
            \$fsTooltip = $('<div id=\"fs-required-tooltip\"></div>').css({
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
        }

        // ── Per-form setup ────────────────────────────────────────────────────
        function fsSetupForm(\$form) {
            var \$submit = \$form.find('input[type=\"submit\"], button[type=\"submit\"]');
            var touchedFields = {}; // tracks fields user has interacted with

            // Only include rules for fields that actually exist in this form
            var formRules = [];
            for (var i = 0; i < fsRules.length; i++) {
                if (\$form.find('[name=\"' + fsRules[i].name + '\"]').length) {
                    formRules.push(fsRules[i]);
                }
            }
            if (formRules.length === 0) return;

            // Returns an array of error objects: { rule, field_name, message }
            function getErrors() {
                var errors = [];
                for (var i = 0; i < formRules.length; i++) {
                    var rule  = formRules[i];
                    var \$field = \$form.find('[name=\"' + rule.name + '\"]');
                    var val   = \$field.val() || '';
                    var len   = val.trim().length;

                    if (rule.required && len === 0) {
                        errors.push({ rule: rule, field_name: rule.name, message: rule.label + ' is required' });
                        continue; // skip length checks if empty and required
                    }

                    if (len > 0 && rule.min_length !== null && len < rule.min_length) {
                        errors.push({ rule: rule, field_name: rule.name, message: rule.label + ' must be at least ' + rule.min_length + ' characters' });
                    }

                    if (len > 0 && rule.max_length !== null && len > rule.max_length) {
                        errors.push({ rule: rule, field_name: rule.name, message: rule.label + ' must not exceed ' + rule.max_length + ' characters' });
                    }
                }
                return errors;
            }

            function checkForm() {
                var errors = getErrors();
                \$submit.prop('disabled', errors.length > 0);
                
                if (errors.length > 0) {
                    \$wrapper.css('cursor', 'not-allowed');
                    \$submit.css('pointerEvents', 'none');
                } else {
                    \$wrapper.css('cursor', '');
                    \$submit.css('pointerEvents', '');
                    if (\$fsTooltip) \$fsTooltip.hide();
                }

                // If inline style is used, show errors under touched fields
                if (fsStyle === 'inline') {
                    // Remove all existing inline errors first
                    \$form.find('.fs-inline-error').remove();
                    
                    // Add back errors for touched fields
                    for (var i = 0; i < errors.length; i++) {
                        var err = errors[i];
                        if (touchedFields[err.field_name]) {
                            // Find the field robustly, regardless of theme or structure
                            var \$field = \$form.find('[name=\"' + err.field_name + '\"]');
                            if (\$field.length === 0) continue; // safety check
                            
                            // Find the CF7 wrapper, or if missing (custom theme), use the field itself
                            var \$wrap = \$field.closest('.wpcf7-form-control-wrap');
                            if (\$wrap.length > 0) {
                                // Append to the wrapper so it stays grouped inside
                                \$wrap.append('<span class=\"fs-inline-error\">' + err.message + '</span>');
                            } else {
                                // Fallback: put it directly after the field
                                \$field.last().after('<span class=\"fs-inline-error\">' + err.message + '</span>');
                            }
                        }
                    }
                }
            }

            // Wrap submit button so hover works even when button is disabled
            \$submit.wrap('<span class=\"fs-submit-wrapper\" style=\"display:inline-block;\"></span>');
            var \$wrapper = \$submit.parent('.fs-submit-wrapper');

            // Disable on load
            \$submit.prop('disabled', true);
            \$wrapper.css('cursor', 'not-allowed');
            \$submit.css('pointerEvents', 'none');

            // Listen to field interactions to mark them as touched
            \$form.on('blur input change', '.wpcf7-form-control', function() {
                var name = \$(this).attr('name');
                if (name) {
                    touchedFields[name] = true;
                }
                checkForm();
            });

            // Initial check just sets the button state without marking fields touched
            checkForm();

            // Tooltip / Inline triggers on hovering the disabled submit button wrapper
            \$wrapper.on('mouseenter', function() {
                if (!\$submit.prop('disabled')) return;
                var errors = getErrors();
                if (errors.length === 0) return;

                if (fsStyle === 'tooltip') {
                    var msg = '';
                    for (var i = 0; i < errors.length; i++) {
                        msg += '&bull; ' + errors[i].message + '<br>';
                    }

                    \$fsTooltip.html(msg);
                    var btnOffset = \$wrapper.offset();
                    \$fsTooltip.css({
                        top: (btnOffset.top - \$fsTooltip.outerHeight(true) - 10) + 'px',
                        left: btnOffset.left + 'px'
                    }).fadeIn(150);
                } else if (fsStyle === 'inline') {
                    // Hovering the disabled button marks ALL required fields as touched 
                    // so the user immediately sees all inline red error boxes.
                    for (var j = 0; j < formRules.length; j++) {
                        touchedFields[formRules[j].name] = true;
                    }
                    checkForm();
                }
            });

            \$wrapper.on('mouseleave', function() {
                if (fsStyle === 'tooltip' && \$fsTooltip) {
                    \$fsTooltip.fadeOut(200);
                }
            });
        }

        // Init all forms on load
        \$('.wpcf7-form').each(function() { fsSetupForm(\$(this)); });

        // Reset after successful CF7 submission
        \$(document).on('wpcf7reset', function(e) {
            var \$form = \$(e.target).find('.wpcf7-form');
            \$form.find('.fs-inline-error').remove();
            \$form.find('input[type=\"submit\"], button[type=\"submit\"]').prop('disabled', true);
            // We cannot clear the touchedFields object directly here without re-initializing, 
            // but CF7 re-triggers 'change' empty, we can just rely on the form re-init or 
            // accept that they are still \"touched\" but empty (which is fine after reset)
        });
";
    }

    // ── Disable submit button while CF7 is loading ─────────────────────────────
    if ($disable_submit_on_loading) {
        $inline_js .= "
        (function() {
            // Store the original button labels per form so we can restore them
            var fsOriginalLabels = {};

            // Before CF7 sends: lock the button
            $(document).on('wpcf7beforesubmit', '.wpcf7', function(e) {
                var \$form   = $(this);
                var formKey = \$form.attr('id') || \$form.index();
                var \$btn    = \$form.find('input[type=\"submit\"], button[type=\"submit\"]');

                // Save the original label (value for <input>, text for <button>)
                if (\$btn.is('input')) {
                    fsOriginalLabels[formKey] = \$btn.val();
                    \$btn.val('Sending\u2026');
                } else {
                    fsOriginalLabels[formKey] = \$btn.text();
                    \$btn.text('Sending\u2026');
                }
                \$btn.prop('disabled', true);
            });

            // CF7 server-side validation failed: restore so user can resubmit
            $(document).on('wpcf7invalid', '.wpcf7', function(e) {
                var \$form   = $(this);
                var formKey = \$form.attr('id') || \$form.index();
                var \$btn    = \$form.find('input[type=\"submit\"], button[type=\"submit\"]');
                var orig    = fsOriginalLabels[formKey];

                if (\$btn.is('input')) {
                    \$btn.val(orig || 'Submit');
                } else {
                    \$btn.text(orig || 'Submit');
                }
                \$btn.prop('disabled', false);
            });
        })();
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
