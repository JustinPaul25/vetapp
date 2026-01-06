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
     * Update the specified setting(s).
     */
    public function update(Request $request)
    {
        // Support both single setting update and bulk update
        if ($request->has('settings') && is_array($request->settings)) {
            // Bulk update mode
            $validated = $request->validate([
                'settings' => 'required|array',
                'settings.*.key' => 'required|string',
                'settings.*.value' => 'present',
            ]);

            foreach ($validated['settings'] as $settingData) {
                $setting = Setting::where('key', $settingData['key'])->first();
                
                if (!$setting) {
                    // Create the setting if it doesn't exist
                    $setting = Setting::create([
                        'key' => $settingData['key'],
                        'value' => '',
                        'type' => 'string',
                        'description' => '',
                    ]);
                }
                
                // Handle boolean values properly
                $value = $settingData['value'];
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                } elseif ($value === 'true' || $value === 'false') {
                    $value = $value === 'true' ? '1' : '0';
                }
                
                $setting->update([
                    'value' => $value,
                ]);
            }

            return redirect()->back()->with('success', 'Settings updated successfully');
        } else {
            // Single setting update mode (backward compatibility)
            $validated = $request->validate([
                'key' => 'required|string',
                'value' => 'present', // Changed from 'required' to allow false values
            ]);

            $setting = Setting::where('key', $validated['key'])->first();
            
            // Determine if this is a boolean setting
            $isBoolean = is_bool($validated['value']) 
                || $validated['value'] === 'true' 
                || $validated['value'] === 'false'
                || $validated['value'] === true
                || $validated['value'] === false
                || in_array($validated['key'], [
                    'enable_knn_prediction',
                    'enable_logistic_regression_prediction',
                    'enable_neural_network_prediction',
                ]);
            
            if (!$setting) {
                // Create the setting if it doesn't exist with appropriate type
                $setting = Setting::create([
                    'key' => $validated['key'],
                    'value' => '',
                    'type' => $isBoolean ? 'boolean' : 'string',
                    'description' => '',
                ]);
            }
            
            // Handle boolean values properly - convert all possible boolean representations to '1' or '0'
            $value = $validated['value'];
            $truthyValues = [true, 'true', '1', 1, 'on', 'yes'];
            $falsyValues = [false, 'false', '0', 0, 'off', 'no', '', null];
            
            if (in_array($value, $truthyValues, true) || (is_bool($value) && $value === true)) {
                $value = '1';
            } elseif (in_array($value, $falsyValues, true) || (is_bool($value) && $value === false)) {
                $value = '0';
            }
            
            // Prepare update data
            $updateData = ['value' => $value];
            
            // Ensure type is boolean if it's a boolean setting
            if ($isBoolean && $setting->type !== 'boolean') {
                $updateData['type'] = 'boolean';
            }
            
            $setting->update($updateData);
            
            // Reload the setting to ensure we have the latest data
            $setting->refresh();

            // Get updated settings to return in response
            $settings = Setting::all()->mapWithKeys(function ($s) {
                return [$s->key => [
                    'value' => $this->castValue($s->value, $s->type),
                    'type' => $s->type,
                    'description' => $s->description,
                ]];
            });

            // Use Inertia::back() to properly refresh props, or redirect with Inertia
            return back()->with('success', 'Setting updated successfully');
        }
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
