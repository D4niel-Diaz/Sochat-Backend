<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BanGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_id' => 'required|string|exists:guests,guest_id',
        ];
    }
}
