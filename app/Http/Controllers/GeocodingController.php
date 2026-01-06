<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingController extends Controller
{
    /**
     * Search for addresses using Nominatim API (proxy to avoid CORS)
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|max:255',
            'limit' => 'sometimes|integer|min:1|max:10',
        ]);

        $query = $request->input('q');
        $limit = $request->input('limit', 5);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'VetApp Address Picker (contact: ' . config('app.url') . ')',
                    'Accept' => 'application/json',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'q' => $query,
                    'limit' => $limit,
                    'countrycodes' => 'ph',
                    'addressdetails' => 1,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Ensure we return an array
                if (!is_array($data)) {
                    $data = [];
                }

                return response()->json($data);
            }

            Log::warning('Nominatim API error', [
                'status' => $response->status(),
                'query' => $query,
            ]);

            return response()->json([], 200); // Return empty array instead of error
        } catch (\Exception $e) {
            Log::error('Geocoding error', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            return response()->json([], 200); // Return empty array instead of error
        }
    }
}

