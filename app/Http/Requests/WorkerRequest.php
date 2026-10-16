<?php
// app/Http/Requests/WorkerRequest.php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Clean the input BEFORE validation, so the unique checks
     * compare the standard phone format (03001234567) every time.
     */
    protected function prepareForValidation(): void
    {
        $name  = $this->input('name');
        $phone = $this->input('phone_number');

        $this->merge([
            'name'         => is_string($name) ? trim(preg_replace('/\s+/', ' ', $name)) : $name,
            'phone_number' => is_string($phone) ? PhoneNumber::normalize($phone) : $phone,
        ]);
    }

    public function rules(): array
    {
        $worker = $this->route('worker'); // null on create, model on update

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('workers', 'name')->ignore($worker),
            ],
            'phone_number' => [
                'required',
                'string',
                'regex:/^03\d{9}$/',
                Rule::unique('workers', 'phone_number')->ignore($worker),
            ],
            'monthly_salary' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999.99',
            ],
            'joined_on' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'               => 'Worker name is required.',
            'name.unique'                 => 'A worker with this name already exists.',
            'phone_number.required'       => 'Phone number is required.',
            'phone_number.regex'          => 'Enter a valid Pakistani mobile number, e.g. 0300 1234567 or +92 300 1234567.',
            'phone_number.unique'         => 'This phone number is already registered to another worker.',
            'monthly_salary.required'     => 'Monthly salary is required.',
            'monthly_salary.numeric'      => 'Monthly salary must be a number.',
            'monthly_salary.gt'           => 'Monthly salary must be greater than zero.',
            'monthly_salary.max'          => 'Monthly salary is too large.',
            'joined_on.required'          => 'Joining date is required.',
            'joined_on.date_format'       => 'Joining date is not a valid date.',
            'joined_on.before_or_equal'   => 'Joining date cannot be in the future.',
        ];
    }
}
