<?php

namespace App\Http\Controllers;

use App\Services\Identity\Governance\IdentityGovernanceStreamAuthorizeService;
use App\Services\Identity\Governance\IdentityGovernanceStreamHandoffService;
use App\Services\Identity\Governance\IdentityGovernanceStreamRegisterService;
use App\Services\Identity\Governance\Sl1eAuthorizeRequestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IdentityGovernanceStreamAuthorizeController extends Controller
{
    public function options(Request $request, IdentityGovernanceStreamAuthorizeService $authorize): JsonResponse
    {
        $context = Sl1eAuthorizeRequestContext::fromRequest($request);

        $entityAddress = strtolower(trim((string) $request->input('entityAddress', $request->input('entity_address', ''))));

        $payload = $authorize->issueAuthenticationOptions(
            context: $context,
            entityAddressHint: $entityAddress !== '' ? $entityAddress : null,
        );

        return response()->json([
            'success' => true,
            ...$payload,
        ]);
    }

    public function verify(Request $request, IdentityGovernanceStreamAuthorizeService $authorize): JsonResponse
    {
        $context = Sl1eAuthorizeRequestContext::fromRequest($request);

        $data = $request->validate([
            'flowId' => 'required|string|max:80',
            'authenticationResponse' => 'required|array',
        ]);

        $result = $authorize->verifyAuthentication(
            context: $context,
            flowId: (string) $data['flowId'],
            authenticationResponse: (array) $data['authenticationResponse'],
        );

        return response()->json($result);
    }

    public function registerOptions(
        Request $request,
        IdentityGovernanceStreamRegisterService $register,
    ): JsonResponse {
        $context = Sl1eAuthorizeRequestContext::fromRequest($request);

        return response()->json($register->issueRegistrationOptions($context));
    }

    public function registerVerify(
        Request $request,
        IdentityGovernanceStreamRegisterService $register,
    ): JsonResponse {
        $context = Sl1eAuthorizeRequestContext::fromRequest($request);

        $data = $request->validate([
            'flowId' => 'required|string|max:80',
            'attestationResponse' => 'required|array',
        ]);

        $result = $register->verifyRegistration(
            context: $context,
            flowId: (string) $data['flowId'],
            attestationResponse: (array) $data['attestationResponse'],
        );

        return response()->json($result);
    }

    public function handoff(Request $request, IdentityGovernanceStreamHandoffService $handoff): JsonResponse
    {
        $context = Sl1eAuthorizeRequestContext::fromRequest($request);

        return response()->json($handoff->create($context));
    }

    public function handoffStatus(string $handoffId, IdentityGovernanceStreamHandoffService $handoff): JsonResponse
    {
        return response()->json($handoff->poll($handoffId));
    }
}
