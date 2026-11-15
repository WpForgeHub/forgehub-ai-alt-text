/**
 * WPForgeHub AI Alt-Text — Admin Scripts
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Settings Page: Dynamic Provider & Model Switcher with Manual Typing Fallback
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

        if ($providerSelect.length) {
            $providerSelect.on('change', function() {
                var provKey = $(this).val();
                updateModelDropdown(provKey, '');
            });
        }

        // When user changes the preset selectbox
        if ($modelSelect.length) {
            $modelSelect.on('change', function() {
                var val = $(this).val();
                if (val === '__custom__') {
                    $manualWrap.slideDown(150);
                    $toggleBtn.text('📋 Choose from List');
                    $modelInput.focus();
                } else {
                    $manualWrap.slideUp(150);
                    $toggleBtn.text('✏️ Type Manually');
                    $modelInput.val(val);
                }
            });
        }

        // Toggle manual typing input
        if ($toggleBtn.length) {
            $toggleBtn.on('click', function(e) {
                e.preventDefault();
                if ($manualWrap.is(':visible')) {
                    $manualWrap.slideUp(150);
                    $(this).text('✏️ Type Manually');
                    if ($modelSelect.val() === '__custom__') {
                        var firstVal = $modelSelect.find('option:first').val();
                        if (firstVal && firstVal !== '__custom__') {
                            $modelSelect.val(firstVal);
                            $modelInput.val(firstVal);
                        }
                    }
                } else {
                    $manualWrap.slideDown(150);
                    $(this).text('📋 Choose from List');
                    $modelSelect.val('__custom__');
                    $modelInput.focus();
                }
            });
        }

        // Media Library / Modal Single Image Generation
        $(document).on('click', '.wpfh-aat-btn', function(e) {
            e.preventDefault();

            var $btn         = $(this);
            var $status      = $btn.siblings('.wpfh-aat-status');
            var attachmentId = $btn.data('attachment-id');

            if (!attachmentId) {
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
                    attachment_id: attachmentId
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
                    $status.removeClass('is-loading').addClass('is-error').text(wpfhAatData.i18n.error + ' (' + error + ')');
                }
            });
        });

    });
})(jQuery);
