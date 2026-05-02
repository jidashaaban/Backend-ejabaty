<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exam;
use App\Models\User; 
use App\Notifications\SchoolNotification;

class AdminMarkController extends Controller
{
    public function submitStudentMark(Request $request)
    {
        $requester = User::find($request->query('requester_id'));

        if (!$requester || $requester->role !== 'admin') {
            return response()->json([
              'message' => 'Forbidden: Only Administrators can perform this action.'
    ], 403);
}
        // 1. Validate the incoming request
        $request->validate([
            
            'student_id' => 'required|exists:users,id', 
            'exam_id' => 'required|exists:exams,id',
            'mark' => 'required|numeric|min:0|max:100',
        ]);

        // 2. Find the Exam
        $exam = Exam::findOrFail($request->exam_id);

        // 3. Attach the mark to the pivot table
        // We use syncWithoutDetaching so we don't remove other students already graded
        $exam->students()->syncWithoutDetaching([
            $request->student_id => ['mark' => $request->mark]
        ]);
        $student = User::find($request->student_id);
        $courseName = $exam->course ? $exam->course->name : 'your recent exam';
        $student->notify(new SchoolNotification(
            "New Exam Grade Released",
            "Your mark for $courseName has been submitted. Your grade: " . $request->mark . "%",
            "exam_result"
        ));
        foreach ($student->parents as $parent) {
        $parent->notify(new SchoolNotification(
            "Child Progress Update",
            "A new grade has been posted for " . $student->name . " in $courseName. Grade: " . $request->mark . "%",
            "child_grade_alert"
        ));
    }

        return response()->json([
            'message' => 'Student mark submitted successfully!',
            'data' => [
                'exam_id' => $exam->id,
                'student_id' => $request->student_id,
                'mark' => $request->mark
            ]
        ], 200);
    }
}
