<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Connection;

use Illuminate\Foundation\Http\FormRequest;

class VerifyConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controller handles tenant and policy authorization
    }

    public function rules(): array
    {
        return [
            'site_url'        => ['required', 'string', 'max:255'],
            'consumer_key'    => ['required', 'string', 'max:255'],
            'consumer_secret' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'site_url.required'        => 'The store site URL is required.',
            'consumer_key.required'    => 'The WooCommerce Consumer Key is required.',
            'consumer_secret.required' => 'The WooCommerce Consumer Secret is required.',
        ];
    }
}
