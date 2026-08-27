<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreHabitCheckInRequest extends FormRequest
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
            'date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'mood' => ['required', 'integer', 'between:1,5'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $date = $this->input('date');
            if (! is_string($date)) {
                return;
            }

            $habit = $this->user()?->habits()->find($this->route('habit'));
            $created = $habit?->created_at?->toDateString();
            if ($created && $date < $created) {
                $validator->errors()->add(
                    'date',
                    'You cannot check in before this habit was created.',
                );
            }
        });
    }
}
