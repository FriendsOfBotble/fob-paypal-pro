@if (setting('payment_fob_paypal_pro_status') == 1)
    <x-plugins-payment::payment-method
        :name="FOB_PAYPAL_PRO_METHOD_NAME"
        paymentName="PayPal"
        :supportedCurrencies="(new Botble\FobPaypalPro\Services\FobPaypalProService)->supportedCurrencyCodes()"
    >
        <div id="fob-paypal-pro-button-container" class="mt-3" style="display: none;"></div>

        <x-slot name="currencyNotSupportedMessage">
            <p class="mt-1 mb-0">
                {{ trans('plugins/fob-paypal-pro::fob-paypal-pro.learn_more') }}:
                {{ Html::link('https://developer.paypal.com/docs/api/reference/currency-codes', attributes: ['target' => '_blank', 'rel' => 'nofollow']) }}.
            </p>
        </x-slot>
    </x-plugins-payment::payment-method>
@endif
