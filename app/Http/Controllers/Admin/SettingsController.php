<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Display the settings page.
     */
    public function index()
    {
        $settings = Setting::all()->mapWithKeys(function ($setting) {
            return [$setting->key => [
                'value' => $this->castValue($setting->value, $setting->type),
                'type' => $setting->type,
                'description' => $setting->description,
            ]];
        });

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update the specified setting.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string',
            'value' => 'present', // Changed from 'required' to allow false values
        ]);

        $setting = Setting::where('key', $validated['key'])->firstOrFail();
        
        // Handle boolean values properly
        $value = $validated['value'];
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif ($value === 'true' || $value === 'false') {
            $value = $value === 'true' ? '1' : '0';
        }
        
        $setting->update([
            'value' => $value,
        ]);

        return redirect()->back()->with('success', 'Setting updated successfully');
    }

    /**
     * Get settings for API access.
     */
    public function getSettings()
    {
        $settings = Setting::all()->mapWithKeys(function ($setting) {
            return [$setting->key => $this->castValue($setting->value, $setting->type)];
        });

        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    /**
     * Cast the value based on type.
     */
    private function castValue($value, string $type)
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
