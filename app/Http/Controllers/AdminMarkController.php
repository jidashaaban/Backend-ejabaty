<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exam;
use App\Models\User; 

class AdminMarkController extends Controller
{
    public function submitStudentMark(Request $request)
    {
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
