<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\DeletionSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A deletion the team carries out for someone who asked outside the app: by
 * email, through the web page's instructions, or by talking to support.
 */
class StoreDeletionRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::enum(DeletionSource::class)->except([DeletionSource::App])],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function source(): DeletionSource
    {
        return DeletionSource::from($this->string('source')->value());
    }

    public function note(): ?string
    {
        $note = trim((string) $this->input('note'));

        return $note === '' ? null : $note;
    }
}
