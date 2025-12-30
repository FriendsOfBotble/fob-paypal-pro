<?php

namespace Botble\FobPaypalPro\Http\Requests;

use Botble\Support\Http\Requests\Request;

class CallbackRequest extends Request
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'PayerID' => ['required', 'string'],
        ];
    }
}
