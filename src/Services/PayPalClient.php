<?php

namespace Botble\FobPaypalPro\Services;

use PayPalCheckoutSdk\Core\PayPalEnvironment;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\ProductionEnvironment;
use PayPalCheckoutSdk\Core\SandboxEnvironment;

class PayPalClient extends PayPalHttpClient
{
    public $curlCls;

    public function __construct(PayPalEnvironment $environment)
    {
        parent::__construct($environment);
    }

    public static function create(): self
    {
        $clientId = setting('payment_fob_paypal_pro_client_id', '');
        $clientSecret = setting('payment_fob_paypal_pro_client_secret', '');
        $mode = setting('payment_fob_paypal_pro_mode');

        $environment = $mode
            ? new ProductionEnvironment($clientId, $clientSecret)
            : new SandboxEnvironment($clientId, $clientSecret);

        return new self($environment);
    }

    public static function environment(): SandboxEnvironment|ProductionEnvironment
    {
        $clientId = setting('payment_fob_paypal_pro_client_id', '');
        $clientSecret = setting('payment_fob_paypal_pro_client_secret', '');
        $mode = setting('payment_fob_paypal_pro_mode');

        return $mode
            ? new ProductionEnvironment($clientId, $clientSecret)
            : new SandboxEnvironment($clientId, $clientSecret);
    }
}
