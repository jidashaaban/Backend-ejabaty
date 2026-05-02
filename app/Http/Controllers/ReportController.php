<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User; 

class ReportController extends Controller
{
    public function getUserReports(Request $request){
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
}
