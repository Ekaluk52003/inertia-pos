<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQrCodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $restaurantId = $this->route()->parameter('restaurant')->id;

        return [
            'table_number' => [
                'required',
                'string',
                'min:1',
                'max:255',
                // Check that no active QR code exists with this table number for this restaurant
                Rule::unique('qr_codes', 'table_number')
                    ->where('restaurant_id', $restaurantId)
                    ->where('is_active', true)
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'table_number.required' => 'The table identifier is required.',
            'table_number.string' => 'The table identifier must be text.',
            'table_number.min' => 'The table identifier must be at least 1 character.',
            'table_number.max' => 'The table identifier may not be greater than 255 characters.',
            'table_number.unique' => 'A QR code for this table identifier already exists and is active. Please use a different identifier or deactivate the existing QR code.',
        ];
    }
}
