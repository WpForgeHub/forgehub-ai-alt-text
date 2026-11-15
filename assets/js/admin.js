/**
 * WPForgeHub AI Alt-Text — Admin Scripts
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Settings Page: Dynamic Provider & Model Switcher
        var $providerSelect = $('#wpfh_provider');
        var $customRow      = $('#wpfh_custom_endpoint_row');
        var $modelSelect    = $('#wpfh_model');
        var $customModel    = $('#wpfh_custom_model');
        var providersData   = (typeof wpfhAatData !== 'undefined' && wpfhAatData.providers) ? wpfhAatData.providers : {};

        function updateModelDropdown(providerKey, selectedModel) {
            var prov = providersData[providerKey];
            if (!prov) {
                return;
            }

            if (providerKey === 'custom') {
                $customRow.show();
                $modelSelect.hide();
                $customModel.show();
                if (selectedModel) {
                    $customModel.val(selectedModel);
                }
                return;
            }

            $customRow.hide();
            $customModel.hide();
            $modelSelect.show().empty();

            var models = prov.models || {};
            var keys   = Object.keys(models);

            if (keys.length > 0) {
                var defaultToSelect = selectedModel;
                if (!defaultToSelect || !models[defaultToSelect]) {
                    defaultToSelect = prov.default_model || keys[0];
                }

                $.each(models, function(val, label) {
                    var $opt = $('<option></option>').val(val).text(label);
                    if (val === defaultToSelect) {
                        $opt.prop('selected', true);
                    }
                    $modelSelect.append($opt);
                });
            } else {
                var def = prov.default_model || '';
                $modelSelect.append($('<option></option>').val(def).text(def).prop('selected', true));
            }
        }

        if ($providerSelect.length) {
            $providerSelect.on('change', function() {
                var provKey = $(this).val();
                updateModelDropdown(provKey, '');
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

                        var altText = response.data.alt_text;
                        var title   = response.data.title;

                        // 1. WordPress Media Modal fields
                        var $modalAltField = $('[data-setting="alt"] textarea, [data-setting="alt"] input');
                        if ($modalAltField.length) {
                            $modalAltField.val(altText).trigger('change');
                        }

                        // 2. Edit Media screen (post.php) fields
                        var $legacyAltField = $('#attachment_alt, [name="attachments[' + attachmentId + '][image_alt]"]');
                        if ($legacyAltField.length) {
                            $legacyAltField.val(altText).trigger('change');
                        }

                        // If title returned and modal title exists
                        if (title) {
                            var $titleField = $('[data-setting="title"] input, #attachment_title, #title');
                            if ($titleField.length) {
                                $titleField.val(title).trigger('change');
                            }
                        }

                        setTimeout(function() {
                            $status.fadeOut(400, function() {
                                $(this).text('').show();
                            });
                        }, 3000);
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
