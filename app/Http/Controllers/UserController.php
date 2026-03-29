<?php

namespace App\Http\Controllers;

use App\Helpers\NormalizePhone;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public static function updateOrCreate(?string $phone, array $data, ?string $ym_user_id = null): \App\Models\User
    {
        $normalizedPhone = $phone ? NormalizePhone::normalize($phone) : null;

        // Сначала попробуем найти по email
        if (!empty($data['email'])) {
            $existingByEmail = User::where('email', $data['email'])->first();
            if ($existingByEmail) {
                // Принудительно обновляем этого пользователя
                $existingByEmail->update(array_merge($data, [
                    'phone' => $normalizedPhone,
                    'ym_user_id' => $ym_user_id,
                ]));
                return $existingByEmail->refresh();
            }
        }

        // Иначе — стандартный updateOrCreate по телефону
        return User::updateOrCreate(
            ['phone' => $normalizedPhone],
            array_merge($data, [
                'phone' => $normalizedPhone,
                'ym_user_id' => $ym_user_id,
                'password' => bcrypt(Str::random(12)),
            ])
        );
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
