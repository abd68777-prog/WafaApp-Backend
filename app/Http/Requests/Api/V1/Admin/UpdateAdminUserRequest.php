<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Models\AdminUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Rename an account, change its role, or switch it on and off.
 *
 * The email can be corrected only while the account is not linked yet: after
 * the first sign-in the Clerk user is the identity, and the email is just a
 * label.
 */
class UpdateAdminUserRequest extends FormRequest
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
        /** @var AdminUser $adminUser */
        $adminUser = $this->route('adminUser');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                Rule::prohibitedIf($adminUser->isLinked()),
                'email',
                'max:255',
                Rule::unique('admin_users', 'email')->ignore($adminUser),
            ],
            'role' => ['sometimes', Rule::enum(AdminRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.prohibited' => 'The email cannot change after the account has been linked to a sign-in.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }
}
