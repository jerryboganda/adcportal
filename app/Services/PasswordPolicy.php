<?php

namespace App\Services;

use Illuminate\Validation\Rules\Password;

/**
 * Server-side password policy (auth best practice): credential-minting
 * endpoints require 12+ characters with mixed case, a digit and a symbol.
 * Applied to staff accounts, clinic-owner signup and platform users —
 * everywhere a password grants access to clinical or control-plane data.
 */
class PasswordPolicy
{
    /** @return array<int, mixed> Laravel validation rule set */
    public static function rules(): array
    {
        return [
            'required',
            'string',
            Password::min(12)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised(),
        ];
    }
}
