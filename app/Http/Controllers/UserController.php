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
     * @param string $ym_user_id
     * @return mixed
     */
    public static function updateOrCreate(string $phone, array $data, string $ym_user_id): mixed
    {
        unset($data['phone']);

        $user = User::where('phone', NormalizePhone::normalize($phone))->first();

        if ($user) {
            $user->update($data);

            return $user->refresh();
        }

        $rand_pass = Str::random(12);

        //TODO send $rand_pass to email maybe

        return User::create([
            'phone' => NormalizePhone::normalize($phone),
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
