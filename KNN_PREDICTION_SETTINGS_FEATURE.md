# KNN Prediction Settings Feature

## Overview

This feature adds an admin setting to enable or disable KNN (K-Nearest Neighbors) machine learning predictions for disease diagnosis and medicine recommendations.

## What Was Implemented

### 1. Database & Models

- **Migration**: `2025_12_19_021918_create_settings_table.php`
  - Creates a `settings` table with key-value storage
  - Supports multiple data types (string, boolean, integer, json)
  - Includes description field for documentation

- **Model**: `app/Models/Setting.php`
  - Provides static methods `get()` and `set()` for easy access
  - Automatic type casting based on the type field
  - Safe defaults if setting doesn't exist

- **Seeder**: `database/seeders/SettingsSeeder.php`
  - Creates default setting `enable_knn_prediction` (enabled by default)
  - Can be run anytime to ensure settings exist

### 2. Backend (Laravel)

- **Controller**: `app/Http/Controllers/Admin/SettingsController.php`
  - `index()`: Display settings page (Admin only)
  - `update()`: Update individual setting values
  - `getSettings()`: API endpoint to retrieve all settings

- **Routes**: Added to `routes/web.php`
  - `GET /admin/settings` - Settings page
  - `PATCH /admin/settings` - Update setting
  - `GET /admin/settings/api` - Get settings via API

### 3. Frontend (Vue.js)

- **Settings Page**: `resources/js/pages/Admin/Settings/Index.vue`
  - Clean, modern UI using shadcn components
  - Toggle switch for KNN prediction
  - Warning message when KNN is disabled
  - Only accessible to admins

- **Switch Component**: `resources/js/components/ui/switch/Switch.vue`
  - New reusable UI component
  - Follows existing component patterns
  - Accessible and keyboard-friendly

- **Navigation**: Updated `resources/js/components/AppSidebar.vue`
  - Added "Settings" menu item for admins
  - Placed under System Administration section

### 4. ML Integration

- **Updated**: `resources/js/composables/useDiseaseML.ts`
  - Added `checkKnnEnabled()` function to check settings
  - Modified `getMedicineRecommendations()` to check setting before running
  - Modified `predictDiseasesFromSymptoms()` to check setting before running
  - Returns empty array when KNN is disabled
  - Console logging for debugging

## How It Works

### Flow Diagram

```
User selects disease/symptoms
         ↓
useDiseaseML composable called
         ↓
checkKnnEnabled() fetches settings from API
         ↓
    KNN Enabled?
         ↓
    Yes ← → No
     ↓        ↓
Run ML  Return empty
Model   array []
     ↓
Return predictions
```

### Setting States

- **Enabled (default)**: ML predictions work normally
- **Disabled**: 
  - `getMedicineRecommendations()` returns `[]`
  - `predictDiseasesFromSymptoms()` returns `[]`
  - Console logs indicate KNN is disabled
  - Manual selection required in UI

## Usage

### For Administrators

1. Navigate to **Settings** in the admin sidebar
2. Find **Machine Learning Settings** section
3. Toggle **Enable KNN Prediction** on/off
4. Changes take effect immediately for all users

### For Developers

**Check if KNN is enabled:**
```typescript
import { useDiseaseML } from '@/composables/useDiseaseML';

const { isKnnEnabled, checkKnnEnabled } = useDiseaseML();

// Check current status
await checkKnnEnabled();
console.log(isKnnEnabled.value); // true or false
```

**Get medicine recommendations:**
```typescript
const { getMedicineRecommendations } = useDiseaseML();

// Will automatically check if KNN is enabled
const recommendations = await getMedicineRecommendations(diseaseId, 3);

if (recommendations.length === 0) {
  // Either no recommendations found OR KNN is disabled
  // Show manual selection interface
}
```

**Predict diseases from symptoms:**
```typescript
const { predictDiseasesFromSymptoms } = useDiseaseML();

// Will automatically check if KNN is enabled
const predictions = await predictDiseasesFromSymptoms(symptomIds, 10);

if (predictions.length === 0) {
  // Either no matches found OR KNN is disabled
  // Show manual disease selection
}
```

## Database Seeding

To ensure the setting exists:

```bash
php artisan db:seed --class=SettingsSeeder
```

Or include in `DatabaseSeeder.php`:
```php
$this->call([
    SettingsSeeder::class,
]);
```

## API Endpoints

### Get All Settings
```
GET /admin/settings/api
```

Response:
```json
{
  "success": true,
  "settings": {
    "enable_knn_prediction": true
  }
}
```

### Update Setting
```
PATCH /admin/settings
Content-Type: application/json

{
  "key": "enable_knn_prediction",
  "value": false
}
```

## Adding New Settings

To add more settings in the future:

1. **Add to seeder** (`database/seeders/SettingsSeeder.php`):
```php
[
    'key' => 'new_setting_key',
    'value' => 'default_value',
    'type' => 'string', // or 'boolean', 'integer', 'json'
    'description' => 'Description of what this setting does',
]
```

2. **Add to Settings page** (`resources/js/pages/Admin/Settings/Index.vue`):
```vue
<div class="flex items-center justify-between space-x-4">
    <div class="flex-1 space-y-1">
        <Label for="new_setting" class="text-base font-medium">
            Setting Name
        </Label>
        <p class="text-sm text-muted-foreground">
            {{ settings.new_setting?.description }}
        </p>
    </div>
    <Switch
        id="new_setting"
        :checked="form.new_setting"
        @update:checked="(val) => updateSetting('new_setting', val)"
    />
</div>
```

## Testing

### Manual Testing Steps

1. **Verify Settings Page**
   - Login as admin
   - Navigate to Settings
   - Confirm toggle is visible and works

2. **Test KNN Disabled**
   - Turn off KNN prediction
   - Try to prescribe medicine
   - Verify no ML recommendations appear
   - Check browser console for "KNN prediction is disabled" message

3. **Test KNN Enabled**
   - Turn on KNN prediction
   - Try to prescribe medicine
   - Verify ML recommendations appear
   - Verify disease prediction works

4. **Test Permissions**
   - Login as staff (not admin)
   - Verify Settings menu item doesn't appear
   - Try to access `/admin/settings` directly
   - Should be redirected or get 403 error

## Security

- Settings page is **admin-only** (protected by `EnsureUserIsAdmin` middleware)
- Settings API endpoint is also **admin-only**
- No client-side enforcement - security handled on backend
- Settings are cached per request, not globally

## Performance

- Setting is fetched once per prediction request
- No database query caching (consider adding in production)
- Minimal overhead (single API call)
- Failed setting fetch defaults to enabled (fail-safe)

## Future Improvements

1. Add setting caching (Redis/Memcached)
2. Add more granular ML settings:
   - Enable/disable medicine recommendations only
   - Enable/disable disease predictions only
   - Configure confidence thresholds
   - Configure top K results
3. Add setting change audit log
4. Add setting validation rules
5. Add bulk setting updates
6. Add setting import/export

## Troubleshooting

**Issue**: Settings toggle doesn't appear
- Check if logged in as admin
- Check browser console for errors
- Verify migration ran successfully

**Issue**: KNN still works when disabled
- Check browser network tab for `/admin/settings/api` response
- Verify `enable_knn_prediction` is actually `false` in database
- Clear browser cache and reload

**Issue**: Cannot access settings page
- Verify user has admin role
- Check `EnsureUserIsAdmin` middleware is working
- Check route is registered in `routes/web.php`

## Files Modified/Created

### Created
- `database/migrations/2025_12_19_021918_create_settings_table.php`
- `app/Models/Setting.php`
- `database/seeders/SettingsSeeder.php`
- `app/Http/Controllers/Admin/SettingsController.php`
- `resources/js/pages/Admin/Settings/Index.vue`
- `resources/js/components/ui/switch/Switch.vue`
- `resources/js/components/ui/switch/index.ts`
- `KNN_PREDICTION_SETTINGS_FEATURE.md` (this file)

### Modified
- `routes/web.php` - Added settings routes
- `resources/js/composables/useDiseaseML.ts` - Added KNN check logic
- `resources/js/components/AppSidebar.vue` - Added Settings menu item

## Deployment Checklist

- [ ] Run migrations: `php artisan migrate`
- [ ] Run seeder: `php artisan db:seed --class=SettingsSeeder`
- [ ] Clear cache: `php artisan config:clear`
- [ ] Build frontend: `npm run build`
- [ ] Test as admin user
- [ ] Test as staff user (verify no access)
- [ ] Test ML predictions with setting on/off
