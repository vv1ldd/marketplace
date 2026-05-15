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
        $sl1Address = $request->input('sl1_address', 'anonymous');
        
        return response()->json($manager->searchAndAnchor($inn, $sl1Address));
    }
}
