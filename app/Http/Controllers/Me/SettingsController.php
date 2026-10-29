<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['settings' => (object) ($request->user()->settings?->settings ?? [])]);
    }

    /**
     * Merge the given reader preferences into the stored ones.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'replace' => ['sometimes', 'boolean'],
        ]);

        $setting = UserSetting::firstOrNew(['user_id' => $request->user()->id]);
        $setting->settings = $request->boolean('replace')
            ? $data['settings']
            : array_replace_recursive($setting->settings ?? [], $data['settings']);
        $setting->save();

        return response()->json(['settings' => (object) $setting->settings]);
    }
}
