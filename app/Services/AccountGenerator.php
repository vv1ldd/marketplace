<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class AccountGenerator
{
    public function generateForOrder(User $user): array
    {
        $clientEmail = $user->email ?? ('user' . $user->id . '@example.com');

        $login = preg_replace('/@.*/', '', $clientEmail) . '@gmailess.com';
        $password = Str::password(12);

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
}
