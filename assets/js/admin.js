/**
 * WPForgeHub AI Alt-Text — Admin Scripts
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // =========================================================================
        // 1. Settings Page: Dynamic Provider & Model Switcher with Manual Typing
        // =========================================================================
        var $providerSelect = $('#wpfh_provider');
        var $customRow      = $('#wpfh_custom_endpoint_row');
        var $modelSelect    = $('#wpfh_model_select');
        var $modelInput     = $('#wpfh_model_input');
        var $manualWrap     = $('#wpfh_manual_model_wrap');
        var $toggleBtn      = $('#wpfh_toggle_manual_model');
        var providersData   = (typeof wpfhAatData !== 'undefined' && wpfhAatData.providers) ? wpfhAatData.providers : {};

        function updateModelDropdown(providerKey, selectedModel) {
            var prov = providersData[providerKey];
            if (!prov) {
                return;
            }

            if (providerKey === 'custom') {
                $customRow.show();
                $modelSelect.hide();
                $toggleBtn.hide();
                $manualWrap.show();
                if (selectedModel) {
                    $modelInput.val(selectedModel);
                }
                return;
            }

            $customRow.hide();
            $modelSelect.show().empty();
            $toggleBtn.show();

            var models   = prov.models || {};
            var keys     = Object.keys(models);
            var isCustom = false;

            if (keys.length > 0) {
                var defaultToSelect = selectedModel;
                if (!defaultToSelect) {
                    defaultToSelect = prov.default_model || keys[0];
                }

                // If currently saved model is not in standard presets, treat as custom
                if (defaultToSelect && !models[defaultToSelect]) {
                    isCustom = true;
                }

                $.each(models, function(val, label) {
                    var $opt = $('<option></option>').val(val).text(label);
                    if (!isCustom && val === defaultToSelect) {
                        $opt.prop('selected', true);
                    }
                    $modelSelect.append($opt);
                });

                // Add Custom option at the end
                var $customOpt = $('<option></option>').val('__custom__').text('✏️ Custom Model (Type manually...)');
                if (isCustom) {
                    $customOpt.prop('selected', true);
                }
                $modelSelect.append($customOpt);

                if (isCustom) {
                    $manualWrap.show();
                    $toggleBtn.text('📋 Choose from List');
                    if (selectedModel) {
                        $modelInput.val(selectedModel);
                    }
                } else {
                    $manualWrap.hide();
                    $toggleBtn.text('✏️ Type Manually');
                    $modelInput.val(defaultToSelect);
                }
            } else {
                $modelSelect.hide();
                $toggleBtn.hide();
                $manualWrap.show();
            }
        }

        // Provider change
        $(document).on('change', '#wpfh_provider', function() {
            var provKey = $(this).val();
            updateModelDropdown(provKey, '');
        });

        // Model preset dropdown change
        $(document).on('change', '#wpfh_model_select', function() {
            var val   = $(this).val();
            var $wrap = $('#wpfh_manual_model_wrap');
            var $btn  = $('#wpfh_toggle_manual_model');
            var $inp  = $('#wpfh_model_input');

            if (val === '__custom__') {
                $wrap.show();
                $btn.text('📋 Choose from List');
                $inp.focus();
            } else {
                $wrap.hide();
                $btn.text('✏️ Type Manually');
                $inp.val(val);
            }
        });

        // Toggle button: Type manually <-> Choose from list
        $(document).on('click', '#wpfh_toggle_manual_model', function(e) {
            e.preventDefault();
            var $wrap = $('#wpfh_manual_model_wrap');
            var $btn  = $(this);
            var $sel  = $('#wpfh_model_select');
            var $inp  = $('#wpfh_model_input');

            if ($wrap.is(':visible')) {
                // Currently in manual mode -> switch to list mode
                $wrap.hide();
                $btn.text('✏️ Type Manually');
                if ($sel.val() === '__custom__') {
                    var firstVal = $sel.find('option:not([value="__custom__"]):first').val();
                    if (firstVal) {
                        $sel.val(firstVal);
                        $inp.val(firstVal);
                    }
                }
            } else {
                // Currently in list mode -> switch to manual mode
                $wrap.show();
                $btn.text('📋 Choose from List');
                $sel.val('__custom__');
                $inp.focus();
            }
        });

        // When user types directly into manual model input
        $(document).on('input', '#wpfh_model_input', function() {
            var typed = $(this).val();
            var $sel  = $('#wpfh_model_select');
            if ($sel.find('option[value="' + typed + '"]').length) {
                $sel.val(typed);
            } else {
                $sel.val('__custom__');
            }
        });


        // =========================================================================
        // 2. Media Library / Modal Single Image Generation (Granular Per-Image)
        // =========================================================================
        $(document).on('click', '.wpfh-aat-btn', function(e) {
            e.preventDefault();

            var $btn         = $(this);
            var $box         = $btn.closest('.wpfh-aat-media-box');
            var $status      = $btn.siblings('.wpfh-aat-status');
            var attachmentId = $btn.data('attachment-id');

            if (!attachmentId) {
                return;
            }

            // Granular per-image field selection
            var selectedFields = [];
            var overwrite      = 1;

            if ($box.length) {
                if ($box.find('.wpfh-field-alt').is(':checked')) selectedFields.push('alt_text');
                if ($box.find('.wpfh-field-title').is(':checked')) selectedFields.push('title');
                if ($box.find('.wpfh-field-caption').is(':checked')) selectedFields.push('caption');
                if ($box.find('.wpfh-field-desc').is(':checked')) selectedFields.push('description');
                overwrite = $box.find('.wpfh-field-overwrite').is(':checked') ? 1 : 0;
            } else {
                selectedFields = ['alt_text'];
            }

            if (selectedFields.length === 0) {
                $status.removeClass('is-loading is-success').addClass('is-error').text('Please check at least one field (Alt, Title, Caption, or Desc).');
                return;
            }

            $btn.prop('disabled', true);
            $status.removeClass('is-success is-error').addClass('is-loading').text(wpfhAatData.i18n.generating);

            $.ajax({
                url: wpfhAatData.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wpfh_aat_generate_single',
                    security: wpfhAatData.nonce,
                    attachment_id: attachmentId,
                    fields: selectedFields,
                    overwrite: overwrite
                },
                success: function(response) {
                    $btn.prop('disabled', false);
                    if (response.success && response.data) {
                        $status.removeClass('is-loading').addClass('is-success').text(wpfhAatData.i18n.success);

                        var altText     = response.data.alt_text;
                        var title       = response.data.title;
                        var caption     = response.data.caption;
                        var description = response.data.description;
                        var updated     = response.data.updated || [];

                        // 1. Alt Text
                        if (altText && (updated.indexOf('alt') !== -1 || !updated.length)) {
                            var $modalAltField = $('[data-setting="alt"] textarea, [data-setting="alt"] input');
                            if ($modalAltField.length) {
                                $modalAltField.val(altText).trigger('change').trigger('input');
                            }
                            var $legacyAltField = $('#attachment_alt, [name="attachments[' + attachmentId + '][image_alt]"]');
                            if ($legacyAltField.length) {
                                $legacyAltField.val(altText).trigger('change').trigger('input');
                            }
                        }

                        // 2. Title
                        if (title && (updated.indexOf('title') !== -1 || !updated.length)) {
                            var $titleField = $('[data-setting="title"] input, #attachment_title, #title, [name="attachments[' + attachmentId + '][post_title]"]');
                            if ($titleField.length) {
                                $titleField.val(title).trigger('change').trigger('input');
                            }
                        }

                        // 3. Caption
                        if (caption && (updated.indexOf('caption') !== -1 || !updated.length)) {
                            var $captionField = $('[data-setting="caption"] textarea, #attachment_caption, [name="attachments[' + attachmentId + '][post_excerpt]"]');
                            if ($captionField.length) {
                                $captionField.val(caption).trigger('change').trigger('input');
                            }
                        }

                        // 4. Description
                        if (description && (updated.indexOf('description') !== -1 || !updated.length)) {
                            var $descField = $('[data-setting="description"] textarea, #attachment_content, [name="attachments[' + attachmentId + '][post_content]"]');
                            if ($descField.length) {
                                $descField.val(description).trigger('change').trigger('input');
                            }
                        }

                        setTimeout(function() {
                            $status.fadeOut(400, function() {
                                $(this).text('').show();
                            });
                        }, 3500);
                    } else {
                        var errMsg = (response.data && response.data.message) ? response.data.message : wpfhAatData.i18n.error;
                        $status.removeClass('is-loading').addClass('is-error').html(errMsg);
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false);
                    var detail = '';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        detail = xhr.responseJSON.data.message;
                    } else if (xhr.responseText && xhr.responseText.length < 200) {
                        detail = xhr.responseText;
                    } else if (error) {
                        detail = error;
                    } else {
                        detail = 'HTTP ' + xhr.status;
                    }
                    $status.removeClass('is-loading').addClass('is-error').html(wpfhAatData.i18n.error + ' (' + detail + ')');
                }
            });
        });

    });
})(jQuery);
