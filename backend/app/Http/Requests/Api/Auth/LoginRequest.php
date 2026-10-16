<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:200'],
        ];
    }
}
