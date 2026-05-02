<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; 
use App\Models\Report;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function getUserReports(Request $request){
        $requester = User::find($request->query('requester_id'));
        if (!$requester || $requester->role !== 'admin') {
            return response()->json(['message' => 'Forbidden: Admin only.'], 403);
    }
        $role = $request->query('role');
        $validRoles = ['student','teacher','parent','admin'];
        if(!in_array($role,$validRoles)){
            return response()->json(['error'=>'Invalid role'],400);
        }
        if($role === 'student'){
            $data = User::where('role','student')
                  ->with(['courses','exams','quizzes'])
                  ->get()
                  ->map(function($student){
                    return[
                        'name'=>$student->name,
                        'enrolled_courses'=>$student->courses->pluck('name'),
                        'exam_marks'=>$student->exams->map(function($exam){
                            return['exam'=>$exam->course->name,'mark'=>$exam->pivot->mark];
                        }),
                        'quiz_points'=>$student->quizzes->map(function($quiz){
                            return['course'=>$quiz->course->name,'points'=>$quiz->pivot->points];
                        })
                    ];
                  });

        }
        elseif($role === 'teacher'){
            $data = User::where('role','teacher')
                  ->with(['teacherCourses','quizzes'])
                  ->get()
                  ->map(function($teacher){
                    return[
                        'name'=>$teacher->name,
                        'teaching_courses'=>$teacher->teacherCourses->pluck ('name')
                    ];
                  });
        }
        elseif($role === 'parent'){
            $data=User::where('role','parent')
                 ->with(['children','complaints'])
                 ->get()
                 ->map(function($parent){
                    return[
                        'name'=>$parent->name,
                        'children'=>$parent->children->pluck('name'),
                        'complaints_history'=>$parent->complaints->map(function($c){
                            return['subject'=>$c->subject,'status'=>$c->is_resolved ? 'Resolved' : 'Pending'];
                        })
                    ];
                 });
        }
        elseif($role === 'admin'){
            $data = User::where('role', 'admin')
                ->withCount(['polls', 'schedules']) // Admins usually track volume of work
                ->get()
                ->map(function ($admin) {
                    return [
                        'name' => $admin->name,
                        'total_polls_created' => $admin->polls_count,
                        'total_schedules_generated' => $admin->schedules_count,
                    ];
                });
        }
        return response()->json([
            'category' => ucfirst($role),
            'reports' => $data
        ]);
    }
    public function generateAndSaveReport(Request $request)
{
    // 1. Identify who is making the request
    $requesterId = $request->query('requester_id'); 
    $admin = \App\Models\User::find($requesterId);

    // 2. Security: Verify the user exists AND is an administrator
    if (!$admin || $admin->role !== 'admin') {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only admins can generate and save reports.'
        ], 403);
    }

    $role = $request->query('role');
    $reportContent = null;

    // 3. Build the specific qualitative report content
    if ($role === 'student') {
        $reportContent = \App\Models\User::where('role', 'student')
            ->with(['courses', 'exams', 'quizzes'])
            ->get()
            ->map(function ($student) {
                return [
                    'name' => $student->name,
                    'courses' => $student->courses->pluck('name'),
                    'marks' => $student->exams->map(fn($e) => ['course' => $e->course->name ?? 'N/A', 'mark' => $e->pivot->mark]),
                    'quizzes' => $student->quizzes->map(fn($q) => ['points' => $q->pivot->points]),
                ];
            });
    } 
    elseif ($role === 'teacher') {
        $reportContent = \App\Models\User::where('role', 'teacher')
            ->with(['teacherCourses', 'announcedQuizzes'])
            ->get()
            ->map(function ($teacher) {
                return [
                    'name' => $teacher->name,
                    'teaching' => $teacher->teacherCourses->pluck('name'),
                    'quizzes_announced' => $teacher->announcedQuizzes->count(),
                ];
            });
    } 
    elseif ($role === 'parent') {
        $reportContent = \App\Models\User::where('role', 'parent')
            ->with(['children', 'complaints'])
            ->get()
            ->map(function ($parent) {
                return [
                    'name' => $parent->name,
                    'children' => $parent->children->pluck('name'),
                    'complaints_count' => $parent->complaints->count(),
                ];
            });
    }

    // 4. Check if we actually found any users to report on
    if (!$reportContent || $reportContent->isEmpty()) {
        return response()->json(['success' => false, 'message' => "No users found for category: $role"], 404);
    }

    // 5. SAVE to the database (Fixing the undefined variable here)
    $savedReport = \App\Models\Report::create([
        'admin_id'    => $admin->id,    // Corrected: Uses the $admin object from step 1
        'category'    => $role,
        'report_data' => $reportContent,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Historical ' . $role . ' report archived by Admin: ' . $admin->name,
        'data' => $savedReport
    ]);
}
}
