<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Session;
use App\Notifications\SchoolNotification;
use App\Services\ScheduleGenerator;
use App\Services\CourseHallAssigner; // Your new service
use App\Services\HallAssigner;


class ScheduleController extends Controller
{
    protected $scheduleGenerator;
    protected $courseHallAssigner;

    public function __construct(ScheduleGenerator $generator,CourseHallAssigner $courseHallAssigner){
        $this->scheduleGenerator = $generator;
        $this->courseHallAssigner = $courseHallAssigner;
    }

    public function destroySession(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: Admin only.'], 403);
        }

        $session = Session::with('course.teacher', 'course.students')->find($id);

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        
        $courseName = $session->course->name;
        $day = $session->day;
        $time = $session->start_time;

        
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

    // 3. Define the Grid Constraints (Sunday-Thursday, 8am-5pm)
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
    // We match the time slots used in the generator logic.  A 17:00 slot
    // was previously omitted which caused sessions scheduled at 17:00 to
    // disappear from the admin view.  Adding it here ensures all
    // sessions are represented in the grid.
    $timeSlots = ['08:00:00', '09:30:00', '11:00:00', '12:30:00', '14:00:00', '15:30:00', '17:00:00'];

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

    public function store(Request $request, HallAssigner $examHallAssigner)
    {
        // 1. Authorization check
        $user = $request->user();
        if (!$user||$user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: Only Administrators can perform this action.'], 403);
        }

        // 2. Validation
        $request->validate([
            'type' => 'required|in:exam,course'
        ]);

        try {
            // 3. Generate the Time Slots (The "Engine")
            $result = $this->scheduleGenerator->generate($request->type);
            $schedule = $result['schedule'];
            $schedule->update(['admin_id' => $user->id]);
            $schedule->save();
            $adminAlerts = [];

            // 4. Hall Assignment Logic
            if ($request->type === 'course') {
                
                $adminAlerts = $this->courseHallAssigner->assignHallsToCourseSchedule($schedule->id);
            } else {
                // If it's an exam, trigger the exam hall logic
                $examResult = $examHallAssigner->assignHallsToSchedule($schedule->id);
                $adminAlerts = $examResult['report'] ?? [];
            }

            
            $schedule->load(['sessions.hall', 'sessions.course', 'sessions.hallAssignments.hall']);

            
            $typeName = ($request->type === 'exam') ? 'Exam' : 'Course';
            $usersToNotify = User::whereIn('role', ['student', 'teacher'])->get();
            foreach ($usersToNotify as $user) {
                $user->notify(new SchoolNotification(
                    "New $typeName Schedule Live!",
                    "The administration has published the latest $typeName schedule.",
                    "schedule_published",
                    "Academic Schedule"
                ));
            }

            return response()->json([
                'success' => true,
                'message' => 'Schedule generated and halls assigned successfully.',
                'admin_alerts' => $adminAlerts,
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