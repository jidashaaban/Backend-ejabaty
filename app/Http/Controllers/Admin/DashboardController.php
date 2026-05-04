<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Complaint;
use App\Models\Poll;
use App\Models\Courses; 
use App\Models\Schedule;
use App\Models\Session;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request){
        $stats = [
            'students'=>User::where('role','student')->count(),
            'teachers'=>User::where('role','teacher')->count(),
            'parents'=>User::where('role','parent')->count(),
            'pending_complaints'=>Complaint::whereNull('answer_text')->count(),
            'total_courses'=>Courses::count(),
            'total_polls'=>Poll::count()
        ];
        $type = $request->query('type','course');

        $scheduleGrid = $this->getFormattedMasterSchedule($type);
        return response()->json([
            'metrics'=> $stats,
            'viewing_type'=> $type,
            'master_schedule'=> $scheduleGrid,
        ]);
    }
    private function getFormattedMasterSchedule($type){
        $schedule = Schedule::where('type',$type)->latest()->first();

        if(!$schedule){
            return null;
        }
        $sessions = Session::where('schedule_id',$schedule->id)
               ->with('course:id,name,code')
               ->get();
        $grid = [];
        foreach($sessions as $session){
            $day = $session->day;
            $time = $session->start_time;
            $grid[$day][$time][]=[
                'course_name'=>$session->course->name,
                'course_code'=>$session->course->code,
                'end_time'=>$session->end_time,
                'hall'=>$session->hall_name ?? 'TBA'
            ];
        }   
        return $grid;    
    }
}
