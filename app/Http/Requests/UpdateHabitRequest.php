<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHabitRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
      return [
        'name' => ['sometimes', 'string', 'max:255'],
        'question' => ['sometimes', 'nullable', 'string', 'max:255'],
        'color' => [
          'sometimes',
          'required',
          'regex:/^#[0-9A-Fa-f]{6}$/',
        ],
        'note' => ['sometimes', 'nullable', 'string'],

        'type' => [
            'sometimes',
            'required',
            Rule::in(['build', 'quit']),
        ],

        'frequency_type' => [
          'sometimes',
          'required',
          Rule::in([
            'daily',
            'every_x_days',
            'x_times_per_week',
            'x_times_in_y_days',
          ]),
        ],

        'frequency_count' => [
          'sometimes',
          'nullable',
          'integer',
          'min:1',
          'required_if:frequency_type,every_x_days,x_times_per_week,x_times_in_y_days',
          Rule::when($this->input('frequency_type') === 'x_times_per_week', ['max:7']),
          Rule::when($this->input('frequency_type') === 'x_times_in_y_days', ['max:31']),
        ],

        'frequency_period_days' => [
          'sometimes',
          'nullable',
          'integer',
          'min:1',
          'required_if:frequency_type,x_times_in_y_days',
        ],

        'reminder_time' => [
          'sometimes',
          'nullable',
          'date_format:H:i',
        ],
      ];
    }
}
