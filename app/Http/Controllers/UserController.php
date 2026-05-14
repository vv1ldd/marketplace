<?php

namespace App\Http\Controllers;

use App\Helpers\NormalizePhone;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public static function updateOrCreate(?string $phone, array $data, ?string $ym_user_id = null): \App\Models\Customer
    {
        $normalizedPhone = $phone ? NormalizePhone::normalize($phone) : null;

        // Ищем существующего клиента через Blind Index (email/phone зашифрованы)
        $existingCustomer = null;
        if ($normalizedPhone) {
            $existingCustomer = Customer::findByPhone($normalizedPhone);
        }

        if (!$existingCustomer && !empty($data['email'])) {
            $existingCustomer = Customer::findByEmail($data['email']);
        }

        if ($existingCustomer) {
            // ОХРАННАЯ ЛОГИКА: Обновляем только те поля, которые сейчас ПУСТЫЕ
            $updateData = array_merge($data, [
                'ym_user_id' => $ym_user_id ?: $existingCustomer->ym_user_id,
            ]);
            
            if ($normalizedPhone && empty($existingCustomer->phone)) {
                $updateData['phone'] = $normalizedPhone;
            }

            $dataToUpdate = [];
            foreach ($updateData as $key => $value) {
                // Если в базе пусто, а в новых данных что-то есть — заполняем
                if (empty($existingCustomer->{$key}) && !empty($value)) {
                    $dataToUpdate[$key] = $value;
                }
            }

            if (!empty($dataToUpdate)) {
                $existingCustomer->update($dataToUpdate);
            }

            return $existingCustomer->refresh();
        }

        // Если клиента нет — создаем с нуля
        return Customer::create(
            array_merge($data, [
                'phone' => $normalizedPhone,
                'ym_user_id' => $ym_user_id,
                'password' => bcrypt(Str::random(12)),
            ])
        );
    }

    /**
     * @param string $ym_user_id
     * @return Customer|null
     */
    public static function getByYmUserId(string $ym_user_id): ?Customer
    {
        return Customer::where('ym_user_id', $ym_user_id)->first();
    }

    public static function getByPhone(string $phone): ?Customer
    {
        $phone = NormalizePhone::normalize($phone);

        return Customer::findByPhone($phone);
    }
}
