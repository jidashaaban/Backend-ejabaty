<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ScheduleGenerator;
use App\Services\HallAssigner;
use App\Models\Schedule;

class ScheduleController extends Controller
{
    protected $scheduleGenerator;

    public function __construct(ScheduleGenerator $generator){
        $this->scheduleGenerator = $generator;
    }

    public function store(Request $request, HallAssigner $hallAssigner)
    {
        // 1. Validate the incoming request
        $request->validate([
            'type' => 'required|in:exam,course'
        ]);

        try {
            // 2. Generate the Time Slots
            $result = $this->scheduleGenerator->generate($request->type);
            $schedule = $result['schedule'];

            $adminReport = null;

            // 3. Automatically assign halls for Course schedules
            if ($request->type === 'course') {
                $hallResult = $hallAssigner->assignHallsToSchedule($schedule->id);
                $adminReport = $hallResult['report']; // Contains the "Partial Fit" warnings

                $schedule->load(['sessions.hall']);
            }

            // 4. Return everything in a single, clean JSON response
            return response()->json([
                'success' => true,
                'message' => 'Schedule generated and halls assigned successfully.',
                'admin_alerts' => $adminReport, // Array of warnings if students don't fit
                'data' => $schedule
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Generation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}