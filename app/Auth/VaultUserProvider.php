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

        if (isset($credentials['entity_l1_address'])) {
            $model = $this->createModel();

            if (method_exists($model, 'findByEntityL1Address')) {
                return $model::findByEntityL1Address($credentials['entity_l1_address']);
            }

            $salt = config('vault.blind_index.salt', 'default-salt');
            $bidx = hash_hmac('sha256', strtolower(trim($credentials['entity_l1_address'])), $salt);

            return $this->newModelQuery()->where('entity_l1_address_bidx', $bidx)->first();
        }

        if (isset($credentials['email'])) {
            return null;
        }

        return parent::retrieveByCredentials($credentials);
    }
}
