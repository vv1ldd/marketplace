<?php

namespace App\Http\Controllers;

use App\Services\SimpleL1ProtocolClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SimpleL1ConnectController extends Controller
{
    public function connect(Request $request): RedirectResponse
    {
        $returnTo = $this->safeReturnTo((string) $request->query('return_to', '/store'));
        $state = Str::random(40);

        session([
            'simple_l1_connect.state' => $state,
            'simple_l1_connect.return_to' => $returnTo,
        ]);

        $authorizeUrl = config('simple_l1.identity_provider_url').'/identity/simple-l1/authorize?'.http_build_query([
            'redirect_uri' => route('meanly.simple_l1.callback'),
            'state' => $state,
            'action' => 'meanly.marketplace.connect',
        ]);

        return redirect()->away($authorizeUrl);
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = (string) session('simple_l1_connect.state');
        $returnTo = $this->safeReturnTo((string) session('simple_l1_connect.return_to', '/store'));

        abort_unless($expectedState !== '' && hash_equals($expectedState, (string) $request->query('state')), 403);

        $proofToken = trim((string) $request->query('proof_token', ''));
        abort_if($proofToken === '', 422, 'Simple L1 proof token is missing.');

        $identity = app(SimpleL1ProtocolClient::class)->introspectProof($proofToken);
        abort_unless((bool) data_get($identity, 'active'), 422, 'Simple L1 proof is not active.');

        $l1Address = (string) data_get($identity, 'identity.entity_l1_address', data_get($identity, 'identity.l1_address', ''));
        abort_unless(preg_match('/^sl1e_[a-f0-9]{39}$/i', $l1Address) === 1, 422);
        $keyAddress = data_get($identity, 'identity.key_l1_address');

        session([
            'simple_l1_identity' => [
                'l1_address' => strtolower($l1Address),
                'entity_l1_address' => strtolower($l1Address),
                'key_l1_address' => is_string($keyAddress) ? strtolower($keyAddress) : null,
                'proof_token' => $proofToken,
                'proof' => $identity['proof'] ?? null,
                'protocol' => 'simple-l1',
                'connected_at' => now()->toIso8601String(),
            ],
            'sovereign_l1_address' => strtolower($l1Address),
        ]);
        session()->forget(['simple_l1_connect.state', 'simple_l1_connect.return_to']);

        return redirect($returnTo)->with('status', 'Simple L1 wallet connected.');
    }

    public function status(): array
    {
        $identity = session('simple_l1_identity');

        return [
            'protocol' => 'simple-l1',
            'authenticated' => is_array($identity) && ! empty($identity['l1_address']),
            'identity' => $identity,
        ];
    }

    private function safeReturnTo(string $returnTo): string
    {
        $returnTo = trim($returnTo);

        if ($returnTo === '' || ! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return '/store';
        }

        return $returnTo;
    }
}
