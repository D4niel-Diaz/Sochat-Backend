<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EndChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'chat_id' => 'required|integer|exists:chats,chat_id',
        ];
    }
}
