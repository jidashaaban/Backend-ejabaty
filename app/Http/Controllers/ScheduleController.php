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
            }
            $schedule->load(['sessions.course', 'sessions.hall', 'sessions.hallAssignments.hall']);

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
    public function index(Request $request)
{
    // 1. Determine if we want the 'course' or 'exam' schedule
    $type = $request->query('type', 'course');

    // 2. Fetch the latest schedule of that type
    $schedule = \App\Models\Schedule::where('type', $type)->latest()->first();

    if (!$schedule) {
        return response()->json([
            'success' => false,
            'message' => 'No schedule found in the database.'
        ], 404);
    }

    // 3. THE CRITICAL PART: Load all relationships so they "print" on frontend
    // We need courses, halls, and exam hall assignments
    $schedule->load([
        'sessions.course', 
        'sessions.hall', 
        'sessions.hallAssignments.hall'
    ]);

    return response()->json([
        'success' => true,
        'data' => $schedule
    ]);
}
}