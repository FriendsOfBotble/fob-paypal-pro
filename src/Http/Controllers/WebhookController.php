<?php

namespace Botble\FobPaypalPro\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\FobPaypalPro\Services\WebhookService;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Payment\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends BaseController
{
    public function __invoke(Request $request, WebhookService $webhookService): Response
    {
        if (! $webhookService->verifySignature($request)) {
            do_action('payment_after_api_response', FOB_PAYPAL_PRO_METHOD_NAME, [
                'error' => 'Invalid webhook signature',
                'headers' => $request->headers->all(),
            ]);

            return response('Invalid signature', 401);
        }

        $payload = $request->all();
        $eventType = $payload['event_type'] ?? null;

        do_action('payment_after_api_response', FOB_PAYPAL_PRO_METHOD_NAME, [
            'webhook_event' => $eventType,
            'resource' => $payload['resource'] ?? [],
        ]);

        match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED' => $this->handleCaptureCompleted($payload),
            'PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.DECLINED' => $this->handleCaptureFailed($payload),
            'PAYMENT.CAPTURE.REFUNDED' => $this->handleRefund($payload),
            default => null,
        };

        return response('OK', 200);
    }

    protected function handleCaptureCompleted(array $payload): void
    {
        $orderId = $this->extractOrderId($payload);

        if (! $orderId) {
            return;
        }

        $payment = Payment::query()
            ->where('charge_id', $orderId)
            ->where('payment_channel', FOB_PAYPAL_PRO_METHOD_NAME)
            ->first();

        if ($payment && $payment->status !== PaymentStatusEnum::COMPLETED) {
            $payment->update(['status' => PaymentStatusEnum::COMPLETED]);
        }
    }

    protected function handleCaptureFailed(array $payload): void
    {
        $orderId = $this->extractOrderId($payload);

        if (! $orderId) {
            return;
        }

        $payment = Payment::query()
            ->where('charge_id', $orderId)
            ->where('payment_channel', FOB_PAYPAL_PRO_METHOD_NAME)
            ->first();

        if ($payment) {
            $payment->update(['status' => PaymentStatusEnum::FAILED]);
        }
    }

    protected function extractOrderId(array $payload): ?string
    {
        $resource = $payload['resource'] ?? [];

        return data_get($resource, 'supplementary_data.related_ids.order_id')
            ?? data_get($resource, 'custom_id');
    }

    protected function handleRefund(array $payload): void
    {
        $resource = $payload['resource'] ?? [];

        do_action('payment_after_api_response', FOB_PAYPAL_PRO_METHOD_NAME, [
            'action' => 'refund_webhook',
            'capture_id' => $resource['id'] ?? null,
            'amount' => $resource['amount'] ?? [],
        ]);
    }
}
