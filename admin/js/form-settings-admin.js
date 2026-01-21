/**
 * Form Settings Admin JavaScript
 */

(function ($) {
    'use strict';

    $(document).ready(function () {

        // Helper function to show messages
        function showMessage(container, message, type) {
            const $message = $(container);
            $message.removeClass('success error').addClass(type + ' show').text(message);
            setTimeout(function () {
                $message.removeClass('show');
            }, 5000);
        }

        // ==================== Recipients Tab ====================

        // Add recipient
        $('#fs-add-recipient-btn').on('click', function () {
            const email = $('#fs-new-recipient').val().trim();

            if (!email) {
                showMessage('#fs-recipient-message', 'Please enter an email address.', 'error');
                return;
            }

            $.ajax({
                url: formSettingsAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'fs_add_recipient',
                    nonce: formSettingsAjax.nonce,
                    email: email
                },
                success: function (response) {
                    if (response.success) {
                        showMessage('#fs-recipient-message', response.data.message, 'success');
                        $('#fs-new-recipient').val('');

                        // Remove "no recipients" message if exists
                        $('.fs-no-recipients').remove();

                        // Add new recipient to list
                        const recipientHtml = `
                            <li class="fs-recipient-item">
                                <span class="fs-recipient-email">${response.data.email}</span>
                                <button type="button" class="button button-small fs-remove-recipient" data-email="${response.data.email}">
                                    Remove
                                </button>
                            </li>
                        `;
                        $('#fs-recipients-container').append(recipientHtml);
                    } else {
                        showMessage('#fs-recipient-message', response.data.message, 'error');
                    }
                },
                error: function () {
                    showMessage('#fs-recipient-message', 'An error occurred. Please try again.', 'error');
                }
            });
        });

        // Remove recipient
        $(document).on('click', '.fs-remove-recipient', function () {
            const email = $(this).data('email');
            const $item = $(this).closest('.fs-recipient-item');

            if (!confirm('Are you sure you want to remove this recipient?')) {
                return;
            }

            $.ajax({
                url: formSettingsAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'fs_remove_recipient',
                    nonce: formSettingsAjax.nonce,
                    email: email
                },
                success: function (response) {
                    if (response.success) {
                        showMessage('#fs-recipient-message', response.data.message, 'success');
                        $item.fadeOut(300, function () {
                            $(this).remove();

                            // Show "no recipients" message if list is empty
                            if ($('#fs-recipients-container').children().length === 0) {
                                $('#fs-recipients-container').html('<li class="fs-no-recipients">No recipients added yet.</li>');
                            }
                        });
                    } else {
                        showMessage('#fs-recipient-message', response.data.message, 'error');
                    }
                },
                error: function () {
                    showMessage('#fs-recipient-message', 'An error occurred. Please try again.', 'error');
                }
            });
        });

        // Scan form recipients
        $('#fs-scan-recipients-btn').on('click', function () {
            const $btn = $(this);
            $btn.prop('disabled', true).text('Scanning...');

            $.ajax({
                url: formSettingsAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'fs_scan_form_recipients',
                    nonce: formSettingsAjax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const formRecipients = response.data.form_recipients;

                        if (formRecipients.length === 0) {
                            $('#fs-form-recipients-results').html('<p style="margin-top: 20px;">No form-specific recipients found.</p>');
                            $btn.prop('disabled', false).text('Scan Form Recipients');
                            return;
                        }

                        // Build results HTML
                        let html = '<div style="margin-top: 20px;">';
                        html += '<table class="wp-list-table widefat fixed striped">';
                        html += '<thead><tr><th>Form</th><th>Recipients</th><th>Actions</th></tr></thead>';
                        html += '<tbody>';

                        formRecipients.forEach(function (formData) {
                            html += '<tr>';
                            html += '<td><strong>' + formData.form_title + '</strong></td>';
                            html += '<td>';
                            formData.recipients.forEach(function (email) {
                                html += '<span class="fs-form-recipient-email" style="display: inline-block; background: #f0f0f0; padding: 4px 8px; margin: 2px; border-radius: 3px;">';
                                html += email;
                                html += ' <button type="button" class="button button-small fs-remove-form-recipient" data-form-id="' + formData.form_id + '" data-email="' + email + '" style="margin-left: 5px; padding: 0 8px; height: 20px; line-height: 18px; font-size: 11px;">Remove</button>';
                                html += '</span>';
                            });
                            html += '</td>';
                            html += '<td><a href="' + '/wp-admin/admin.php?page=wpcf7&post=' + formData.form_id + '&action=edit" target="_blank" class="button button-small">Edit Form</a></td>';
                            html += '</tr>';
                        });

                        html += '</tbody></table>';
                        html += '</div>';

                        $('#fs-form-recipients-results').html(html);
                        showMessage('#fs-recipient-message', 'Form recipients scanned successfully!', 'success');
                    } else {
                        showMessage('#fs-recipient-message', response.data.message, 'error');
                    }

                    $btn.prop('disabled', false).text('Scan Form Recipients');
                },
                error: function () {
                    showMessage('#fs-recipient-message', 'An error occurred. Please try again.', 'error');
                    $btn.prop('disabled', false).text('Scan Form Recipients');
                }
            });
        });

        // Remove recipient from specific form
        $(document).on('click', '.fs-remove-form-recipient', function () {
            const $btn = $(this);
            const formId = $btn.data('form-id');
            const email = $btn.data('email');

            if (!confirm('Are you sure you want to remove "' + email + '" from this form?')) {
                return;
            }

            $.ajax({
                url: formSettingsAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'fs_remove_form_recipient',
                    nonce: formSettingsAjax.nonce,
                    form_id: formId,
                    email: email
                },
                success: function (response) {
                    if (response.success) {
                        showMessage('#fs-recipient-message', response.data.message, 'success');
                        // Remove the email span from the UI
                        $btn.closest('.fs-form-recipient-email').fadeOut(300, function () {
                            $(this).remove();
                        });
                    } else {
                        showMessage('#fs-recipient-message', response.data.message, 'error');
                    }
                },
                error: function () {
                    showMessage('#fs-recipient-message', 'An error occurred. Please try again.', 'error');
                }
            });
        });

        // ==================== Validation Tab ====================

        // Load form fields
        $('#fs-load-fields-btn').on('click', function () {
            const $btn = $(this);
            $btn.prop('disabled', true).text('Loading...');

            $.ajax({
                url: formSettingsAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'fs_scan_forms',
                    nonce: formSettingsAjax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const fields = response.data.fields;

                        if (fields.length === 0) {
                            showMessage('#fs-validation-message', 'No form fields found. Please create some Contact Form 7 forms first.', 'error');
                            $btn.prop('disabled', false).text('Load Form Fields');
                            return;
                        }

                        // Build validation form
                        let html = '<form id="fs-validation-form"><table class="wp-list-table widefat fixed striped"><thead><tr>';
                        html += '<th>Field Name</th><th>Type</th><th>Required</th><th>Min Length</th><th>Max Length</th>';
                        html += '</tr></thead><tbody>';

                        fields.forEach(function (field) {
                            html += '<tr>';
                            html += '<td><strong>' + field.name + '</strong></td>';
                            html += '<td>' + field.type + '</td>';
                            html += '<td><label class="fs-toggle">';
                            html += '<input type="checkbox" name="rules[' + field.name + '][required]" value="1" />';
                            html += '<span class="fs-toggle-slider"></span></label></td>';
                            html += '<td><input type="number" name="rules[' + field.name + '][min_length]" value="" min="0" class="small-text" /></td>';
                            html += '<td><input type="number" name="rules[' + field.name + '][max_length]" value="" min="0" class="small-text" /></td>';
                            html += '</tr>';
                        });

                        html += '</tbody></table>';
                        html += '<p class="submit"><button type="submit" class="button button-primary">Save Validation Rules</button></p>';
                        html += '</form>';

                        $('#fs-validation-fields').html(html);
                        showMessage('#fs-validation-message', 'Form fields loaded successfully. Set your validation rules below.', 'success');
                    } else {
                        showMessage('#fs-validation-message', response.data.message, 'error');
                    }

                    $btn.prop('disabled', false).text('Load Form Fields');
                },
                error: function () {
                    showMessage('#fs-validation-message', 'An error occurred. Please try again.', 'error');
                    $btn.prop('disabled', false).text('Load Form Fields');
                }
            });
        });

        // Save validation rules
        $(document).on('submit', '#fs-validation-form', function (e) {
            e.preventDefault();

            const formData = $(this).serialize();

            $.ajax({
                url: formSettingsAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'fs_save_validation_rules',
                    nonce: formSettingsAjax.nonce,
                    rules: $(this).serializeArray().reduce(function (obj, item) {
                        const match = item.name.match(/rules\[([^\]]+)\]\[([^\]]+)\]/);
                        if (match) {
                            const fieldName = match[1];
                            const ruleName = match[2];
                            if (!obj[fieldName]) obj[fieldName] = {};
                            obj[fieldName][ruleName] = item.value;
                        }
                        return obj;
                    }, {})
                },
                success: function (response) {
                    if (response.success) {
                        showMessage('#fs-validation-message', response.data.message, 'success');
                    } else {
                        showMessage('#fs-validation-message', response.data.message, 'error');
                    }
                },
                error: function () {
                    showMessage('#fs-validation-message', 'An error occurred. Please try again.', 'error');
                }
            });
        });

        // ==================== Scanner Tab ====================

        // Scan forms
        $('#fs-scan-forms-btn').on('click', function () {
            const $btn = $(this);
            $btn.prop('disabled', true).text('Scanning...');

            $.ajax({
                url: formSettingsAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'fs_scan_forms',
                    nonce: formSettingsAjax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        const fields = response.data.fields;
                        const stats = response.data.stats;

                        // Build results HTML
                        let html = '<div class="fs-scan-stats">';
                        html += '<p><strong>Total Forms:</strong> ' + stats.total_forms + '</p>';
                        html += '<p><strong>Unique Fields:</strong> ' + stats.total_unique_fields + '</p>';
                        html += '<p><strong>Last Scan:</strong> ' + stats.last_scan + '</p>';
                        html += '</div>';

                        if (fields.length > 0) {
                            html += '<table class="wp-list-table widefat fixed striped">';
                            html += '<thead><tr><th>Field Name</th><th>Type</th><th>Required</th><th>Used In Forms</th></tr></thead>';
                            html += '<tbody>';

                            fields.forEach(function (field) {
                                html += '<tr>';
                                html += '<td><strong>' + field.name + '</strong></td>';
                                html += '<td>' + field.type + '</td>';
                                html += '<td>' + (field.required ? 'Yes' : 'No') + '</td>';
                                html += '<td class="fs-field-forms">';
                                field.forms.forEach(function (form) {
                                    html += '<span>' + form.title + '</span>';
                                });
                                html += '</td>';
                                html += '</tr>';
                            });

                            html += '</tbody></table>';
                        } else {
                            html += '<p>No form fields found.</p>';
                        }

                        $('#fs-scan-results').html(html);
                        showMessage('#fs-scan-message', 'Scan completed successfully!', 'success');
                    } else {
                        showMessage('#fs-scan-message', response.data.message, 'error');
                    }

                    $btn.prop('disabled', false).text('Scan All Forms');
                },
                error: function () {
                    showMessage('#fs-scan-message', 'An error occurred. Please try again.', 'error');
                    $btn.prop('disabled', false).text('Scan All Forms');
                }
            });
        });

        // ==================== Templates Tab ====================

        // Save template
        $('#fs-template-form').on('submit', function (e) {
            e.preventDefault();

            const templateData = {
                action: 'fs_save_email_template',
                nonce: formSettingsAjax.nonce,
                template_id: $('#fs-template-id').val(),
                template_name: $('#fs-template-name').val(),
                template_subject: $('#fs-template-subject').val(),
                template_body: $('#fs-template-body').val(),
                template_active: $('#fs-template-active').is(':checked') ? '1' : '0'
            };

            $.ajax({
                url: formSettingsAjax.ajax_url,
                type: 'POST',
                data: templateData,
                success: function (response) {
                    if (response.success) {
                        showMessage('#fs-template-message', response.data.message, 'success');
                        setTimeout(function () {
                            location.reload();
                        }, 1500);
                    } else {
                        showMessage('#fs-template-message', response.data.message, 'error');
                    }
                },
                error: function () {
                    showMessage('#fs-template-message', 'An error occurred. Please try again.', 'error');
                }
            });
        });

        // Reset template form
        $('#fs-reset-template-form').on('click', function () {
            $('#fs-template-form')[0].reset();
            $('#fs-template-id').val('');
        });

        // Edit template
        $(document).on('click', '.fs-edit-template', function () {
            const template = $(this).data('template');

            $('#fs-template-id').val(template.id);
            $('#fs-template-name').val(template.name);
            $('#fs-template-subject').val(template.subject);
            $('#fs-template-body').val(template.body);
            $('#fs-template-active').prop('checked', template.active);

            $('html, body').animate({
                scrollTop: $('.fs-template-editor').offset().top - 50
            }, 500);
        });

        // Delete template
        $(document).on('click', '.fs-delete-template', function () {
            if (!confirm('Are you sure you want to delete this template?')) {
                return;
            }

            const templateId = $(this).data('id');

            $.ajax({
                url: formSettingsAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'fs_delete_email_template',
                    nonce: formSettingsAjax.nonce,
                    template_id: templateId
                },
                success: function (response) {
                    if (response.success) {
                        showMessage('#fs-template-message', response.data.message, 'success');
                        setTimeout(function () {
                            location.reload();
                        }, 1500);
                    } else {
                        showMessage('#fs-template-message', response.data.message, 'error');
                    }
                },
                error: function () {
                    showMessage('#fs-template-message', 'An error occurred. Please try again.', 'error');
                }
            });
        });

        // Activate template
        $(document).on('click', '.fs-activate-template', function () {
            const templateId = $(this).data('id');

            $.ajax({
                url: formSettingsAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'fs_set_active_template',
                    nonce: formSettingsAjax.nonce,
                    template_id: templateId
                },
                success: function (response) {
                    if (response.success) {
                        showMessage('#fs-template-message', response.data.message, 'success');
                        setTimeout(function () {
                            location.reload();
                        }, 1500);
                    } else {
                        showMessage('#fs-template-message', response.data.message, 'error');
                    }
                },
                error: function () {
                    showMessage('#fs-template-message', 'An error occurred. Please try again.', 'error');
                }
            });
        });

    });

})(jQuery);
