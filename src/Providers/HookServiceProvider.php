<?php

namespace Botble\FobPaypalPro\Providers;

use Botble\Base\Facades\Html;
use Botble\FobPaypalPro\Forms\FobPaypalProMethodForm;
use Botble\FobPaypalPro\Services\FobPaypalProService;
use Botble\Payment\Enums\PaymentMethodEnum;
use Botble\Payment\Facades\PaymentMethods;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_filter(PAYMENT_FILTER_ADDITIONAL_PAYMENT_METHODS, [$this, 'registerFobPaypalProMethod'], 3, 2);

        $this->app->booted(function (): void {
            add_filter(PAYMENT_FILTER_AFTER_POST_CHECKOUT, [$this, 'checkoutWithFobPaypalPro'], 3, 2);
        });

        add_filter(PAYMENT_METHODS_SETTINGS_PAGE, [$this, 'addPaymentSettings'], 3);

        add_filter(BASE_FILTER_ENUM_ARRAY, function ($values, $class) {
            if ($class == PaymentMethodEnum::class) {
                $values['FOB_PAYPAL_PRO'] = FOB_PAYPAL_PRO_METHOD_NAME;
            }

            return $values;
        }, 3, 2);

        add_filter(BASE_FILTER_ENUM_LABEL, function ($value, $class) {
            if ($class == PaymentMethodEnum::class && $value == FOB_PAYPAL_PRO_METHOD_NAME) {
                $value = 'PayPal';
            }

            return $value;
        }, 3, 2);

        add_filter(BASE_FILTER_ENUM_HTML, function ($value, $class) {
            if ($class == PaymentMethodEnum::class && $value == FOB_PAYPAL_PRO_METHOD_NAME) {
                $value = Html::tag(
                    'span',
                    PaymentMethodEnum::getLabel($value),
                    ['class' => 'label-success status-label']
                )
                    ->toHtml();
            }

            return $value;
        }, 3, 2);

        add_filter(PAYMENT_FILTER_GET_SERVICE_CLASS, function ($data, $value) {
            if ($value == FOB_PAYPAL_PRO_METHOD_NAME) {
                $data = FobPaypalProService::class;
            }

            return $data;
        }, 3, 2);

        add_filter(PAYMENT_FILTER_PAYMENT_INFO_DETAIL, function ($data, $payment) {
            if ($payment->payment_channel == FOB_PAYPAL_PRO_METHOD_NAME) {
                $paymentDetail = (new FobPaypalProService())->getPaymentDetails($payment->charge_id);
                $data .= view('plugins/fob-paypal-pro::detail', ['payment' => $paymentDetail])->render();
            }

            return $data;
        }, 3, 2);

        if (defined('PAYMENT_FILTER_FOOTER_ASSETS')) {
            add_filter(PAYMENT_FILTER_FOOTER_ASSETS, function ($data) {
                return $data . view('plugins/fob-paypal-pro::assets')->render();
            }, 3);
        }
    }

    public function addPaymentSettings(?string $settings): string
    {
        return $settings . FobPaypalProMethodForm::create()->renderForm();
    }

    public function registerFobPaypalProMethod(?string $html, array $data): string
    {
        PaymentMethods::method(FOB_PAYPAL_PRO_METHOD_NAME, [
            'html' => view('plugins/fob-paypal-pro::methods', $data)->render(),
        ]);

        return $html;
    }

    public function checkoutWithFobPaypalPro(array $data, Request $request): array
    {
        if ($data['type'] !== FOB_PAYPAL_PRO_METHOD_NAME) {
            return $data;
        }

        $currentCurrency = get_application_currency();

        $currencyModel = $currentCurrency->replicate();

        $service = $this->app->make(FobPaypalProService::class);

        $supportedCurrencies = $service->supportedCurrencyCodes();

        $currency = $currentCurrency->title;

        $notSupportCurrency = false;

        if (! in_array($currency, $supportedCurrencies)) {
            $notSupportCurrency = true;

            if (! $currencyModel->query()->where('title', 'USD')->exists()) {
                $data['error'] = true;
                $data['message'] = trans(
                    'plugins/payment::payment.currency_not_supported',
                    [
                        'name' => 'PayPal Pro',
                        'currency' => $currency,
                        'currencies' => implode(', ', $supportedCurrencies),
                    ]
                );

                return $data;
            }
        }

        $paymentData = apply_filters(PAYMENT_FILTER_PAYMENT_DATA, [], $request);

        if ($notSupportCurrency) {
            $usdCurrency = $currencyModel->query()->where('title', 'USD')->first();

            $paymentData['currency'] = 'USD';
            if ($currentCurrency->is_default) {
                $paymentData['amount'] = $paymentData['amount'] * $usdCurrency->exchange_rate;
            } else {
                $paymentData['amount'] = format_price(
                    $paymentData['amount'] / $currentCurrency->exchange_rate,
                    $currentCurrency,
                    true
                );
            }
        }

        if (! $request->input('callback_url')) {
            $paymentData['callback_url'] = route('payments.fob-paypal-pro.callback');
        }

        $checkoutUrl = $service->execute($paymentData);

        if ($checkoutUrl) {
            $data['checkoutUrl'] = $checkoutUrl;
        } else {
            $data['error'] = true;
            $data['message'] = $service->getErrorMessage();
        }

        return $data;
    }
}
