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
            'fcm_token' => ['required', 'string', 'max:512'],
            'previous_fcm_token' => ['nullable', 'string', 'max:512'],
            'platform' => ['nullable', 'in:android,ios'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'sound_enabled' => ['sometimes', 'boolean'],
            'vibration_enabled' => ['sometimes', 'boolean'],
        ]);

        $userId = $request->attributes->get('firebase_uid');
        $role = $request->attributes->get('firebase_role');

        if (!empty($validated['previous_fcm_token']) && $validated['previous_fcm_token'] !== $validated['fcm_token']) {
            DeviceToken::where('fcm_token', $validated['previous_fcm_token'])
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            \Illuminate\Support\Facades\Log::info('REPLACE TOKEN SUCCESS', [
                'fcm_token' => $validated['fcm_token'],
                'previous_fcm_token' => $validated['previous_fcm_token'],
                'user_id' => $userId,
            ]);
        }

        $device = DeviceToken::updateOrCreate(
            ['fcm_token' => $validated['fcm_token']],
            [
                'user_id' => $userId,
                'role' => $role,
                'platform' => $validated['platform'] ?? null,
                'device_name' => $validated['device_name'] ?? null,
                'sound_enabled' => $validated['sound_enabled'] ?? true,
                'vibration_enabled' => $validated['vibration_enabled'] ?? true,
                'is_active' => true,
                'last_seen_at' => now(),
            ],
        );

        \Illuminate\Support\Facades\Log::info('REGISTER TOKEN SUCCESS', [
            'fcm_token' => $validated['fcm_token'],
            'user_id' => $device->user_id,
            'role' => $device->role,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['id' => $device->id, 'is_active' => $device->is_active],
        ]);
    }

    public function destroyCurrent(Request $request): JsonResponse
    {
        $validated = $request->validate(['fcm_token' => ['required', 'string', 'max:512']]);

        $deleted = DeviceToken::where('fcm_token', $validated['fcm_token'])
            ->where('user_id', $request->attributes->get('firebase_uid'))
            ->update(['is_active' => false]);

        if ($deleted) {
            \Illuminate\Support\Facades\Log::info('DELETE TOKEN SUCCESS', ['fcm_token' => $validated['fcm_token']]);
        } else {
            \Illuminate\Support\Facades\Log::warning('DELETE TOKEN FAILED', ['fcm_token' => $validated['fcm_token']]);
        }

        return response()->json(['success' => true]);
    }
}
