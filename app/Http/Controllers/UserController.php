<?php

namespace App\Http\Controllers;

use App\Helpers\NormalizePhone;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * @param string $phone
     * @param array $data
     * @param string|null $ym_user_id
     * @return mixed
     */
    public static function updateOrCreate(string $phone, array $data, string $ym_user_id = null): mixed
    {
        unset($data['phone']);

        $normalizedPhone = NormalizePhone::normalize($phone);
        $user = User::where('phone', $normalizedPhone)->first();

        if (isset($data['email'])) {
            $exists = User::where('email', $data['email'])
                ->when($user, fn($q) => $q->where('id', '!=', $user->id))
                ->exists();

            if ($exists) {
                unset($data['email']);
            }
        }

        if ($user) {
            $user->update($data);
            return $user->refresh();
        }

        $rand_pass = Str::random(12);

        return User::create([
            'phone' => $normalizedPhone,
            'ym_user_id' => $ym_user_id,
            ...$data,
            'password' => bcrypt($rand_pass),
        ]);
    }

    /**
     * @param string $ym_user_id
     * @return User|null
     */
    public static function getByYmUserId(string $ym_user_id): ?User
    {
        return User::where('ym_user_id', $ym_user_id)->first();
    }

    public static function getByPhone(string $phone): ?User
    {
        $phone = NormalizePhone::normalize($phone);

        return User::where('phone', $phone)->first();
    }
}
