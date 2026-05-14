<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;

class VaultUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials)
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($credentials)) {
            return null;
        }

        if (isset($credentials['email'])) {
            $model = $this->createModel();
            
            // Если в модели есть хелпер findByEmail, используем его
            if (method_exists($model, 'findByEmail')) {
                return $model::findByEmail($credentials['email']);
            }

            // Иначе ищем сами по email_bidx
            $salt = config('vault.blind_index.salt', 'default-salt');
            $bidx = hash_hmac('sha256', strtolower(trim($credentials['email'])), $salt);
            
            $query = $this->newModelQuery();
            
            // Поддержка "открытых" email-ов (как у старого admin@admin.com) 
            // ИЛИ зашифрованных через email_bidx
            $query->where(function ($q) use ($credentials, $bidx) {
                $q->where('email_bidx', $bidx)
                  ->orWhere('email', $credentials['email']);
            });

            // Добавляем остальные поля, если есть
            foreach ($credentials as $key => $value) {
                if ($key !== 'email') {
                    $query->where($key, $value);
                }
            }
            
            return $query->first();
        }

        return parent::retrieveByCredentials($credentials);
    }
}
