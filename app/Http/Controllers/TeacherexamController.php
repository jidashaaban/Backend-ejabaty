<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Exam;
use App\Models\Courses; // Keep as Courses based on your previous error message
use App\Models\ExamQuestion;
use App\Models\User;

class TeacherexamController extends Controller
{
    /**
     * STAGE 1: CREATE THE EXAM
     * The teacher creates the exam shell and adds questions.
     */
    public function createExam(Request $request, $teacherId)
    {
        $requester = auth()->user();

        if (!$requester || $requester->role !== 'teacher') {
                return response()->json([
                    'message' => 'Forbidden: Only Teachers can access this feature.'
    ], 403);
}
        // 1. First, check if the course exists at all
        $course = Courses::find($request->course_id);

        if (!$course) {
            return response()->json([
                'message' => 'This course does not exist.'
            ], 404);
        }

        // 2. SECURITY: Check if this specific teacher is the owner of the course
        if ($course->teacher_id != $teacherId) {
            return response()->json([
                'message' => "Access Denied: You cannot create an exam for a course you don't teach!"
            ], 403); 
        }

        // 3. If authorized, validate the rest of the data
        $request->validate([
            'title' => 'required|string',
            'questions' => 'required|array',
        ]);

        // 4. Create the Exam shell
        $exam = Exam::create([
            'course_id' => $course->id,
            'title' => $request->title,
            'is_published' => false // Not published until teacher adds answers
        ]);

        // 5. Process and link Questions
        foreach ($request->questions as $qText) {
            // Create the question in the bank
            $question = ExamQuestion::create(['question' => $qText]);
            
            // Link it to this exam in the pivot table (answer remains null for now)
            $exam->questions()->attach($question->id);
        }

        // Fetch students enrolled in this course
         $course = Courses::with('students')->find($request->course_id);

        foreach ($course->students as $student) {
           $student->notify(new SchoolNotification(
               "New Exam Created",
               "An exam has been scheduled for " . $course->name . ": " . $request->title,
               "exam_created"
        ));
    }

        return response()->json([
            'message' => 'Exam and questions created successfully!', 
            'exam' => $exam->load('questions')
        ], 201);
    }

    /**
     * STAGE 2: RETRIEVE THE EXAM
     * Teacher gets the exam to start adding answers.
     */
    public function getExamForMarking(Request $request,$teacherId, $examId)
    {
        $requester = auth()->user();

        if (!$requester || $requester->role !== 'teacher') {
           return response()->json([
                'message' => 'Forbidden: Only Teachers can access this feature.'
    ], 403);
}
        // Find the exam and ensure the teacher owns the parent course
        $exam = Exam::where('id', $examId)
            ->whereHas('course', function($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId);
            })
            ->with('questions')
            ->first();

        if (!$exam) {
            return response()->json([
                'message' => 'Exam not found or you are not authorized to view it.'
            ], 403);
        }

        return response()->json($exam);
    }

    /**
     * STAGE 3: SUBMIT MARKING SCHEME
     * Teacher saves the answers into the pivot table.
     */
    public function submitMarkingScheme(Request $request, $examId)
    {
        $requester = auth()->user();

        if (!$requester || $requester->role !== 'teacher') {
               return response()->json([
                   'message' => 'Forbidden: Only Teachers can access this feature.'
    ], 403);
}
        // 1. Find the exam and check authorization
        $exam = Exam::with('course')->findOrFail($examId);
        
        // Optional: If you pass teacherId in the request, add a check here too
        // if ($exam->course->teacher_id != $request->teacher_id) { ... }

        $request->validate([
            'marking_data' => 'required|array',
            'marking_data.*.question_id' => 'required|exists:exam_questions,id',
            'marking_data.*.answer' => 'required|string',
        ]);

        $answers = $request->input('marking_data'); 

        foreach ($answers as $data) {
            // Update the 'answer' column specifically in the pivot table (marking scheme)
            $exam->questions()->updateExistingPivot($data['question_id'], [
                'answer' => $data['answer']
            ]);
        }

        // Once answers are saved, publish the exam so students can see it
        $exam->update(['is_published' => true]);

        // --- NOTIFICATION LOGIC ---
        $exam->load('course.students'); // Get everyone in the class
    
        foreach ($exam->course->students as $student) {
            $student->notify(new SchoolNotification(
                "Marking Scheme Available",
                "The correct answers for '" . $exam->title . "' are now live. Check your dashboard to review.",
                "marking_scheme_published"
        ));
    }

        return response()->json([
            'message' => 'Marking scheme submitted successfully!'
        ]);
    }
}