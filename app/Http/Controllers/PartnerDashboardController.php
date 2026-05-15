<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PartnerDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $legalEntity = $user->legalEntities()->first();

        return view('partner.dashboard', [
            'user' => $user,
            'legalEntity' => $legalEntity
        ]);
    }
}
