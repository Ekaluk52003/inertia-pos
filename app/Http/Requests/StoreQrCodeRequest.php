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
                // Check that no non-checked QR code exists with this table number for this restaurant.
                // If an existing QR has status = 'checked', it is considered available and a new QR
                // may be created for the same table_number.
                Rule::unique('qr_codes', 'table_number')
                    ->where(function ($query) use ($restaurantId) {
                        $query->where('restaurant_id', $restaurantId)
                              ->where('status', '!=', 'checked');
                    }),
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
            'table_number.unique' => 'A QR code for this table identifier already exists and is not marked as checked. Please use a different identifier or mark the existing QR code as checked before creating a new one.',
        ];
    }
}
