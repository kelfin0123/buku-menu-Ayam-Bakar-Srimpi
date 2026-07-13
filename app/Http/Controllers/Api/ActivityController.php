<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        // For now show latest activities; can be filtered/permissioned
        $activities = Activity::query()
            ->with('order')
            ->orderByDesc('created_at')
            ->paginate(20);

        return ActivityResource::collection($activities);
    }

    public function markRead(Activity $activity)
    {
        $activity->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }
}
