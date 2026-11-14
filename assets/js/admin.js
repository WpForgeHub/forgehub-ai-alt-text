/**
 * WPForgeHub AI Alt-Text — Admin Scripts
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Settings Page: Dynamic Provider Toggle
        var $providerSelect = $('#wpfh_provider');
        var $customRow      = $('#wpfh_custom_endpoint_row');
        var $modelInput     = $('#wpfh_model');

        if ($providerSelect.length) {
            $providerSelect.on('change', function() {
                var selected = $(this).val();
                if (selected === 'custom') {
                    $customRow.show();
                    $modelInput.attr('placeholder', 'llama-3.3-70b-versatile');
                } else {
                    $customRow.hide();
                    if (selected === 'gemini') {
                        $modelInput.attr('placeholder', 'gemini-1.5-flash');
                    } else if (selected === 'openai') {
                        $modelInput.attr('placeholder', 'gpt-4o-mini');
                    } else if (selected === 'claude') {
                        $modelInput.attr('placeholder', 'claude-3-5-haiku-20241022');
                    } else if (selected === 'deepseek') {
                        $modelInput.attr('placeholder', 'deepseek-chat');
                    } else if (selected === 'openrouter') {
                        $modelInput.attr('placeholder', 'google/gemini-flash-1.5');
                    }
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
