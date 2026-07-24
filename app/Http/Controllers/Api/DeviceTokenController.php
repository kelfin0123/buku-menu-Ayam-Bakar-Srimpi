<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'in:android,ios'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'sound_enabled' => ['sometimes', 'boolean'],
            'vibration_enabled' => ['sometimes', 'boolean'],
        ]);
        $device = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->attributes->get('firebase_uid'),
                'role' => $request->attributes->get('firebase_role'),
                'platform' => $validated['platform'] ?? null,
                'device_name' => $validated['device_name'] ?? null,
                'sound_enabled' => $validated['sound_enabled'] ?? true,
                'vibration_enabled' => $validated['vibration_enabled'] ?? true,
                'is_active' => true,
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'success' => true,
            'data' => ['id' => $device->id, 'is_active' => $device->is_active],
        ]);
    }

    public function destroyCurrent(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string', 'max:512']]);
        DeviceToken::where('token', $validated['token'])
            ->where('user_id', $request->attributes->get('firebase_uid'))
            ->update(['is_active' => false]);

        return response()->json(['success' => true]);
    }
}
