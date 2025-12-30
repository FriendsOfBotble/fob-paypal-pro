<?php

namespace Botble\FobPaypalPro\Services;

use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Models\Payment;
use Botble\Payment\Services\Traits\PaymentErrorTrait;
use Botble\Payment\Supports\PaymentHelper;
use Botble\Theme\Facades\Theme;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersGetRequest;
use PayPalCheckoutSdk\Payments\CapturesRefundRequest;
use PayPalHttp\HttpResponse;

class FobPaypalProService
{
    use PaymentErrorTrait;

    protected array $itemList = [];

    protected string $paymentCurrency;

    protected float $totalAmount;

    protected string $returnUrl;

    protected string $cancelUrl;

    protected PayPalClient $client;

    protected string $transactionDescription = '';

    protected string $customer = '';

    protected bool $supportRefundOnline = true;

    public function __construct()
    {
        $this->paymentCurrency = config('plugins.payment.payment.currency');
        $this->totalAmount = 0;
        $this->client = PayPalClient::create();
    }

    public function getSupportRefundOnline(): bool
    {
        return $this->supportRefundOnline;
    }

    public function getClient(): PayPalClient
    {
        return $this->client;
    }

    public function setCurrency(string $currency): self
    {
        $this->paymentCurrency = $currency;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->paymentCurrency;
    }

    public function getCustomer(): string
    {
        return $this->customer;
    }

    public function setCustomer(string $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function setItem(array $itemData): self
    {
        if (count($itemData) === count($itemData, COUNT_RECURSIVE)) {
            $itemData = [$itemData];
        }

        foreach ($itemData as $data) {
            $amount = $data['price'] * $data['quantity'];

            $item = [
                'name' => $data['name'],
                'sku' => $data['sku'],
                'unit_amount' => [
                    'currency_code' => $this->paymentCurrency,
                    'value' => $amount,
                ],
                'quantity' => $data['quantity'],
            ];

            if ($description = Arr::get($data, 'description')) {
                $item['description'] = $description;
            }

            if ($tax = Arr::get($data, 'tax')) {
                $item['tax'] = [
                    'currency_code' => $this->paymentCurrency,
                    'value' => $tax,
                ];
            }

            if ($category = Arr::get($data, 'category')) {
                $item['category'] = $category;
            }

            $this->itemList[] = $item;
            $this->totalAmount += $amount;
        }

        $this->totalAmount = round((float) $this->totalAmount, $this->isSupportedDecimals() ? 2 : 0);

        return $this;
    }

    public function setReturnUrl(string $url): self
    {
        $this->returnUrl = $url;

        return $this;
    }

    public function setCancelUrl(string $url): self
    {
        $this->cancelUrl = $url;

        return $this;
    }

    protected function buildRequestBody(): array
    {
        return [
            'intent' => 'CAPTURE',
            'application_context' => [
                'return_url' => $this->returnUrl,
                'cancel_url' => $this->cancelUrl ?: $this->returnUrl,
                'brand_name' => Theme::getSiteTitle(),
            ],
            'purchase_units' => [
                0 => [
                    'description' => $this->transactionDescription,
                    'custom_id' => $this->customer,
                    'amount' => [
                        'currency_code' => $this->paymentCurrency,
                        'value' => (string) $this->totalAmount,
                    ],
                ],
            ],
        ];
    }

    public function createPayment(string $transactionDescription): string|null|bool
    {
        $this->transactionDescription = $transactionDescription;

        $orderRequest = new OrdersCreateRequest();
        $orderRequest->prefer('return=representation');
        $orderRequest->body = $this->buildRequestBody();
        $checkoutUrl = '';
        $paymentId = null;

        try {
            do_action('payment_before_making_api_request', FOB_PAYPAL_PRO_METHOD_NAME, $orderRequest);

            $response = $this->client->execute($orderRequest);

            do_action('payment_after_api_response', FOB_PAYPAL_PRO_METHOD_NAME, (array) $orderRequest, (array) $response);

            if ($response && $response->statusCode == 201) {
                $paymentId = $response->result->id;

                foreach ($response->result->links as $link) {
                    if ($link->rel == 'approve') {
                        $checkoutUrl = $link->href;
                    }
                }
            }
        } catch (Exception $exception) {
            do_action('payment_after_api_response', FOB_PAYPAL_PRO_METHOD_NAME, (array) $exception);

            $this->setErrorMessageAndLogging($exception, 1);

            return false;
        }

        if ($checkoutUrl && $paymentId) {
            session(['fob_paypal_pro_payment_id' => $paymentId]);

            return $checkoutUrl;
        }

        session()->forget('fob_paypal_pro_payment_id');

        return null;
    }

    public function getPaymentStatus(Request $request): bool|string
    {
        if (empty($request->input('PayerID')) || empty($request->input('token'))) {
            return false;
        }

        $paymentId = session('fob_paypal_pro_payment_id');

        try {
            $orderRequest = new OrdersCaptureRequest($paymentId);
            $orderRequest->prefer('return=representation');

            do_action('payment_before_making_api_request', FOB_PAYPAL_PRO_METHOD_NAME, $orderRequest);

            $response = $this->client->execute($orderRequest);

            do_action('payment_after_api_response', FOB_PAYPAL_PRO_METHOD_NAME, (array) $orderRequest, (array) $response);

            if ($response && $response->statusCode == 201 && $response->result->status == 'COMPLETED') {
                return $response->result->status;
            }
        } catch (Exception $exception) {
            $this->setErrorMessageAndLogging($exception, 1);
        }

        return false;
    }

    public function getPaymentDetails(string $paymentId): bool|HttpResponse
    {
        try {
            $orderRequest = new OrdersGetRequest($paymentId);

            do_action('payment_before_making_api_request', FOB_PAYPAL_PRO_METHOD_NAME, $orderRequest);

            $response = $this->client->execute($orderRequest);

            do_action('payment_after_api_response', FOB_PAYPAL_PRO_METHOD_NAME, (array) $orderRequest, (array) $response);
        } catch (Exception $exception) {
            $this->setErrorMessageAndLogging($exception, 1);

            return false;
        }

        return $response;
    }

    public function buildRefundRequestBody(float|int|string $totalAmount): array
    {
        $totalAmount = round((float) $totalAmount, 2);

        return [
            'amount' => [
                'value' => (string) $totalAmount,
                'currency_code' => $this->paymentCurrency,
            ],
        ];
    }

    public function refundOrder(string $paymentId, float|int|string $totalAmount): array
    {
        try {
            $detail = $this->getPaymentDetails($paymentId);

            if ($detail) {
                $purchaseUnits = $detail->result->purchase_units;
                $purchaseUnit = Arr::get($purchaseUnits, 0);

                $refunds = null;
                $payments = $purchaseUnit->payments;
                if ($payments && ! empty($payments->refunds)) {
                    $refunds = $payments->refunds;
                }

                if ($refunds) {
                    $purchase = Arr::first($detail->result->purchase_units);
                    $capture = Arr::first($purchase->payments->captures);

                    if (! $capture) {
                        return [
                            'error' => true,
                            'message' => trans('plugins/payment::payment.cannot_found_capture_id'),
                        ];
                    }

                    $captureId = $capture->id;

                    if ($captureId && $capture->status != 'DECLINED') {
                        $payment = Payment::query()->where('charge_id', $paymentId)->firstOrFail();
                        $paymentCurrency = $purchase->amount->currency_code;

                        if ($payment->currency !== $paymentCurrency) {
                            $currency = cms_currency()->currencies()->where('title', $paymentCurrency)->first();

                            if ($currency) {
                                $totalAmount = $totalAmount * $currency->exchange_rate;
                                $this->paymentCurrency = $paymentCurrency;
                            }
                        }

                        $refundRequest = new CapturesRefundRequest($captureId);
                        $refundRequest->body = $this->buildRefundRequestBody($totalAmount);
                        $refundRequest->prefer('return=representation');
                        $response = $this->client->execute($refundRequest);

                        if ($response && $response->statusCode == 201 && $response->result->status == 'COMPLETED') {
                            return [
                                'error' => false,
                                'status' => $response->result->status,
                                'data' => (array) $response->result,
                            ];
                        }

                        return [
                            'error' => true,
                            'status' => $response->statusCode,
                            'message' => trans('plugins/payment::payment.status_is_not_completed'),
                        ];
                    }
                }
            }

            return [
                'error' => false,
                'status' => true,
                'data' => [],
            ];
        } catch (Exception $exception) {
            $this->setErrorMessageAndLogging($exception, 1);

            return [
                'error' => true,
                'message' => $exception->getMessage(),
            ];
        }
    }

    public function execute(array $data): string|null|bool
    {
        try {
            return $this->makePayment($data);
        } catch (Exception $exception) {
            $this->setErrorMessageAndLogging($exception, 1);

            return false;
        }
    }

    public function makePayment(array $data): string|null|bool
    {
        $amount = round((float) $data['amount'], $this->isSupportedDecimals() ? 2 : 0);

        $currency = strtoupper($data['currency']);

        $queryParams = [
            'type' => FOB_PAYPAL_PRO_METHOD_NAME,
            'amount' => $amount,
            'currency' => $currency,
            'order_id' => $data['order_id'],
            'customer_id' => Arr::get($data, 'customer_id'),
            'customer_type' => Arr::get($data, 'customer_type'),
        ];

        if ($cancelUrl = $data['return_url'] ?: PaymentHelper::getCancelURL()) {
            $this->setCancelUrl($cancelUrl);
        }

        $description = Str::limit($data['description'], 50);

        return $this
            ->setReturnUrl($data['callback_url'] . '?' . http_build_query($queryParams))
            ->setCurrency($currency)
            ->setCustomer(Arr::get($data, 'address.email') ?: '')
            ->setItem([
                'name' => $description,
                'quantity' => 1,
                'price' => $amount,
                'sku' => null,
                'type' => FOB_PAYPAL_PRO_METHOD_NAME,
            ])
            ->createPayment($description);
    }

    public function afterMakePayment(array $data): ?string
    {
        $status = PaymentStatusEnum::COMPLETED;

        $chargeId = session('fob_paypal_pro_payment_id');

        $orderIds = (array) Arr::get($data, 'order_id', []);

        do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'charge_id' => $chargeId,
            'order_id' => $orderIds,
            'customer_id' => Arr::get($data, 'customer_id'),
            'customer_type' => Arr::get($data, 'customer_type'),
            'payment_channel' => FOB_PAYPAL_PRO_METHOD_NAME,
            'status' => $status,
        ]);

        session()->forget('fob_paypal_pro_payment_id');

        return $chargeId;
    }

    public function isSupportedDecimals(): bool
    {
        return ! in_array($this->getCurrency(), [
            'BIF',
            'CLP',
            'DJF',
            'GNF',
            'JPY',
            'KMF',
            'KRW',
            'MGA',
            'PYG',
            'RWF',
            'VND',
            'VUV',
            'XAF',
            'XOF',
            'XPF',
        ]);
    }

    public function supportedCurrencyCodes(): array
    {
        return [
            'AUD',
            'BRL',
            'CAD',
            'CNY',
            'CZK',
            'DKK',
            'EUR',
            'HKD',
            'HUF',
            'ILS',
            'JPY',
            'MYR',
            'MXN',
            'TWD',
            'NZD',
            'NOK',
            'PHP',
            'PLN',
            'GBP',
            'RUB',
            'SGD',
            'SEK',
            'CHF',
            'THB',
            'USD',
        ];
    }

    public function createOrderForSmartButtons(array $data): ?string
    {
        $amount = round((float) $data['amount'], $this->isSupportedDecimals() ? 2 : 0);
        $currency = strtoupper($data['currency'] ?? get_application_currency()->title);
        $description = Str::limit($data['description'] ?? 'Order payment', 50);

        $this->transactionDescription = $description;
        $this->paymentCurrency = $currency;
        $this->totalAmount = $amount;
        $this->customer = Arr::get($data, 'address.email', '');

        $orderRequest = new OrdersCreateRequest();
        $orderRequest->prefer('return=representation');
        $orderRequest->body = [
            'intent' => 'CAPTURE',
            'application_context' => [
                'brand_name' => Theme::getSiteTitle(),
                'user_action' => 'PAY_NOW',
            ],
            'purchase_units' => [
                [
                    'description' => $this->transactionDescription,
                    'custom_id' => $this->customer,
                    'amount' => [
                        'currency_code' => $this->paymentCurrency,
                        'value' => (string) $this->totalAmount,
                    ],
                ],
            ],
        ];

        try {
            do_action('payment_before_making_api_request', FOB_PAYPAL_PRO_METHOD_NAME, $orderRequest);

            $response = $this->client->execute($orderRequest);

            do_action('payment_after_api_response', FOB_PAYPAL_PRO_METHOD_NAME, (array) $orderRequest, (array) $response);

            if ($response && $response->statusCode == 201) {
                session([
                    'fob_paypal_pro_payment_id' => $response->result->id,
                    'fob_paypal_pro_order_id' => $data['order_id'] ?? [],
                    'fob_paypal_pro_customer_id' => Arr::get($data, 'customer_id'),
                    'fob_paypal_pro_customer_type' => Arr::get($data, 'customer_type'),
                ]);

                return $response->result->id;
            }
        } catch (Exception $exception) {
            do_action('payment_after_api_response', FOB_PAYPAL_PRO_METHOD_NAME, (array) $exception);
            $this->setErrorMessageAndLogging($exception, 1);
        }

        return null;
    }

    public function captureOrderForSmartButtons(string $orderId): ?object
    {
        $sessionOrderId = session('fob_paypal_pro_payment_id');
        if (! $sessionOrderId || $sessionOrderId !== $orderId) {
            $this->setErrorMessage(trans('plugins/fob-paypal-pro::fob-paypal-pro.invalid_order'));

            return null;
        }

        try {
            $orderRequest = new OrdersCaptureRequest($orderId);
            $orderRequest->prefer('return=representation');

            do_action('payment_before_making_api_request', FOB_PAYPAL_PRO_METHOD_NAME, $orderRequest);

            $response = $this->client->execute($orderRequest);

            do_action('payment_after_api_response', FOB_PAYPAL_PRO_METHOD_NAME, (array) $orderRequest, (array) $response);

            if ($response && $response->statusCode == 201 && $response->result->status == 'COMPLETED') {
                return $response->result;
            }
        } catch (Exception $exception) {
            $this->setErrorMessageAndLogging($exception, 1);
        }

        return null;
    }

    public function afterMakePaymentFromSmartButtons(object $captureResult): ?string
    {
        $orderId = $captureResult->id;
        $purchaseUnit = $captureResult->purchase_units[0] ?? null;

        do_action(PAYMENT_ACTION_PAYMENT_PROCESSED, [
            'amount' => $purchaseUnit?->amount?->value ?? 0,
            'currency' => $purchaseUnit?->amount?->currency_code ?? 'USD',
            'charge_id' => $orderId,
            'order_id' => session('fob_paypal_pro_order_id', []),
            'customer_id' => session('fob_paypal_pro_customer_id'),
            'customer_type' => session('fob_paypal_pro_customer_type'),
            'payment_channel' => FOB_PAYPAL_PRO_METHOD_NAME,
            'status' => PaymentStatusEnum::COMPLETED,
        ]);

        session()->forget([
            'fob_paypal_pro_payment_id',
            'fob_paypal_pro_order_id',
            'fob_paypal_pro_customer_id',
            'fob_paypal_pro_customer_type',
        ]);

        return $orderId;
    }
}
