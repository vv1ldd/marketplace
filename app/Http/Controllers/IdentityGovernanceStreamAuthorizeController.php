<?php

namespace App\Http\Controllers;

use App\Services\Identity\Governance\IdentityGovernanceStreamAuthorizeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IdentityGovernanceStreamAuthorizeController extends Controller
{
    public function options(Request $request, IdentityGovernanceStreamAuthorizeService $authorize): JsonResponse
    {
        $data = $request->validate([
            'entityAddress' => 'nullable|string|max:128',
        ]);

        $entityAddress = strtolower(trim((string) ($data['entityAddress'] ?? '')));

        if ($entityAddress === '') {
            abort(422, 'entityAddress is required.');
        }

        $payload = $authorize->issueAuthenticationOptions($entityAddress, $entityAddress);

        return response()->json($payload);
    }

    public function verify(Request $request, IdentityGovernanceStreamAuthorizeService $authorize): JsonResponse
    {
        $data = $request->validate([
            'flowId' => 'required|string|max:80',
            'authenticationResponse' => 'required|array',
        ]);

        $result = $authorize->verifyAuthentication(
            flowId: (string) $data['flowId'],
            authenticationResponse: (array) $data['authenticationResponse'],
        );

        return response()->json([
            'active' => true,
            'entityAddress' => $result['entityAddress'],
            'factorId' => $result['factorId'],
        ]);
    }
}
