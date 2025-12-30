<?php

namespace Botble\FobPaypalPro\Forms;

use Botble\Base\Facades\BaseHelper;
use Botble\Base\Forms\FieldOptions\CheckboxFieldOption;
use Botble\Base\Forms\FieldOptions\SelectFieldOption;
use Botble\Base\Forms\FieldOptions\TextFieldOption;
use Botble\Base\Forms\Fields\OnOffCheckboxField;
use Botble\Base\Forms\Fields\SelectField;
use Botble\Base\Forms\Fields\TextField;
use Botble\Payment\Concerns\Forms\HasAvailableCountriesField;
use Botble\Payment\Forms\PaymentMethodForm;

class FobPaypalProMethodForm extends PaymentMethodForm
{
    use HasAvailableCountriesField;

    public function setup(): void
    {
        parent::setup();

        $this
            ->paymentId(FOB_PAYPAL_PRO_METHOD_NAME)
            ->paymentName('PayPal')
            ->paymentDescription(trans('plugins/fob-paypal-pro::fob-paypal-pro.description'))
            ->paymentLogo(url('vendor/core/plugins/fob-paypal-pro/images/paypal-pro.svg'))
            ->paymentFeeField(FOB_PAYPAL_PRO_METHOD_NAME)
            ->paymentUrl('https://paypal.com')
            ->defaultDescriptionValue(trans('plugins/fob-paypal-pro::fob-paypal-pro.redirect_message', ['name' => 'PayPal']))
            ->paymentInstructions(view('plugins/fob-paypal-pro::instructions')->render())
            ->add(
                sprintf('payment_%s_client_id', FOB_PAYPAL_PRO_METHOD_NAME),
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/payment::payment.client_id'))
                    ->value(BaseHelper::hasDemoModeEnabled() ? '*******************************' : get_payment_setting('client_id', FOB_PAYPAL_PRO_METHOD_NAME))
            )
            ->add(
                sprintf('payment_%s_client_secret', FOB_PAYPAL_PRO_METHOD_NAME),
                'password',
                TextFieldOption::make()
                    ->label(trans('plugins/payment::payment.client_secret'))
                    ->value(BaseHelper::hasDemoModeEnabled() ? '*******************************' : get_payment_setting('client_secret', FOB_PAYPAL_PRO_METHOD_NAME))
            )
            ->add(
                sprintf('payment_%s_mode', FOB_PAYPAL_PRO_METHOD_NAME),
                OnOffCheckboxField::class,
                CheckboxFieldOption::make()
                    ->label(trans('plugins/payment::payment.live_mode'))
                    ->value(get_payment_setting('mode', FOB_PAYPAL_PRO_METHOD_NAME, true))
            )
            ->add(
                sprintf('payment_%s_button_layout', FOB_PAYPAL_PRO_METHOD_NAME),
                SelectField::class,
                SelectFieldOption::make()
                    ->label(trans('plugins/fob-paypal-pro::fob-paypal-pro.button_layout'))
                    ->choices([
                        'vertical' => trans('plugins/fob-paypal-pro::fob-paypal-pro.layout_vertical'),
                        'horizontal' => trans('plugins/fob-paypal-pro::fob-paypal-pro.layout_horizontal'),
                    ])
                    ->selected(get_payment_setting('button_layout', FOB_PAYPAL_PRO_METHOD_NAME, 'vertical'))
            )
            ->add(
                sprintf('payment_%s_button_color', FOB_PAYPAL_PRO_METHOD_NAME),
                SelectField::class,
                SelectFieldOption::make()
                    ->label(trans('plugins/fob-paypal-pro::fob-paypal-pro.button_color'))
                    ->choices([
                        'gold' => trans('plugins/fob-paypal-pro::fob-paypal-pro.color_gold'),
                        'blue' => trans('plugins/fob-paypal-pro::fob-paypal-pro.color_blue'),
                        'silver' => trans('plugins/fob-paypal-pro::fob-paypal-pro.color_silver'),
                        'white' => trans('plugins/fob-paypal-pro::fob-paypal-pro.color_white'),
                        'black' => trans('plugins/fob-paypal-pro::fob-paypal-pro.color_black'),
                    ])
                    ->selected(get_payment_setting('button_color', FOB_PAYPAL_PRO_METHOD_NAME, 'gold'))
            )
            ->add(
                sprintf('payment_%s_button_shape', FOB_PAYPAL_PRO_METHOD_NAME),
                SelectField::class,
                SelectFieldOption::make()
                    ->label(trans('plugins/fob-paypal-pro::fob-paypal-pro.button_shape'))
                    ->choices([
                        'rect' => trans('plugins/fob-paypal-pro::fob-paypal-pro.shape_rect'),
                        'pill' => trans('plugins/fob-paypal-pro::fob-paypal-pro.shape_pill'),
                    ])
                    ->selected(get_payment_setting('button_shape', FOB_PAYPAL_PRO_METHOD_NAME, 'rect'))
            )
            ->add(
                sprintf('payment_%s_button_label', FOB_PAYPAL_PRO_METHOD_NAME),
                SelectField::class,
                SelectFieldOption::make()
                    ->label(trans('plugins/fob-paypal-pro::fob-paypal-pro.button_label'))
                    ->choices([
                        'paypal' => 'PayPal',
                        'checkout' => trans('plugins/fob-paypal-pro::fob-paypal-pro.label_checkout'),
                        'buynow' => trans('plugins/fob-paypal-pro::fob-paypal-pro.label_buynow'),
                        'pay' => trans('plugins/fob-paypal-pro::fob-paypal-pro.label_pay'),
                    ])
                    ->selected(get_payment_setting('button_label', FOB_PAYPAL_PRO_METHOD_NAME, 'paypal'))
            )
            ->add(
                sprintf('payment_%s_webhook_id', FOB_PAYPAL_PRO_METHOD_NAME),
                TextField::class,
                TextFieldOption::make()
                    ->label(trans('plugins/fob-paypal-pro::fob-paypal-pro.webhook_id'))
                    ->value(BaseHelper::hasDemoModeEnabled() ? '*******************************' : get_payment_setting('webhook_id', FOB_PAYPAL_PRO_METHOD_NAME))
                    ->helperText(trans('plugins/fob-paypal-pro::fob-paypal-pro.webhook_id_helper'))
            )
            ->addAvailableCountriesField(FOB_PAYPAL_PRO_METHOD_NAME);
    }
}
