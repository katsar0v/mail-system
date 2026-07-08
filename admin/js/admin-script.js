/**
 * MSKD Admin Scripts
 *
 * @package MSKD
 */

(function($) {
    'use strict';

    /**
     * Initialize SlimSelect on list dropdowns
     */
    function initSlimSelect() {
        var selectElement = document.querySelector('.mskd-slimselect-lists');
        
        if (!selectElement) {
            return;
        }

        // Check if SlimSelect is available
        if (typeof SlimSelect === 'undefined') {
            console.warn('MSKD: SlimSelect not loaded, retrying...');
            setTimeout(initSlimSelect, 100);
            return;
        }

        // Initialize SlimSelect
        new SlimSelect({
            select: selectElement,
            settings: {
                placeholderText: (typeof mskd_admin !== 'undefined' && mskd_admin.strings.select_lists_placeholder) || 'Select lists...',
                searchPlaceholderText: (typeof mskd_admin !== 'undefined' && mskd_admin.strings.search_placeholder) || 'Search...',
                searchText: (typeof mskd_admin !== 'undefined' && mskd_admin.strings.no_results) || 'No results found',
                allowDeselect: true,
                closeOnSelect: false,
                showSearch: true
            }
        });
    }

    /**
     * SlimSelect instance for import target lists
     */
    var importListsSlimSelect = null;

    /**
     * Initialize SlimSelect for import target lists selector
     */
    function initImportListsSlimSelect() {
        var selectElement = document.querySelector('.mskd-slimselect-import-lists');
        
        if (!selectElement || importListsSlimSelect) {
            return;
        }

        // Check if SlimSelect is available
        if (typeof SlimSelect === 'undefined') {
            setTimeout(initImportListsSlimSelect, 100);
            return;
        }

        importListsSlimSelect = new SlimSelect({
            select: selectElement,
            settings: {
                placeholderText: (typeof mskd_admin !== 'undefined' && mskd_admin.strings.select_lists_placeholder) || 'Select lists...',
                searchPlaceholderText: (typeof mskd_admin !== 'undefined' && mskd_admin.strings.search_placeholder) || 'Search...',
                searchText: (typeof mskd_admin !== 'undefined' && mskd_admin.strings.no_results) || 'No results found',
                allowDeselect: true,
                closeOnSelect: false,
                showSearch: true
            },
            events: {
                afterChange: function() {
                    toggleImportListsWarning();
                }
            }
        });
    }

    /**
     * Toggle warning message when target lists are selected
     */
    function toggleImportListsWarning() {
        var selectElement = document.querySelector('.mskd-slimselect-import-lists');
        var warningElement = document.getElementById('mskd-target-lists-warning');
        var assignListsCheckbox = document.querySelector('input[name="assign_lists"]');
        
        if (!selectElement || !warningElement) {
            return;
        }

        var selectedValues = Array.from(selectElement.selectedOptions).map(function(opt) {
            return opt.value;
        });

        if (selectedValues.length > 0) {
            warningElement.classList.remove('mskd-hidden');
            // Disable the "assign from file" checkbox when target lists are selected
            if (assignListsCheckbox) {
                assignListsCheckbox.disabled = true;
                assignListsCheckbox.closest('.mskd-checkbox-item').classList.add('mskd-disabled');
            }
        } else {
            warningElement.classList.add('mskd-hidden');
            // Re-enable the checkbox
            if (assignListsCheckbox) {
                assignListsCheckbox.disabled = false;
                assignListsCheckbox.closest('.mskd-checkbox-item').classList.remove('mskd-disabled');
            }
        }
    }

    $(document).ready(function() {
        // Initialize SlimSelect for lists multi-select
        initSlimSelect();

        // Initialize SlimSelect for import target lists
        initImportListsSlimSelect();

        // Confirm delete
        $('.mskd-delete-link').on('click', function(e) {
            if (!confirm(mskd_admin.strings.confirm_delete)) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-dismiss notices after 5 seconds
        setTimeout(function() {
            $('.mskd-wrap .notice.is-dismissible').fadeOut();
        }, 5000);

        // SMTP Settings Toggle
        function toggleSmtpSettings() {
            var isEnabled = $('#smtp_enabled').is(':checked');
            if (isEnabled) {
                $('.mskd-smtp-setting').show();
            } else {
                $('.mskd-smtp-setting').hide();
            }
            toggleSmtpAuthSettings();
        }

        function toggleSmtpAuthSettings() {
            var isAuthEnabled = $('#smtp_auth').is(':checked');
            var isSmtpEnabled = $('#smtp_enabled').is(':checked');
            if (isAuthEnabled && isSmtpEnabled) {
                $('.mskd-smtp-auth-setting').show();
            } else {
                $('.mskd-smtp-auth-setting').hide();
            }
        }

        // Initial toggle on page load
        if ($('#smtp_enabled').length) {
            toggleSmtpSettings();

            $('#smtp_enabled').on('change', toggleSmtpSettings);
            $('#smtp_auth').on('change', toggleSmtpAuthSettings);
        }

        // Schedule Type Toggle
        function toggleScheduleFields() {
            var scheduleType = $('#schedule_type').val();
            var $absoluteFields = $('.mskd-schedule-absolute');
            var $relativeFields = $('.mskd-schedule-relative');
            var $submitBtn = $('.mskd-submit-btn');

            // Hide all schedule-specific fields first
            $absoluteFields.hide();
            $relativeFields.hide();

            // Show relevant fields based on selection
            if (scheduleType === 'absolute') {
                $absoluteFields.show();
            } else if (scheduleType === 'relative') {
                $relativeFields.show();
            }

            // Update button text
            if ($submitBtn.length && $submitBtn.data('send-now') && $submitBtn.data('schedule')) {
                if (scheduleType === 'now') {
                    $submitBtn.val($submitBtn.data('send-now'));
                } else {
                    $submitBtn.val($submitBtn.data('schedule'));
                }
            }
        }

        // Initial toggle on page load
        if ($('#schedule_type').length) {
            toggleScheduleFields();
            $('#schedule_type').on('change', toggleScheduleFields);
        }

        // Datetime picker - enforce 10-minute intervals
        if ($('.mskd-datetime-picker').length) {
            $('.mskd-datetime-picker').on('change', function() {
                var $input = $(this);
                var value = $input.val();
                
                if (value) {
                    // Parse the datetime
                    var date = new Date(value);
                    var minutes = date.getMinutes();
                    
                    // Round to nearest 10 minutes
                    var roundedMinutes = Math.round(minutes / 10) * 10;
                    if (roundedMinutes >= 60) {
                        date.setHours(date.getHours() + 1);
                        roundedMinutes = 0;
                    }
                    date.setMinutes(roundedMinutes);
                    date.setSeconds(0);
                    
                    // Format back to datetime-local format (YYYY-MM-DDTHH:MM)
                    var year = date.getFullYear();
                    var month = String(date.getMonth() + 1).padStart(2, '0');
                    var day = String(date.getDate()).padStart(2, '0');
                    var hours = String(date.getHours()).padStart(2, '0');
                    var mins = String(date.getMinutes()).padStart(2, '0');
                    
                    var formattedDate = year + '-' + month + '-' + day + 'T' + hours + ':' + mins;
                    $input.val(formattedDate);
                }
            });

            // Validate minimum datetime on form submit
            $('form').on('submit', function() {
                var $scheduleType = $('#schedule_type');
                var $datetimePicker = $('.mskd-datetime-picker');
                
                if ($scheduleType.length && $scheduleType.val() === 'absolute' && $datetimePicker.length) {
                    var selectedDate = new Date($datetimePicker.val());
                    var now = new Date();
                    
                    if (selectedDate <= now) {
                        alert(mskd_admin.strings.datetime_past || 'Моля, изберете бъдеща дата и час.');
                        return false;
                    }
                }
                return true;
            });
        }

        // SMTP Test Button
        $(document).on('click', '#mskd-smtp-test', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $result = $('#mskd-smtp-test-result');
            var originalText = $button.data('original-text');

            // Store original text if not already stored
            if (!originalText) {
                originalText = $button.text();
                $button.data('original-text', originalText);
            }

            $button.prop('disabled', true).text(mskd_admin.strings.sending);
            $result.removeClass('mskd-smtp-success mskd-smtp-error').text('');

            $.ajax({
                url: mskd_admin.ajax_url,
                type: 'POST',
                timeout: 30000,
                data: {
                    action: 'mskd_test_smtp',
                    nonce: mskd_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('mskd-smtp-success').text(response.data.message);
                    } else {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : mskd_admin.strings.error;
                        $result.addClass('mskd-smtp-error').text(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    var errorMsg = mskd_admin.strings.error;
                    if (status === 'timeout') {
                        errorMsg = mskd_admin.strings.timeout || 'Времето за изчакване изтече.';
                    }
                    $result.addClass('mskd-smtp-error').text(errorMsg);
                },
                complete: function() {
                    $button.prop('disabled', false).text($button.data('original-text'));
                }
            });
        });

        // Delete Inactive Subscribers Button
        $(document).on('click', '#mskd-delete-inactive-subscribers', function(e) {
            e.preventDefault();
            handleTruncate($(this), 'mskd_delete_inactive_subscribers', '#mskd-delete-inactive-subscribers-result', mskd_admin.strings.confirm_delete_inactive_subscribers);
        });

        // Truncate Subscribers Button
        $(document).on('click', '#mskd-truncate-subscribers', function(e) {
            e.preventDefault();
            handleTruncate($(this), 'mskd_truncate_subscribers', '#mskd-truncate-subscribers-result', mskd_admin.strings.confirm_truncate_subscribers);
        });

        // Truncate Lists Button
        $(document).on('click', '#mskd-truncate-lists', function(e) {
            e.preventDefault();
            handleTruncate($(this), 'mskd_truncate_lists', '#mskd-truncate-lists-result', mskd_admin.strings.confirm_truncate_lists);
        });

        // Truncate Queue Button
        $(document).on('click', '#mskd-truncate-queue', function(e) {
            e.preventDefault();
            handleTruncate($(this), 'mskd_truncate_queue', '#mskd-truncate-queue-result', mskd_admin.strings.confirm_truncate_queue);
        });

        /**
         * Handle truncate action with confirmation
         */
        function handleTruncate($button, action, resultSelector, confirmMessage) {
            if (!confirm(confirmMessage)) {
                return;
            }

            var $result = $(resultSelector);
            var originalText = $button.data('original-text');

            // Store original text if not already stored
            if (!originalText) {
                originalText = $button.text();
                $button.data('original-text', originalText);
            }

            $button.prop('disabled', true).text(mskd_admin.strings.processing);
            $result.removeClass('mskd-truncate-success mskd-truncate-error').text('');

            $.ajax({
                url: mskd_admin.ajax_url,
                type: 'POST',
                timeout: 30000,
                data: {
                    action: action,
                    nonce: mskd_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.addClass('mskd-truncate-success').text(response.data.message);
                    } else {
                        var errorMsg = (response.data && response.data.message) ? response.data.message : mskd_admin.strings.error;
                        $result.addClass('mskd-truncate-error').text(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    var errorMsg = mskd_admin.strings.error;
                    $result.addClass('mskd-truncate-error').text(errorMsg);
                },
                complete: function() {
                    $button.prop('disabled', false).text($button.data('original-text'));
                }
            });
        }

        // =====================================================================
        // Import/Export Page
        // =====================================================================

        // Only run import/export logic if on the import/export page
        if ($('.mskd-import-export-container').length) {
            // Toggle subscriber-specific export options
            $('#export_type').on('change', function() {
                if ($(this).val() === 'subscribers') {
                    $('.mskd-export-subscribers-options').show();
                } else {
                    $('.mskd-export-subscribers-options').hide();
                }
            });

            // Toggle subscriber-specific import options
            $('#import_type').on('change', function() {
                if ($(this).val() === 'subscribers') {
                    $('.mskd-import-subscribers-options').show();
                } else {
                    $('.mskd-import-subscribers-options').hide();
                }
            });

            // Update accepted file types based on format
            $('#import_format').on('change', function() {
                var format = $(this).val();
                $('#import_file').attr('accept', '.' + format);
            });
        }

        // =====================================================================
        // Shortcodes Page - Copy functionality
        // =====================================================================

        $('.mskd-copy-btn').on('click', function() {
            var $button = $(this);
            var targetId = $button.data('target');
            var $codeElement = $('#' + targetId);
            var shortcode = $codeElement.text();
            var originalText = $button.text();

            // Copy to clipboard
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(shortcode).then(function() {
                    showCopySuccess($button, originalText);
                }).catch(function() {
                    fallbackCopyToClipboard(shortcode, $button, originalText);
                });
            } else {
                fallbackCopyToClipboard(shortcode, $button, originalText);
            }
        });

        /**
         * Fallback copy method for older browsers
         */
        function fallbackCopyToClipboard(text, $button, originalText) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            textArea.style.top = '-9999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand('copy');
                showCopySuccess($button, originalText);
            } catch (err) {
                showCopyError($button, originalText);
            }

            document.body.removeChild(textArea);
        }

        /**
         * Show copy error feedback
         */
        function showCopyError($button, originalText) {
            if ($button.hasClass('mskd-copy-icon-btn')) {
                var $icon = $button.find('.dashicons');
                // Swap clipboard icon with X
                $icon.removeClass('dashicons-clipboard').addClass('dashicons-no');
                $button.addClass('error');
                setTimeout(function() {
                    // Restore clipboard icon
                    $icon.removeClass('dashicons-no').addClass('dashicons-clipboard');
                    $button.removeClass('error');
                }, 2000);
            } else {
                // Text button
                $button.text(mskd_admin.strings.copy_error || 'Error');
                setTimeout(function() {
                    $button.text(originalText);
                }, 2000);
            }
        }

        /**
         * Show copy success feedback
         */
        function showCopySuccess($button, originalText) {
            // Check if this is an icon-only button
            if ($button.hasClass('mskd-copy-icon-btn')) {
                var $icon = $button.find('.dashicons');
                // Swap clipboard icon with checkmark
                $icon.removeClass('dashicons-clipboard').addClass('dashicons-yes-alt');
                $button.addClass('copied');
                setTimeout(function() {
                    // Restore clipboard icon
                    $icon.removeClass('dashicons-yes-alt').addClass('dashicons-clipboard');
                    $button.removeClass('copied');
                }, 2000);
            } else {
                // Text button (shortcodes page)
                $button.text(mskd_admin.strings.copied || 'Copied!');
                $button.addClass('button-primary');
                setTimeout(function() {
                    $button.text(originalText);
                    $button.removeClass('button-primary');
                }, 2000);
            }
        }

        // =====================================================================
        // Email Content Accordion Toggle
        // =====================================================================

        function toggleAccordion($toggle) {
            var $content = $toggle.next('.mskd-accordion-content');
            var isExpanded = $toggle.attr('aria-expanded') === 'true';

            if (isExpanded) {
                $content.slideUp(200);
                $toggle.attr('aria-expanded', 'false');
                $content.attr('aria-hidden', 'true');
            } else {
                $content.slideDown(200);
                $toggle.attr('aria-expanded', 'true');
                $content.attr('aria-hidden', 'false');
            }
        }

        $('.mskd-accordion-toggle').on('click', function() {
            toggleAccordion($(this));
        });

        $('.mskd-accordion-toggle').on('keydown', function(e) {
            // Toggle on Enter or Space key
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleAccordion($(this));
            }
        });

        // =====================================================================
        // Styling Settings - Color Picker and Preview
        // =====================================================================

        // Sync color picker with text input
        function syncColorInputs() {
            // Highlight color sync
            $('#highlight_color').on('input change', function() {
                var color = $(this).val();
                $('#highlight_color_text').val(color);
                updatePreview();
            });

            $('#highlight_color_text').on('input change', function() {
                var color = $(this).val();
                if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                    $('#highlight_color').val(color);
                    updatePreview();
                }
            });

            // Button text color sync
            $('#button_text_color').on('input change', function() {
                var color = $(this).val();
                $('#button_text_color_text').val(color);
                updatePreview();
            });

            $('#button_text_color_text').on('input change', function() {
                var color = $(this).val();
                if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                    $('#button_text_color').val(color);
                    updatePreview();
                }
            });
        }

        // Update live preview
        function updatePreview() {
            var highlightColor = $('#highlight_color').val();
            var buttonTextColor = $('#button_text_color').val();
            var hoverColor = adjustBrightness(highlightColor, -20);

            $('#mskd-preview-button').css({
                'background': highlightColor,
                'color': buttonTextColor
            });

            $('#mskd-preview-link').css('color', highlightColor);

            // Add hover effect dynamically
            $('#mskd-preview-button').off('mouseenter mouseleave').on({
                mouseenter: function() {
                    $(this).css('background', hoverColor);
                },
                mouseleave: function() {
                    $(this).css('background', highlightColor);
                }
            });
        }

        // Adjust brightness of hex color
        function adjustBrightness(hex, steps) {
            hex = hex.replace('#', '');
            var r = parseInt(hex.substring(0, 2), 16);
            var g = parseInt(hex.substring(2, 4), 16);
            var b = parseInt(hex.substring(4, 6), 16);

            r = Math.max(0, Math.min(255, r + steps));
            g = Math.max(0, Math.min(255, g + steps));
            b = Math.max(0, Math.min(255, b + steps));

            return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
        }

        // Initialize color picker sync if on settings page
        if ($('#highlight_color').length) {
            syncColorInputs();
            updatePreview();
        }

        // =====================================================================
        // Subscribers Page - Batch Edit
        // =====================================================================

        // Only run batch edit logic if on the subscribers page
        if ($('.mskd-subscribers-table').length) {
            
            // SlimSelect instance for bulk lists
            var bulkListsSlimSelect = null;

            /**
             * Initialize SlimSelect for bulk list selector
             */
            function initBulkListsSlimSelect() {
                var selectElement = document.querySelector('.mskd-slimselect-bulk-lists');
                
                if (!selectElement || bulkListsSlimSelect) {
                    return;
                }

                // Check if SlimSelect is available
                if (typeof SlimSelect === 'undefined') {
                    setTimeout(initBulkListsSlimSelect, 100);
                    return;
                }

                bulkListsSlimSelect = new SlimSelect({
                    select: selectElement,
                    settings: {
                        placeholderText: (typeof mskd_admin !== 'undefined' && mskd_admin.strings.select_lists_placeholder) || 'Select lists...',
                        searchPlaceholderText: (typeof mskd_admin !== 'undefined' && mskd_admin.strings.search_placeholder) || 'Search...',
                        searchText: (typeof mskd_admin !== 'undefined' && mskd_admin.strings.no_results) || 'No results found',
                        allowDeselect: true,
                        closeOnSelect: false,
                        showSearch: true
                    }
                });
            }

            // Toggle bulk action controls based on selected action
            function syncBulkActionControls() {
                var action = $('#mskd-bulk-action').val();
                if (action === 'assign_lists' || action === 'remove_lists') {
                    $('#mskd-bulk-list-wrapper').show();
                    $('#mskd-bulk-apply').show();
                    // Initialize SlimSelect when first shown
                    initBulkListsSlimSelect();
                } else if (action === 'delete' || action === 'set_active' || action === 'set_inactive') {
                    $('#mskd-bulk-list-wrapper').hide();
                    $('#mskd-bulk-apply').show();
                } else {
                    $('#mskd-bulk-list-wrapper').hide();
                    $('#mskd-bulk-apply').hide();
                }
            }

            $('#mskd-bulk-action').on('change', syncBulkActionControls);

            // Some browsers restore the select's previous value on page load/refresh
            // without firing a change event, leaving the Apply button hidden. Sync once on init.
            syncBulkActionControls();

            // Select all checkboxes
            $('#mskd-select-all').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('.mskd-subscriber-checkbox').prop('checked', isChecked);
                updateSelectedCount();
            });

            // Update selected count on individual checkbox change
            $(document).on('change', '.mskd-subscriber-checkbox', function() {
                updateSelectedCount();
                
                // Update "select all" checkbox state
                var totalCheckboxes = $('.mskd-subscriber-checkbox').length;
                var checkedCheckboxes = $('.mskd-subscriber-checkbox:checked').length;
                
                if (checkedCheckboxes === 0) {
                    $('#mskd-select-all').prop('checked', false);
                    $('#mskd-select-all').prop('indeterminate', false);
                } else if (checkedCheckboxes === totalCheckboxes) {
                    $('#mskd-select-all').prop('checked', true);
                    $('#mskd-select-all').prop('indeterminate', false);
                } else {
                    $('#mskd-select-all').prop('checked', false);
                    $('#mskd-select-all').prop('indeterminate', true);
                }
            });

            // Update selected count display
            function updateSelectedCount() {
                var count = $('.mskd-subscriber-checkbox:checked').length;
                $('#mskd-selected-count').text(count);
            }

            // Handle bulk apply button click
            $('#mskd-bulk-apply').on('click', function(e) {
                e.preventDefault();

                var $button = $(this);
                var $result = $('#mskd-bulk-result');
                var action = $('#mskd-bulk-action').val();
                var listIds = bulkListsSlimSelect ? bulkListsSlimSelect.getSelected() : [];
                var subscriberIds = [];

                // Collect selected subscriber IDs
                $('.mskd-subscriber-checkbox:checked').each(function() {
                    subscriberIds.push($(this).val());
                });

                // Validate inputs
                if (subscriberIds.length === 0) {
                    $result.removeClass('mskd-bulk-success').addClass('mskd-bulk-error')
                        .text(mskd_admin.strings.no_subscribers_selected || 'No subscribers selected.');
                    return;
                }

                // Handle delete action
                if (action === 'delete') {
                    // Show confirmation dialog
                    var confirmMessage = (mskd_admin.strings.confirm_bulk_delete || 'Are you sure you want to delete %d subscriber(s)? This action cannot be undone.')
                        .replace('%d', subscriberIds.length);
                    if (!confirm(confirmMessage)) {
                        return;
                    }

                    // Show loading state
                    var originalText = $button.text();
                    $button.prop('disabled', true).text(mskd_admin.strings.processing || 'Processing...');
                    $result.removeClass('mskd-bulk-success mskd-bulk-error').text('');

                    // Send AJAX request
                    $.ajax({
                        url: mskd_admin.ajax_url,
                        type: 'POST',
                        timeout: 60000,
                        data: {
                            action: 'mskd_batch_delete_subscribers',
                            nonce: mskd_admin.nonce,
                            subscriber_ids: subscriberIds
                        },
                        success: function(response) {
                            if (response.success) {
                                $result.removeClass('mskd-bulk-error').addClass('mskd-bulk-success')
                                    .text(response.data.message);
                                
                                // Remove deleted rows from table
                                $('.mskd-subscriber-checkbox:checked').closest('tr').fadeOut(300, function() {
                                    $(this).remove();
                                });
                                
                                // Clear checkboxes after success
                                $('.mskd-subscriber-checkbox').prop('checked', false);
                                $('#mskd-select-all').prop('checked', false).prop('indeterminate', false);
                                updateSelectedCount();
                                
                                // Reset form
                                $('#mskd-bulk-action').val('');
                                $('#mskd-bulk-apply').hide();
                            } else {
                                var errorMsg = (response.data && response.data.message)
                                    ? response.data.message
                                    : (mskd_admin.strings.error || 'Error occurred.');
                                $result.removeClass('mskd-bulk-success').addClass('mskd-bulk-error')
                                    .text(errorMsg);
                            }
                        },
                        error: function(xhr, status, error) {
                            var errorMsg = mskd_admin.strings.error || 'Error occurred.';
                            if (status === 'timeout') {
                                errorMsg = mskd_admin.strings.timeout || 'Request timed out.';
                            }
                            $result.removeClass('mskd-bulk-success').addClass('mskd-bulk-error')
                                .text(errorMsg);
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                    return;
                }

                // Handle set active / set inactive actions
                if (action === 'set_active' || action === 'set_inactive') {
                    var targetStatus = action === 'set_active' ? 'active' : 'inactive';

                    // Show loading state
                    var originalText = $button.text();
                    $button.prop('disabled', true).text(mskd_admin.strings.processing || 'Processing...');
                    $result.removeClass('mskd-bulk-success mskd-bulk-error').text('');

                    // Send AJAX request
                    $.ajax({
                        url: mskd_admin.ajax_url,
                        type: 'POST',
                        timeout: 60000,
                        data: {
                            action: 'mskd_batch_update_subscriber_status',
                            nonce: mskd_admin.nonce,
                            subscriber_ids: subscriberIds,
                            status: targetStatus
                        },
                        success: function(response) {
                            if (response.success) {
                                $result.removeClass('mskd-bulk-error').addClass('mskd-bulk-success')
                                    .text(response.data.message);

                                // Update status badges only for the rows the server confirmed were updated.
                                var statusLabel = targetStatus === 'active'
                                    ? (mskd_admin.strings.status_active || 'Active')
                                    : (mskd_admin.strings.status_inactive || 'Inactive');
                                var updatedIds = (response.data && response.data.updated_ids) || [];

                                updatedIds.forEach(function(subscriberId) {
                                    var $badge = $('tr[data-subscriber-id="' + subscriberId + '"]').find('.mskd-status-column .mskd-status');
                                    $badge
                                        .removeClass('mskd-status-active mskd-status-inactive mskd-status-unsubscribed')
                                        .addClass('mskd-status-' + targetStatus)
                                        .text(statusLabel);
                                });

                                // Clear checkboxes after success
                                $('.mskd-subscriber-checkbox').prop('checked', false);
                                $('#mskd-select-all').prop('checked', false).prop('indeterminate', false);
                                updateSelectedCount();

                                // Reset form
                                $('#mskd-bulk-action').val('');
                                $('#mskd-bulk-apply').hide();
                            } else {
                                var errorMsg = (response.data && response.data.message)
                                    ? response.data.message
                                    : (mskd_admin.strings.error || 'Error occurred.');
                                $result.removeClass('mskd-bulk-success').addClass('mskd-bulk-error')
                                    .text(errorMsg);
                            }
                        },
                        error: function(xhr, status, error) {
                            var errorMsg = mskd_admin.strings.error || 'Error occurred.';
                            if (status === 'timeout') {
                                errorMsg = mskd_admin.strings.timeout || 'Request timed out.';
                            }
                            $result.removeClass('mskd-bulk-success').addClass('mskd-bulk-error')
                                .text(errorMsg);
                        },
                        complete: function() {
                            $button.prop('disabled', false).text(originalText);
                        }
                    });
                    return;
                }

                // Handle assign/remove lists actions
                if (!listIds || listIds.length === 0) {
                    $result.removeClass('mskd-bulk-success').addClass('mskd-bulk-error')
                        .text(mskd_admin.strings.no_lists_selected || 'No lists selected.');
                    return;
                }

                // Determine AJAX action
                var ajaxAction = action === 'assign_lists' ? 'mskd_batch_assign_lists' : 'mskd_batch_remove_lists';

                // Show loading state
                var originalText = $button.text();
                $button.prop('disabled', true).text(mskd_admin.strings.processing || 'Processing...');
                $result.removeClass('mskd-bulk-success mskd-bulk-error').text('');

                // Send AJAX request
                $.ajax({
                    url: mskd_admin.ajax_url,
                    type: 'POST',
                    timeout: 60000,
                    data: {
                        action: ajaxAction,
                        nonce: mskd_admin.nonce,
                        subscriber_ids: subscriberIds,
                        list_ids: listIds
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.removeClass('mskd-bulk-error').addClass('mskd-bulk-success')
                                .text(response.data.message);
                            
                            // Clear checkboxes after success
                            $('.mskd-subscriber-checkbox').prop('checked', false);
                            $('#mskd-select-all').prop('checked', false).prop('indeterminate', false);
                            updateSelectedCount();
                            
                            // Reset form - clear the SlimSelect and reset action dropdown
                            $('#mskd-bulk-action').val('');
                            if (bulkListsSlimSelect) {
                                bulkListsSlimSelect.setSelected([]);
                            }
                            $('#mskd-bulk-list-wrapper').hide();
                            $('#mskd-bulk-apply').hide();
                        } else {
                            var errorMsg = (response.data && response.data.message) 
                                ? response.data.message 
                                : (mskd_admin.strings.error || 'Error occurred.');
                            $result.removeClass('mskd-bulk-success').addClass('mskd-bulk-error')
                                .text(errorMsg);
                        }
                    },
                    error: function(xhr, status, error) {
                        var errorMsg = mskd_admin.strings.error || 'Error occurred.';
                        if (status === 'timeout') {
                            errorMsg = mskd_admin.strings.timeout || 'Request timed out.';
                        }
                        $result.removeClass('mskd-bulk-success').addClass('mskd-bulk-error')
                            .text(errorMsg);
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            });
        }
    });

    /**
     * Load email preview with header and footer via AJAX
     */
    function loadEmailPreview() {
        // Handle compose wizard previews (content-based)
        $('.mskd-email-preview-iframe').each(function() {
            var $iframe = $(this);
            var content = $iframe.data('content');
            var previewTarget = $iframe.attr('name');
            
            if (!content || !previewTarget) {
                return;
            }

            // Create a form to POST to the AJAX endpoint
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = mskd_admin.ajax_url + '?action=mskd_preview_email';
            form.target = previewTarget;
            form.style.display = 'none';

            // Add nonce
            var nonceInput = document.createElement('input');
            nonceInput.type = 'hidden';
            nonceInput.name = 'nonce';
            nonceInput.value = mskd_admin.preview_nonce;
            form.appendChild(nonceInput);

            // Add content
            var contentInput = document.createElement('input');
            contentInput.type = 'hidden';
            contentInput.name = 'content';
            contentInput.value = content;
            form.appendChild(contentInput);

            // Submit form to load preview in iframe
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });

        // Handle campaign previews (campaign ID-based)
        $('.mskd-campaign-preview-iframe').each(function() {
            var $iframe = $(this);
            var campaignId = $iframe.data('campaign-id');
            var previewTarget = $iframe.attr('name');
            
            if (!campaignId || !previewTarget) {
                return;
            }

            // Create a form to POST to the AJAX endpoint
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = mskd_admin.ajax_url + '?action=mskd_preview_email';
            form.target = previewTarget;
            form.style.display = 'none';

            // Add nonce
            var nonceInput = document.createElement('input');
            nonceInput.type = 'hidden';
            nonceInput.name = 'nonce';
            nonceInput.value = mskd_admin.preview_nonce;
            form.appendChild(nonceInput);

            // Add campaign ID
            var campaignInput = document.createElement('input');
            campaignInput.type = 'hidden';
            campaignInput.name = 'campaign_id';
            campaignInput.value = campaignId;
            form.appendChild(campaignInput);

            // Submit form to load preview in iframe
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });
    }

    // Load previews on page load
    $(document).ready(function() {
        loadEmailPreview();
    });

})(jQuery);
