<?php

namespace App\Http\Requests;

use App\Enums\ProfileSourceEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileLookupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => [
                Rule::enum(ProfileSourceEnum::class),
            ],
            'id' => ['required_without:username'],
            'username' => ['required_without:id'],
        ];
    }
}
