/**
 * MSKD Public Scripts
 *
 * @package MSKD
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Subscribe form handler
        $('.mskd-subscribe-form').on('submit', function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('.mskd-submit-btn');
            var $message = $form.find('.mskd-form-message');
            var originalText = $button.text();

            // Disable button and show loading
            $button.prop('disabled', true).text(mskd_public.strings.subscribing);
            $message.hide().removeClass('success error');

            $.ajax({
                url: mskd_public.ajax_url,
                type: 'POST',
                data: {
                    action: 'mskd_subscribe',
                    nonce: mskd_public.nonce,
                    email: $form.find('input[name="email"]').val(),
                    first_name: $form.find('input[name="first_name"]').val(),
                    last_name: $form.find('input[name="last_name"]').val(),
                    list_id: $form.find('input[name="list_id"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        $message.addClass('success').text(response.data.message).fadeIn();
                        $form.find('input[type="text"], input[type="email"]').val('');

                        // Fire GA4 conversion event if enabled and gtag is available
                        if (mskd_public.enable_ga4_tracking === true && typeof gtag !== 'undefined') {
                            try {
                                gtag('event', 'generate_lead', {
                                    'event_category': 'newsletter',
                                    'event_label': 'subscription_form'
                                });
                            } catch (error) {
                                // Silently fail if gtag encounters an error
                                console.warn('MSKD: Failed to send GA4 event', error);
                            }
                        }
                    } else {
                        $message.addClass('error').text(response.data.message).fadeIn();
                    }
                },
                error: function() {
                    $message.addClass('error').text(mskd_public.strings.error).fadeIn();
                },
                complete: function() {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
    });

})(jQuery);
