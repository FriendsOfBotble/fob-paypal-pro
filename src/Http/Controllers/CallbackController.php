<?php

namespace Botble\FobPaypalPro\Http\Controllers;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\FobPaypalPro\Http\Requests\CallbackRequest;
use Botble\FobPaypalPro\Services\FobPaypalProService;
use Botble\Payment\Supports\PaymentHelper;

class CallbackController extends BaseController
{
    public function __invoke(
        CallbackRequest $request,
        FobPaypalProService $service,
        BaseHttpResponse $response
    ): BaseHttpResponse {
        $status = $service->getPaymentStatus($request);

        if (! $status) {
            return $response
                ->setError()
                ->setNextUrl(PaymentHelper::getCancelURL())
                ->withInput()
                ->setMessage(trans('plugins/fob-paypal-pro::fob-paypal-pro.payment_failed'));
        }

        $service->afterMakePayment($request->input());

        return $response
            ->setNextUrl(PaymentHelper::getRedirectURL())
            ->setMessage(trans('plugins/payment::payment.checkout_success'));
    }
}
