<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; 
use App\Models\Report;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /**
     * JUNIOR PROJECT PRO-TIP: THE DRY PRINCIPLE
     * This helper function builds the standardized report data once.
     * We are returning BOTH naming conventions (Live keys & Saved keys)
     * so your frontend tables will never show empty or 0 by mistake.
     */
    private function buildReportData($role)
    {
        if ($role === 'student') {
            return User::where('role', 'student')
                ->with(['courses', 'exams.course', 'quizzes.course']) // Eager load to prevent null course names
                ->get()
                ->map(function ($student) {
                    return [
                        'name' => $student->name,
                        
                        // COURSE DATA (Both Keys)
                        'enrolled_courses' => $student->courses->pluck('name'),
                        'courses' => $student->courses->pluck('name'),
                        
                        // EXAM DATA (Both Keys)
                        'exam_marks' => $student->exams->map(function ($exam) {
                            return ['course' => $exam->course->name ?? 'N/A', 'mark' => $exam->pivot->mark];
                        }),
                        'marks' => $student->exams->map(function ($exam) {
                            return ['course' => $exam->course->name ?? 'N/A', 'mark' => $exam->pivot->mark];
                        }),
                        
                        // QUIZ DATA (Both Keys)
                        'quiz_points' => $student->quizzes->map(function ($quiz) {
                            return ['course' => $quiz->course->name ?? 'N/A', 'points' => $quiz->pivot->points];
                        }),
                        'quizzes' => $student->quizzes->map(function ($quiz) {
                            return ['course' => $quiz->course->name ?? 'N/A', 'points' => $quiz->pivot->points];
                        })
                    ];
                });
                
        } elseif ($role === 'teacher') {
            return User::where('role', 'teacher')
                ->with(['teacherCourses', 'announcedQuizzes']) // FIXED: Using correct 1:N relationship
                ->get()
                ->map(function ($teacher) {
                    return [
                        'name' => $teacher->name,
                        
                        // COURSE DATA (Both Keys)
                        'teaching_courses' => $teacher->teacherCourses->pluck('name'),
                        'teaching' => $teacher->teacherCourses->pluck('name'),
                        
                        // QUIZ DATA (Both Keys)
                        'quizzes_announced' => $teacher->announcedQuizzes->count(),
                        'total_quizzes_announced' => $teacher->announcedQuizzes->count(),
                    ];
                });
                
        } elseif ($role === 'parent') {
            return User::where('role', 'parent')
                ->with(['children', 'complaints'])
                ->get()
                ->map(function ($parent) {
                    return [
                        'name' => $parent->name,
                        'children' => $parent->children->pluck('name'),
                        
                        // COMPLAINTS HISTORY (Live Key)
                        'complaints_history' => $parent->complaints->map(function ($c) {
                            return ['subject' => $c->subject, 'status' => $c->is_resolved ? 'Resolved' : 'Pending'];
                        }),
                        
                        // COMPLAINTS COUNT (Saved Keys)
                        'complaints_count' => $parent->complaints->count(),
                        'total_complaints' => $parent->complaints->count(),
                    ];
                });
                
        } elseif ($role === 'admin') {
            return User::where('role', 'admin')
                ->withCount(['polls', 'schedules'])
                ->get()
                ->map(function ($admin) {
                    return [
                        'name' => $admin->name,
                        'total_polls_created' => $admin->polls_count,
                        'total_schedules_generated' => $admin->schedules_count,
                    ];
                });
        }

        return collect(); // Return empty collection if role is somehow invalid
    }

    public function getUserReports(Request $request)
    {
        $admin = $request->user();
        if (!$admin || $admin->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: Admin only.'], 403);
        }

        $role = $request->query('role');
        $validRoles = ['student', 'teacher', 'parent', 'admin'];

        if (!in_array($role, $validRoles)) {
            return response()->json(['error' => 'Invalid role'], 400);
        }

        $data = $this->buildReportData($role);

        return response()->json([
            'category' => ucfirst($role),
            'reports' => $data
        ]);
    }

    public function generateAndSaveReport(Request $request)
    {
        $admin = $request->user(); 

        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admins can generate and save reports.'
            ], 403);
        }

        $role = $request->query('role');
        $validRoles = ['student', 'teacher', 'parent', 'admin'];

        if (!in_array($role, $validRoles)) {
            return response()->json(['success' => false, 'message' => 'Invalid role'], 400);
        }

        $data = $this->buildReportData($role);

        if (!$data || $data->isEmpty()) {
            return response()->json(['success' => false, 'message' => "No users found for category: $role"], 404);
        }

        $savedReport = Report::create([
            'admin_id'    => $admin->id,    
            'category'    => $role,
            'report_data' => $data,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Historical ' . ucfirst($role) . ' report archived by Admin: ' . $admin->name,
            'data' => $savedReport
        ]);
    }

    public function getSavedReportsHistory(Request $request)
    {
        $admin = $request->user();

        if (!$admin || $admin->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: Admin only.'], 403);
        }

        $history = Report::with('admin:id,name') 
            ->orderBy('created_at', 'desc')
            ->get();

        if ($history->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No historical reports found.',
                'reports' => []
            ]);
        }

        return response()->json([
            'success' => true,
            'reports' => $history
        ]);
    }
}