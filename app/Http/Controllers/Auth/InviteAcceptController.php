<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InviteAcceptController extends Controller
{
    /**
     * Show the invite acceptance page.
     */
    public function show(string $token)
    {
        return response()->json([
            'error' => 'Email/password invites were retired. Invite a verified SL1E wallet identity instead.',
        ], 410);
    }

    /**
     * Step 1: Get passkey registration options for the invited user.
     */
    public function options(Request $request, string $token)
    {
        return response()->json([
            'error' => 'Email/password invites were retired. Invite a verified SL1E wallet identity instead.',
        ], 410);
    }

    /**
     * Step 2: Store passkey, anchor L1, attach user to workspace.
     */
    public function accept(Request $request, string $token)
    {
        return response()->json([
            'error' => 'Email/password invites were retired. Invite a verified SL1E wallet identity instead.',
        ], 410);
    }
}
