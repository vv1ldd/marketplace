<?php

namespace App\Http\Controllers;

use App\Services\Identity\Governance\IdentityGovernanceSl1eProofIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IdentityGovernanceSl1eProofController extends Controller
{
    public function introspect(Request $request, IdentityGovernanceSl1eProofIssuer $issuer): JsonResponse
    {
        $data = $request->validate([
            'proof_token' => 'required|string|min:8|max:4096',
        ]);

        return response()->json($issuer->introspect((string) $data['proof_token']));
    }
}
