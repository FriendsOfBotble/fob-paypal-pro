@if (setting('payment_fob_paypal_pro_status') == 1 && setting('payment_fob_paypal_pro_client_id'))
<script src="https://www.paypal.com/sdk/js?client-id={{ setting('payment_fob_paypal_pro_client_id') }}&currency={{ get_application_currency()->title }}&intent=capture"></script>
<script>
(function() {
    var fobPaypalProInitialized = false;

    function initFobPaypalProButtons() {
        var buttonContainer = document.getElementById('fob-paypal-pro-button-container');

        if (!buttonContainer || buttonContainer.hasChildNodes()) return;
        if (typeof paypal === 'undefined') return;

        var checkoutForm = document.querySelector('form.checkout-form, form[action*="checkout"]');

        function toggleButtonContainer() {
            var paymentMethodInput = document.querySelector('input[name="payment_method"][value="{{ FOB_PAYPAL_PRO_METHOD_NAME }}"]');
            if (paymentMethodInput && buttonContainer) {
                buttonContainer.style.display = paymentMethodInput.checked ? 'block' : 'none';
            }
        }

        document.querySelectorAll('input[name="payment_method"]').forEach(function(input) {
            input.removeEventListener('change', toggleButtonContainer);
            input.addEventListener('change', toggleButtonContainer);
        });

        toggleButtonContainer();

        paypal.Buttons({
            style: {
                layout: @json(get_payment_setting('button_layout', FOB_PAYPAL_PRO_METHOD_NAME, 'vertical')),
                color: @json(get_payment_setting('button_color', FOB_PAYPAL_PRO_METHOD_NAME, 'gold')),
                shape: @json(get_payment_setting('button_shape', FOB_PAYPAL_PRO_METHOD_NAME, 'rect')),
                label: @json(get_payment_setting('button_label', FOB_PAYPAL_PRO_METHOD_NAME, 'paypal'))
            },
            createOrder: function() {
                var formData = checkoutForm ? new FormData(checkoutForm) : new FormData();

                var pathParts = window.location.pathname.split('/');
                var checkoutToken = pathParts[pathParts.length - 1] || '';
                if (checkoutToken) {
                    formData.append('checkout_token', checkoutToken);
                }

                return fetch(@json(route('payments.fob-paypal-pro.order.create')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.error) {
                        throw new Error(data.message || 'Failed to create order');
                    }
                    return data.id;
                });
            },
            onApprove: function(data) {
                return fetch(@json(route('payments.fob-paypal-pro.order.capture', ['orderId' => '__ORDER_ID__'])).replace('__ORDER_ID__', data.orderID), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json'
                    }
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(result) {
                    if (result.error) {
                        throw new Error(result.message || 'Failed to capture payment');
                    }
                    if (result.redirect) {
                        window.location.href = result.redirect;
                    }
                });
            },
            onError: function(err) {
                console.error('PayPal Smart Buttons error:', err);
                alert(@json(trans('plugins/fob-paypal-pro::fob-paypal-pro.payment_failed')));
            },
            onCancel: function() {
            }
        }).render('#fob-paypal-pro-button-container');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFobPaypalProButtons);
    } else {
        initFobPaypalProButtons();
    }

    document.addEventListener('payment-method-updated', initFobPaypalProButtons);
    document.addEventListener('checkout-updated', initFobPaypalProButtons);

    if (typeof jQuery !== 'undefined') {
        jQuery(document).on('ajaxComplete', function(event, xhr, settings) {
            if (settings.url && (settings.url.indexOf('checkout') !== -1 || settings.url.indexOf('payment') !== -1)) {
                setTimeout(initFobPaypalProButtons, 100);
            }
        });
    }

    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                var container = document.getElementById('fob-paypal-pro-button-container');
                if (container && !container.hasChildNodes()) {
                    setTimeout(initFobPaypalProButtons, 100);
                }
            }
        });
    });

    var paymentSection = document.querySelector('.payment-checkout-wrap, .payment-methods, [data-payment-methods]');
    if (paymentSection) {
        observer.observe(paymentSection, { childList: true, subtree: true });
    } else {
        observer.observe(document.body, { childList: true, subtree: true });
    }
})();
</script>
@endif
