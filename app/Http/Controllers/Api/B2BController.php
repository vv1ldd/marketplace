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
        \Illuminate\Support\Facades\Log::info("B2B Search Triggered", ['inn' => $inn]);
        
        $result = $manager->searchAndAnchor($inn, 'anonymous');
        \Illuminate\Support\Facades\Log::info("B2B Search Result", $result);

        return response()->json($result);
    }
}
