<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegalEntity;
use App\Services\LegalEntityMigrationPillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LegalEntityMagicLoginController extends Controller
{
    public function __construct(private readonly LegalEntityMigrationPillService $pills)
    {
    }

    public function __invoke(Request $request, LegalEntity $legalEntity): RedirectResponse
    {
        $this->ensureMagicLoginAllowed();

        if (! $request->hasValidSignature()) {
            abort(403, 'Magic login link has expired or is invalid.');
        }

        return $this->login($request, $legalEntity);
    }

    public function byInn(Request $request, ?string $inn = null): RedirectResponse
    {
        $this->ensureMagicLoginAllowed();

        $inn = $inn ?: $request->query('inn') ?: $request->query('magic-login');
        abort_if(empty($inn), 404);

        $legalEntity = LegalEntity::findByInn((string) $inn);
        abort_unless($legalEntity, 404);

        return $this->login($request, $legalEntity);
    }

    private function login(Request $request, LegalEntity $legalEntity): RedirectResponse
    {
        [$pill, $token] = $this->pills->issueForOwner(
            legalEntity: $legalEntity,
            targetDomain: $request->query('target_domain') ?: config('app.production_domain', config('app.domain')),
            issuedBy: $request->user(),
            issuedIp: $request->ip(),
            expiresAt: now()->addMinutes(30),
        );

        return redirect()->away($this->pills->migrationUrl($token, $pill->target_domain));
    }

    private function ensureMagicLoginAllowed(): void
    {
        abort_if(app()->isProduction() || config('app.env') === 'production', 404);
    }

}
