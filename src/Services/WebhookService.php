<?php

namespace Botble\FobPaypalPro\Services;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WebhookService
{
    public function verifySignature(Request $request): bool
    {
        $webhookId = setting('payment_fob_paypal_pro_webhook_id');

        if (! $webhookId) {
            return true;
        }

        $headers = [
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
        ];

        foreach ($headers as $value) {
            if (empty($value)) {
                return false;
            }
        }

        try {
            $accessToken = $this->getAccessToken();

            if (! $accessToken) {
                return false;
            }

            $baseUrl = $this->getApiBaseUrl();

            $response = Http::withToken($accessToken)
                ->post("{$baseUrl}/v1/notifications/verify-webhook-signature", [
                    'auth_algo' => $headers['auth_algo'],
                    'cert_url' => $headers['cert_url'],
                    'transmission_id' => $headers['transmission_id'],
                    'transmission_sig' => $headers['transmission_sig'],
                    'transmission_time' => $headers['transmission_time'],
                    'webhook_id' => $webhookId,
                    'webhook_event' => $request->all(),
                ]);

            if ($response->successful()) {
                return $response->json('verification_status') === 'SUCCESS';
            }

            do_action('payment_after_api_response', FOB_PAYPAL_PRO_METHOD_NAME, [
                'error' => 'Webhook verification API failed',
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        } catch (Exception $e) {
            do_action('payment_after_api_response', FOB_PAYPAL_PRO_METHOD_NAME, [
                'error' => 'Webhook verification exception',
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function getAccessToken(): ?string
    {
        $clientId = setting('payment_fob_paypal_pro_client_id');
        $clientSecret = setting('payment_fob_paypal_pro_client_secret');

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        $baseUrl = $this->getApiBaseUrl();

        try {
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post("{$baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }
        } catch (Exception $e) {
            do_action('payment_after_api_response', FOB_PAYPAL_PRO_METHOD_NAME, [
                'error' => 'Failed to get access token',
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected function getApiBaseUrl(): string
    {
        $isLive = setting('payment_fob_paypal_pro_mode');

        return $isLive
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }
}
