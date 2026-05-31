<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class AccountGenerator
{
    public function generateForOrder(User $user): array
    {
        $clientEmail = $user->sovereignIdentityAddress() ?? ('user' . $user->id);

        $login = preg_replace('/@.*/', '', $clientEmail) . '@gmailess.com';
        $password = $this->generatePassword();

        $meta = $user->meta ?? [];
        $meta['generated_account'] = [
            'login' => $login,
            'password' => $password,
            'created_at' => now()->toDateTimeString(),
        ];
        $user->meta = $meta;
        $user->save();

        return $meta['generated_account'];
    }

    private function generatePassword(): string
    {
        $letters = substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz', 6)), 0, 6);
        $numbers = substr(str_shuffle(str_repeat('0123456789', 4)), 0, 4);
        return $letters . $numbers . '!';
    }
}
