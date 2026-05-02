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

    public function destroySession($id)
    {
        $requester = User::find($request->query('requester_id'));

        if (!$requester || $requester->role !== 'admin') {
           return response()->json([
               'message' => 'Forbidden: Only Administrators can perform this action.'
    ], 403);
}
        $session = \App\Models\Session::find($id);

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        $session->delete();

        return response()->json(['success' => true, 'message' => 'Session deleted successfully']);
    }

    public function index(Request $request)
{
    // 1. Determine type (course or exam)
    $type = $request->query('type', 'course');

    // 2. Fetch the latest schedule of that type
    $schedule = \App\Models\Schedule::where('type', $type)->latest()->first();

    if (!$schedule) {
        return response()->json([
            'success' => false,
            'message' => "No $type schedule found."
        ], 404);
    }

    // 3. Define the Grid Constraints (Sunday-Thursday, 8am-3pm)
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
    // We match the time slots used in your generator logic
    $timeSlots = ['08:00:00', '09:30:00', '11:00:00', '12:30:00', '14:00:00', '15:30:00'];

    // 4. Eager load everything needed, including the hall assignments for exams
    $schedule->load(['sessions.course', 'sessions.hall', 'sessions.hallAssignments.hall']);

    $masterGrid = [];

    foreach ($days as $day) {
        foreach ($timeSlots as $time) {
            // Find the session for this specific slot
            $session = $schedule->sessions
                ->where('day', $day)
                ->filter(fn($s) => str_starts_with($s->start_time, $time))
                ->first();

            if ($session) {
                // Determine Hall display: List all halls for exams, or one for courses
                $halls = [];
                if ($type === 'exam') {
                    // Pull all hall names from the hallAssignments bridge
                    $halls = $session->hallAssignments->pluck('hall.name')->unique()->values()->toArray();
                } else {
                    $halls = $session->hall ? [$session->hall->name] : ['Unassigned'];
                }

                $masterGrid[$day][$time] = [
                    'session_id'  => $session->id,
                    'course_name' => $session->course->name ?? 'Unknown',
                    'halls'       => $halls,
                    'start_time'  => $session->start_time,
                    'end_time'    => $session->end_time,
                    'status'      => 'Occupied'
                ];
            } else {
                $masterGrid[$day][$time] = [
                    'status' => 'Empty'
                ];
            }
        }
    }

    return response()->json([
        'success'       => true,
        'schedule_type' => $type,
        'schedule_id'   => $schedule->id,
        'generated_at'  => $schedule->created_at->format('Y-m-d H:i'),
        'master_grid'   => $masterGrid
    ]);
}

    public function store(Request $request, HallAssigner $hallAssigner)
    {
        $requester = User::find($request->query('requester_id'));

        if (!$requester || $requester->role !== 'admin') {
            return response()->json([
               'message' => 'Forbidden: Only Administrators can perform this action.'
    ], 403);
}
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

                // Load both hall and course relationships on sessions so the API returns course
                // names and teacher IDs. Without loading the course relationship, the frontend
                // will only see the course_id but not the course name.
                $schedule->load(['sessions.hall', 'sessions.course']);
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