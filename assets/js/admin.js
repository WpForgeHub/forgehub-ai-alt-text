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

                        // Helper to update DOM inputs with native bubbling events for Backbone / Gutenberg
                        function syncField(selectors, val) {
                            if (typeof val === 'undefined' || val === null || val === '') return;
                            $(selectors).each(function() {
                                this.value = val;
                                $(this).val(val);
                                try {
                                    this.dispatchEvent(new Event('input', { bubbles: true }));
                                    this.dispatchEvent(new Event('change', { bubbles: true }));
                                } catch (e) {}
                                $(this).trigger('input').trigger('change');
                            });
                        }

                        // 1. Sync directly to WordPress Backbone Media Model (instant reactivity in grid modal)
                        if (window.wp && wp.media && typeof wp.media.attachment === 'function') {
                            try {
                                var attModel = wp.media.attachment(attachmentId);
                                if (attModel) {
                                    if (altText && (updated.indexOf('alt') !== -1 || !updated.length)) {
                                        attModel.set('alt', altText);
                                    }
                                    if (title && (updated.indexOf('title') !== -1 || !updated.length)) {
                                        attModel.set('title', title);
                                    }
                                    if (caption && (updated.indexOf('caption') !== -1 || !updated.length)) {
                                        attModel.set('caption', caption);
                                    }
                                    if (description && (updated.indexOf('description') !== -1 || !updated.length)) {
                                        attModel.set('description', description);
                                    }
                                }
                            } catch (e) {
                                console.warn('[WPFH] wp.media Backbone model sync:', e);
                            }
                        }

                        // 2. Alt Text DOM sync (Two-column modal, single column modal, edit-post, list-table)
                        if (altText && (updated.indexOf('alt') !== -1 || !updated.length)) {
                            var altSelectors = [
                                '#attachment-details-two-column-alt-text',
                                '#attachment-details-alt-text',
                                '[data-setting="alt"] textarea',
                                '[data-setting="alt"] input',
                                '#attachment_alt',
                                '[name="attachments[' + attachmentId + '][image_alt]"]'
                            ].join(',');
                            syncField(altSelectors, altText);
                        }

                        // 3. Title DOM sync
                        if (title && (updated.indexOf('title') !== -1 || !updated.length)) {
                            var titleSelectors = [
                                '#attachment-details-two-column-title',
                                '#attachment-details-title',
                                '[data-setting="title"] input',
                                '#title',
                                '[name="attachments[' + attachmentId + '][post_title]"]'
                            ].join(',');
                            syncField(titleSelectors, title);
                        }

                        // 4. Caption DOM sync
                        if (caption && (updated.indexOf('caption') !== -1 || !updated.length)) {
                            var captionSelectors = [
                                '#attachment-details-two-column-caption',
                                '#attachment-details-caption',
                                '[data-setting="caption"] textarea',
                                '#attachment_caption',
                                '[name="attachments[' + attachmentId + '][post_excerpt]"]'
                            ].join(',');
                            syncField(captionSelectors, caption);
                        }

                        // 5. Description DOM sync
                        if (description && (updated.indexOf('description') !== -1 || !updated.length)) {
                            var descSelectors = [
                                '#attachment-details-two-column-description',
                                '#attachment-details-description',
                                '[data-setting="description"] textarea',
                                '#attachment_content',
                                '[name="attachments[' + attachmentId + '][post_content]"]'
                            ].join(',');
                            syncField(descSelectors, description);
                        }

                        setTimeout(function() {
                            $status.fadeOut(400, function() {
                                $(this).text('').show();
                            });
                        }, 3500);
                    } else {
                        var errMsg = (response.data && response.data.message) ? response.data.message : wpfhAatData.i18n.error;
                        $status.removeClass('is-loading').addClass('is-error').text(errMsg);
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
                    $status.removeClass('is-loading').addClass('is-error').text(wpfhAatData.i18n.error + ' (' + detail + ')');
                }
            });
        });

        // =========================================================================
        // 3. Pro Feature: Background Bulk Scanner Client
        // =========================================================================
        var $bulkStatsLabel = $('#wpfh-bulk-stats-label');
        var initialMissing = 0;
        if ($bulkStatsLabel.length && typeof wpfhAatData !== 'undefined') {
            $.post(wpfhAatData.ajax_url, {
                action: 'wpfh_aat_bulk_get_stats',
                security: wpfhAatData.nonce
            }, function(res) {
                if (res.success && res.data) {
                    var d = res.data;
                    initialMissing = parseInt(d.missing_alt, 10) || 0;
                    $bulkStatsLabel.text('Total Images: ' + d.total_images + ' | Missing Alt: ' + d.missing_alt);
                    if (d.missing_alt === 0) {
                        $('#wpfh-bulk-status-msg').text('🎉 All images in library have Alt-Text!').css('color', '#10b981');
                    }
                }
            });
        }

        $(document).on('click', '#wpfh-start-bulk-btn', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var $progressWrap = $('#wpfh-bulk-progress-wrap');
            var $progressBar  = $('#wpfh-bulk-progress-bar');
            var $statusMsg    = $('#wpfh-bulk-status-msg');
            var lastId = 0;
            var totalProcessed = 0;
            var totalFailed = 0;

            $btn.prop('disabled', true).text('⏳ Processing Batch...');
            $progressWrap.show();
            $progressBar.css('width', '0%');
            $statusMsg.text('Processing images with Vision AI...').css('color', '#2563eb');

            function runBatch() {
                $.post(wpfhAatData.ajax_url, {
                    action: 'wpfh_aat_bulk_process_batch',
                    security: wpfhAatData.nonce,
                    last_id: lastId,
                    processed_so_far: totalProcessed + totalFailed
                }, function(res) {
                    if (res.success && res.data) {
                        var d = res.data;
                        var nextLastId = parseInt(d.last_id, 10) || lastId;
                        totalProcessed += parseInt(d.batch_processed, 10) || 0;
                        totalFailed += parseInt(d.batch_failed, 10) || 0;
                        var handled = totalProcessed + totalFailed;
                        var denom = initialMissing > 0 ? initialMissing : (handled + (parseInt(d.remaining, 10) || 0));
                        var pct = denom > 0 ? Math.min(100, Math.round((handled / denom) * 100)) : 100;
                        $progressBar.css('width', pct + '%');

                        if (d.free_cap_hit) {
                            $btn.prop('disabled', false).text('🚀 Run Next Batch');
                            var capText = '✅ Batch complete: ' + totalProcessed + ' generated' + (totalFailed > 0 ? ', ' + totalFailed + ' skipped' : '') + ' (' + d.remaining + ' remaining — click Run Next Batch to continue).';
                            $statusMsg.text(capText).css('color', '#10b981');
                            $bulkStatsLabel.text('Processed: ' + totalProcessed + ' | Remaining: ' + d.remaining);
                        } else if (d.is_finished || nextLastId <= lastId) {
                            $progressBar.css('width', '100%');
                            $btn.prop('disabled', false).text('✅ Scan Complete');
                            var doneText = '🎉 Bulk scan complete: ' + totalProcessed + ' generated' + (totalFailed > 0 ? ', ' + totalFailed + ' skipped/failed.' : '!');
                            $statusMsg.text(doneText).css('color', '#10b981');
                            $bulkStatsLabel.text('Processed: ' + totalProcessed + (totalFailed > 0 ? ' | Skipped: ' + totalFailed : ''));
                        } else {
                            lastId = nextLastId;
                            $statusMsg.text('Processed: ' + totalProcessed + (totalFailed > 0 ? ' (Skipped: ' + totalFailed + ')' : '') + ' | Remaining: ' + d.remaining + ' (' + pct + '%)');
                            runBatch(); // Next chunk
                        }
                    } else {
                        var err = (res.data && res.data.message) ? res.data.message : 'Error in bulk scan.';
                        $btn.prop('disabled', false).text('🚀 Retry Bulk Scan');
                        $statusMsg.text(err).css('color', '#ef4444');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).text('🚀 Retry Bulk Scan');
                    $statusMsg.text('Server request failed during batch.').css('color', '#ef4444');
                });
            }

            runBatch();
        });

    });
})(jQuery);
