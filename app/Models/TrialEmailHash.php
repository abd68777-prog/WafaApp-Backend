<?php

namespace App\Models;

use Database\Factories\TrialEmailHashFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A hashed fingerprint of the email that already used the free trial.
 *
 * It has no foreign key on purpose: it must survive account deletion, which is
 * the only way to stop someone taking the trial again with the same email.
 */
#[Fillable(['email_hash'])]
class TrialEmailHash extends Model
{
    /** @use HasFactory<TrialEmailHashFactory> */
    use HasFactory;

    public static function fingerprint(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), (string) config('app.key'));
    }

    public static function alreadyUsed(string $email): bool
    {
        return static::query()->where('email_hash', static::fingerprint($email))->exists();
    }
}
