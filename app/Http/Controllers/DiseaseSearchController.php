<?php

namespace App\Http\Controllers;

use App\Models\Disease;
use App\Models\Symptom;
use Illuminate\Http\Request;

class DiseaseSearchController extends Controller
{
    /**
     * Public search endpoint for landing page.
     * Searches diseases by name, symptoms field, or related symptoms from disease_symptoms table.
     * Returns full disease information including symptoms and home_remedy.
     */
    public function search(Request $request)
    {
        $keyword = $request->get('keyword', '');
        
        if (empty(trim($keyword))) {
            return response()->json([]);
        }
        
        // First, find symptom IDs that match the keyword
        $matchingSymptomIds = Symptom::where('name', 'LIKE', "%{$keyword}%")
            ->pluck('id');
        
        // Search diseases by:
        // 1. Disease name
        // 2. Symptoms field in diseases table
        // 3. Related symptoms through disease_symptoms pivot table
        $diseases = Disease::where('name', 'LIKE', "%{$keyword}%")
            ->orWhere('symptoms', 'LIKE', "%{$keyword}%")
            ->when($matchingSymptomIds->isNotEmpty(), function ($query) use ($matchingSymptomIds) {
                return $query->orWhereHas('symptoms', function ($q) use ($matchingSymptomIds) {
                    $q->whereIn('symptoms.id', $matchingSymptomIds);
                });
            })
            ->distinct()
            ->limit(20)
            ->get()
            ->map(function ($disease) {
                return [
                    'id' => $disease->id,
                    'name' => $disease->name,
                    'symptoms' => $disease->symptoms ?? '',
                    'home_remedy' => $disease->home_remedy ?? '',
                ];
            });
        
        return response()->json($diseases);
    }
}

