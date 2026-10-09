<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A business type or an icon (requirements §4.5): a unique name, its place in
 * the list, and whether the apps offer it. Nothing is deleted — old cards and
 * shops still point at a disabled entry.
 *
 * An icon also has a key the apps draw it from. It is set once: changing it
 * would redraw every card already using the icon.
 */
class SaveLookupRequest extends FormRequest
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
        $table = $this->lookupTable();
        $existing = $this->existing();
        $creating = $existing === null;
        $presence = $creating ? 'required' : 'sometimes';

        $rules = [
            'name' => [$presence, 'string', 'min:2', 'max:100', Rule::unique($table, 'name')->ignore($existing)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($table === 'icons' && $creating) {
            $rules['key'] = ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/', Rule::unique('icons', 'key')];
        }

        return $rules;
    }

    private function lookupTable(): string
    {
        return $this->routeIs('*.admin.icons.*') ? 'icons' : 'business_types';
    }

    private function existing(): ?Model
    {
        $model = $this->route('icon') ?? $this->route('businessType');

        return $model instanceof Model ? $model : null;
    }
}
