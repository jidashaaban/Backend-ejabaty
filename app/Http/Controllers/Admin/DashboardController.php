<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Complaint;
use App\Models\Poll;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(){
        $userBreakdown = [
            'total'=>User::count(),
            'students'=>User::where('role','student')->count(),
            'teachers'=>User::where('role','teacher')->count(),
            'admins'=>User::where('role','admin')->count(),
            'parents'=>User::where('role','parent')->count(),
        ];

        $pendingComplaints = Complaint::whereNull('answer_text')->count();

        $recentPoll = Poll::latest()->with(['options'=>function($query){
            $query->withCount('votes');
        }])->first();

        $topStudents = User::where('role','student')
                    ->withSum('quizzes as total_points','quiz_student.points')
                    ->orderBy('total_points','desc')
                    ->take(3)
                    ->get(['id','name','total_points']);
        
        return response()->json([
            'user_breakdown'=> $userBreakdown,
            'pending_complaints_count'=> $pendingComplaints,
            'recent_poll'=> $recentPoll,
            'leaderboard'=> $topStudents,
            'quick_links'=>[
                'generate_reports_url'=>'/api/admin/reports',
                'master_schedule_url'=>'/api/admin-schedule?type=course',
                'add_poll_url'=>'/api/admin/create-poll'
            ]
        ]);
    }
}
