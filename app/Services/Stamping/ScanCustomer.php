<?php

namespace App\Services\Stamping;

use App\Models\Customer;
use App\Support\PhoneNumber;

/**
 * What the merchant app is shown about the scanned customer (contract
 * ScanCustomer). The full number never leaves the server in a merchant
 * response, with one exception: the preview of a number the cashier just
 * typed and nobody registered, so they can check they typed it right.
 */
final class ScanCustomer
{
    /**
     * @return array{id: int|null, kind: string, name: string|null, phone_full: string|null, phone_masked: string|null}
     */
    public static function preview(?Customer $customer, ?string $typedPhone): array
    {
        if ($customer !== null && ! $customer->isPending()) {
            return self::describe($customer->id, 'registered', $customer->name, null, PhoneNumber::mask($customer->phone));
        }

        return self::describe($customer?->id, $customer !== null ? 'pending' : 'new', null, $customer->phone ?? $typedPhone, null);
    }

    /**
     * After the stamp is saved: the masked number only, always.
     *
     * @return array{id: int|null, kind: string, name: string|null, phone_full: string|null, phone_masked: string|null}
     */
    public static function saved(Customer $customer): array
    {
        return self::describe(
            $customer->id,
            $customer->isPending() ? 'pending' : 'registered',
            $customer->isPending() ? null : $customer->name,
            null,
            $customer->phone !== null ? PhoneNumber::mask($customer->phone) : null,
        );
    }

    /**
     * @return array{id: int|null, kind: string, name: string|null, phone_full: string|null, phone_masked: string|null}
     */
    private static function describe(?int $id, string $kind, ?string $name, ?string $phoneFull, ?string $phoneMasked): array
    {
        return [
            'id' => $id,
            'kind' => $kind,
            'name' => $name,
            'phone_full' => $phoneFull,
            'phone_masked' => $phoneMasked,
        ];
    }
}
