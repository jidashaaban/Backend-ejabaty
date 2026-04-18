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
        $session = \App\Models\Session::find($id);

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        $session->delete();

        return response()->json(['success' => true, 'message' => 'Session deleted successfully']);
    }

    public function index(Request $request)
    {
        $type = $request->query('type', 'course');

        $schedule = Schedule::where('type', $type)->latest()->first();

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'sessions' => [],
                'message' => "No $type schedule found."
            ]);
        }

        $schedule->load(['sessions.hall', 'sessions.course', 'sessions.hallAssignments.hall']);

        // Map each session into a simple associative array. Use values() at the end to
        // reset the numeric keys so that the returned JSON is encoded as a proper
        // list/array instead of an object with string keys. Without calling
        // values(), Laravel will preserve the original collection keys (e.g., 0, 1,
        // etc.) which end up as string properties in the JSON response. On the
        // client side, axios interprets objects differently and calling
        // sessions.map(...) would fail because map is not defined on plain
        // objects. Using values() ensures that the JSON `sessions` field is an
        // array and can be iterated over safely in the frontend.
        $sessions = $schedule->sessions
            ->map(function ($session) {
                return [
                    'id'         => $session->id,
                    'day'        => $session->day,
                    'start_time' => $session->start_time,
                    'end_time'   => $session->end_time,
                    'course'     => $session->course ? [
                        'id'         => $session->course->id,
                        'name'       => $session->course->name,
                        'teacher_id' => $session->course->teacher_id,
                    ] : null,
                    'course_id'  => $session->course_id,
                    'hall'       => $session->hall ? [
                        'id'   => $session->hall->id,
                        'name' => $session->hall->name,
                    ] : null,
                    'hall_id'    => $session->hall_id,
                ];
            })
            ->values();

        return response()->json([
            'success'  => true,
            'type'     => $type,
            'sessions' => $sessions,
        ]);
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