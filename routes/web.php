<?php

use Botble\FobPaypalPro\Http\Controllers\CallbackController;
use Botble\FobPaypalPro\Http\Controllers\OrderController;
use Botble\FobPaypalPro\Http\Controllers\WebhookController;
use Botble\Theme\Facades\Theme;
use Illuminate\Support\Facades\Route;

Theme::registerRoutes(function (): void {
    Route::get('payment/fob-paypal-pro/callback', CallbackController::class)
        ->name('payments.fob-paypal-pro.callback');

    Route::prefix('payment/fob-paypal-pro')
        ->middleware(['web'])
        ->group(function (): void {
            Route::post('order/create', [OrderController::class, 'create'])
                ->name('payments.fob-paypal-pro.order.create');
            Route::post('order/{orderId}/capture', [OrderController::class, 'capture'])
                ->name('payments.fob-paypal-pro.order.capture');
        });

    Route::post('payment/fob-paypal-pro/webhook', WebhookController::class)
        ->name('payments.fob-paypal-pro.webhook')
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
});
