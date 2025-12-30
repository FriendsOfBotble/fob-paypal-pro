<?php

namespace Botble\FobPaypalPro;

use Botble\PluginManagement\Abstracts\PluginOperationAbstract;
use Botble\Setting\Facades\Setting;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        Setting::delete([
            'payment_fob_paypal_pro_name',
            'payment_fob_paypal_pro_description',
            'payment_fob_paypal_pro_client_id',
            'payment_fob_paypal_pro_client_secret',
            'payment_fob_paypal_pro_mode',
            'payment_fob_paypal_pro_status',
            'payment_fob_paypal_pro_fee',
            'payment_fob_paypal_pro_fee_type',
            'payment_fob_paypal_pro_available_countries',
            'payment_fob_paypal_pro_webhook_id',
            'payment_fob_paypal_pro_button_layout',
            'payment_fob_paypal_pro_button_color',
            'payment_fob_paypal_pro_button_shape',
            'payment_fob_paypal_pro_button_label',
        ]);
    }
}
