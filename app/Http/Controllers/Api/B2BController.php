<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Meanly\SimpleL1\B2B\BusinessRegistrationManager;

class B2BController extends Controller
{
    public function search(Request $request, BusinessRegistrationManager $manager)
    {
        $inn = $request->input('inn');
        
        // 1. Get official data from DaData
        $result = $manager->searchAndAnchor($inn, 'anonymous');
        
        // 2. Check if already exists in our DB (using blind index)
        if ($result['verified']) {
            $bidx = app(\App\Services\VaultTransitService::class)->computeBlindIndex($inn);
            $exists = \App\Models\LegalEntity::where('inn_bidx', $bidx)->exists();
            $result['already_registered'] = $exists;
        }

        return response()->json($result);
    }
}
