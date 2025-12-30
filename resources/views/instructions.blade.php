<ol>
    <li>
        <p>
            <a
                href="https://www.paypal.com/merchantsignup/applicationChecklist?signupType=CREATE_NEW_ACCOUNT&amp;productIntentId=email_payments"
                target="_blank"
            >
                {{ trans('plugins/payment::payment.service_registration', ['name' => 'PayPal']) }}
            </a>
        </p>
    </li>
    <li>
        <p>
            {{ trans('plugins/payment::payment.after_service_registration_msg', ['name' => 'PayPal']) }}
        </p>
    </li>
    <li>
        <p>
            {{ trans('plugins/payment::payment.enter_client_id_and_secret') }}
        </p>
    </li>
</ol>

<h5 class="mt-3">{{ trans('plugins/fob-paypal-pro::fob-paypal-pro.webhook_setup') }}</h5>
<ol>
    <li>
        <p>
            Go to <a href="https://developer.paypal.com/dashboard/applications" target="_blank">PayPal Developer Dashboard</a> and select your app.
        </p>
    </li>
    <li>
        <p>
            Click "Add Webhook" and enter the webhook URL:
            <code>{{ route('payments.fob-paypal-pro.webhook') }}</code>
        </p>
    </li>
    <li>
        <p>
            Select these events: <code>PAYMENT.CAPTURE.COMPLETED</code>, <code>PAYMENT.CAPTURE.DENIED</code>, <code>PAYMENT.CAPTURE.REFUNDED</code>
        </p>
    </li>
    <li>
        <p>
            Copy the Webhook ID and paste it in the "Webhook ID" field above.
        </p>
    </li>
</ol>
