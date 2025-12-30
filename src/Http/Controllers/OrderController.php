<?php

namespace Botble\FobPaypalPro\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Ecommerce\Facades\Cart;
use Botble\Ecommerce\Facades\OrderHelper;
use Botble\Ecommerce\Models\Order;
use Botble\FobPaypalPro\Services\FobPaypalProService;
use Botble\Payment\Supports\PaymentHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends BaseController
{
    public function create(Request $request, FobPaypalProService $service): JsonResponse
    {
        $paymentData = apply_filters(PAYMENT_FILTER_PAYMENT_DATA, [], $request);

        if (empty($paymentData['amount'])) {
            $paymentData = $this->getPaymentDataFromSession($request);
        }

        if (empty($paymentData['amount'])) {
            $paymentData = $this->getPaymentDataFromCart($request);
        }

        if (empty($paymentData['amount'])) {
            return response()->json([
                'error' => true,
                'message' => trans('plugins/fob-paypal-pro::fob-paypal-pro.invalid_order'),
            ], 400);
        }

        $orderId = $service->createOrderForSmartButtons($paymentData);

        if (! $orderId) {
            return response()->json([
                'error' => true,
                'message' => $service->getErrorMessage(),
            ], 400);
        }

        return response()->json(['id' => $orderId]);
    }

    protected function getPaymentDataFromSession(Request $request): array
    {
        $token = $request->input('checkout_token') ?: session('tracked_start_checkout');

        if (! $token) {
            return [];
        }

        $order = Order::query()
            ->where('token', $token)
            ->where('is_finished', false)
            ->first();

        if (! $order) {
            $sessionData = OrderHelper::getOrderSessionData($token);
            $orderId = $sessionData['created_order_id'] ?? null;

            if ($orderId) {
                $order = Order::query()->where('id', $orderId)->first();
            }
        }

        if ($order) {
            return [
                'amount' => (float) $order->amount,
                'currency' => get_application_currency()->title,
                'order_id' => [$order->id],
                'description' => trans('plugins/payment::payment.payment_description', [
                    'order_id' => $order->id,
                    'site_url' => $request->getHost(),
                ]),
                'customer_id' => $order->user_id,
                'customer_type' => $order->user_type,
                'address' => [
                    'name' => $order->address->name ?? '',
                    'email' => $order->address->email ?? '',
                ],
            ];
        }

        return [];
    }

    protected function getPaymentDataFromCart(Request $request): array
    {
        $cart = Cart::instance('cart');

        if ($cart->isEmpty()) {
            return [];
        }

        $amount = $cart->rawTotal();

        if ($amount <= 0) {
            return [];
        }

        $customerEmail = '';
        $customerName = '';

        if (auth('customer')->check()) {
            $customer = auth('customer')->user();
            $customerEmail = $customer->email;
            $customerName = $customer->name;
        }

        return [
            'amount' => (float) $amount,
            'currency' => get_application_currency()->title,
            'description' => trans('plugins/payment::payment.payment_description', [
                'order_id' => 'Cart',
                'site_url' => $request->getHost(),
            ]),
            'customer_id' => auth('customer')->id(),
            'customer_type' => auth('customer')->check() ? get_class(auth('customer')->user()) : null,
            'address' => [
                'name' => $customerName,
                'email' => $customerEmail,
            ],
        ];
    }

    public function capture(string $orderId, FobPaypalProService $service): JsonResponse
    {
        $result = $service->captureOrderForSmartButtons($orderId);

        if (! $result) {
            return response()->json([
                'error' => true,
                'message' => $service->getErrorMessage(),
            ], 400);
        }

        $service->afterMakePaymentFromSmartButtons($result);

        return response()->json([
            'status' => 'COMPLETED',
            'redirect' => PaymentHelper::getRedirectURL(),
        ]);
    }
}
