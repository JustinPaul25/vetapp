<?php

namespace App\Http\Controllers;

use App\Services\AblyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AblyController extends Controller
{
    /**
     * Get Ably token for authenticated user
     */
    public function getToken(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $apiKey = config('services.ably.key');
        
        if (!$apiKey) {
            return response()->json(['error' => 'Ably not configured'], 500);
        }

        // For now, return the API key (in production, generate a proper token)
        // In production, you should use Ably's token authentication
        return response()->json([
            'token' => $apiKey,
            'user_id' => $user->id,
        ]);
    }
}

