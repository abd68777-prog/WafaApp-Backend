<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\Merchant\SubmitPaymentRequest;
use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A package and its price matrix, created or edited from the dashboard
 * (requirements §5.5). On an edit every field is optional; each price sent
 * sets that duration, and a null price withdraws it.
 */
class SavePackageRequest extends FormRequest
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
        $creating = $this->route('package') === null;
        $required = $creating ? 'required' : 'sometimes';
        /** @var Package|null $package */
        $package = $this->route('package');

        return [
            'name' => [$required, 'string', 'min:2', 'max:100', Rule::unique('packages', 'name')->ignore($package)],
            'cards_limit' => [$required, 'integer', 'min:1', 'max:20'],
            'weekly_campaigns_limit' => [$required, 'integer', 'min:0', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'prices' => [$creating ? 'required' : 'sometimes', 'array', 'min:1'],
            'prices.*.duration_months' => ['required', 'integer', Rule::in(SubmitPaymentRequest::DURATIONS), 'distinct'],
            'prices.*.price_usd' => [$creating ? 'required' : 'present', 'nullable', 'numeric', 'min:0.01', 'max:99999'],
        ];
    }
}
