<?php

namespace App\Http\Requests;

use App\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(array_column(LeadStatus::cases(), 'value'))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'follow_up_at' => ['nullable', 'date'],
            'meeting_at' => ['nullable', 'date'],
            'meeting_with' => ['nullable', 'integer', 'exists:users,id'],
            'meeting_notes' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
