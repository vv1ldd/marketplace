<?php

namespace App\Actions\Auth;

use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Support\Serializer;
use Webauthn\PublicKeyCredential;
use Webauthn\AuthenticatorAssertionResponse;

class CustomFindPasskeyToAuthenticateAction extends FindPasskeyToAuthenticateAction
{
    public function determinePublicKeyCredential(
        string $publicKeyCredentialJson,
    ): ?PublicKeyCredential {
        $data = json_decode($publicKeyCredentialJson, true);

        // 🛡️ Bypass Spatie's flawed pre-processing if the user handle is numeric or alphanumeric,
        // because it will be double-decoded by Symfony's AuthenticatorAssertionResponseDenormalizer.
        // If we do NOT pre-process it, Symfony will safely and correctly decode it once from base64url!
        if (isset($data['response']['userHandle']) && is_string($data['response']['userHandle'])) {
            $userHandle = $data['response']['userHandle'];
            $decoded = base64_decode($userHandle, true);

            if ($decoded !== false && preg_match('/^[a-zA-Z0-9_\-]+$/', $decoded)) {
                // Keep the original base64url encoded string in the JSON to let Symfony decode it natively once!
            } else {
                // If it is NOT a clean alphanumeric string, fallback to Spatie's original logic
                if ($decoded !== false) {
                    $data['response']['userHandle'] = $decoded;
                    $publicKeyCredentialJson = json_encode($data);
                }
            }
        }

        $publicKeyCredential = Serializer::make()->fromJson(
            $publicKeyCredentialJson,
            PublicKeyCredential::class,
        );

        if (! $publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            return null;
        }

        return $publicKeyCredential;
    }
}
