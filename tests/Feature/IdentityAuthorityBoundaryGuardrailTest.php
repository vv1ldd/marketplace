<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class IdentityAuthorityBoundaryGuardrailTest extends TestCase
{
    /**
     * Credential semantics must not spread through the application layer.
     *
     * The baseline below is a migration ceiling for known legacy code. Lower it
     * whenever a legacy exception is removed.
     */
    public function test_passkey_and_webauthn_verification_dependencies_do_not_expand_outside_known_legacy_boundaries(): void
    {
        $legacyBaseline = [
            'app/Actions/Auth/CustomFindPasskeyToAuthenticateAction.php' => 7,
            'app/Actions/Auth/IntentStorePasskeyAction.php' => 3,
            'app/Http/Controllers/Auth/PasskeyAuthenticateController.php' => 5,
            'app/Http/Controllers/Auth/PasskeyAuthenticationOptionsController.php' => 3,
            'app/Http/Controllers/CabinetController.php' => 9,
            'app/Http/Controllers/PartnerDashboardController.php' => 9,
            'app/Http/Controllers/PartnerRegistrationController.php' => 19,
            'app/Models/SovereignBalanceRequest.php' => 1,
            'app/Providers/AppServiceProvider.php' => 2,
            'app/Services/BuyerWalletTransactionService.php' => 10,
            'app/Services/IntentLedgerService.php' => 1,
            'app/Services/L1IdentityService.php' => 1,
        ];

        $violations = [];

        foreach ($this->appPhpFiles() as $file) {
            $relativePath = $this->relativePath($file->getPathname());
            $forbiddenLines = $this->credentialBoundaryLines($file->getPathname());

            if ($forbiddenLines === []) {
                continue;
            }

            $allowedCount = $legacyBaseline[$relativePath] ?? 0;

            if (count($forbiddenLines) <= $allowedCount) {
                continue;
            }

            $violations[] = sprintf(
                '%s has %d credential-boundary matches; allowed baseline is %d. Lines: %s',
                $relativePath,
                count($forbiddenLines),
                $allowedCount,
                implode(', ', array_slice($forbiddenLines, 0, 10)),
            );
        }

        $this->assertSame(
            [],
            $violations,
            "Applications must consume SL1 authority instead of importing WebAuthn/passkey verification semantics.\n"
            .implode("\n", $violations),
        );
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function appPhpFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file;
        }

        return $files;
    }

    /**
     * @return array<int, string>
     */
    private function credentialBoundaryLines(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        $matches = [];
        $pattern = '/Webauthn\\\\|Spatie\\\\LaravelPasskeys\\\\Actions|Spatie\\\\LaravelPasskeys\\\\Models\\\\Passkey|FindPasskey|GeneratePasskey|StorePasskey|PublicKeyCredential|AuthenticatorSelectionCriteria|passkey_options|passkey-authentication-options/';

        foreach ($lines as $lineNumber => $line) {
            if (preg_match($pattern, $line) !== 1) {
                continue;
            }

            $matches[] = (string) ($lineNumber + 1);
        }

        return $matches;
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
