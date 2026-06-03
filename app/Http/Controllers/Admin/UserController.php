<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule; 
use App\Notifications\SchoolNotification;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'admin') {
             return response()->json([
                'message' => 'Forbidden: Only Administrators can perform this action.'
    ], 403);
}
        $request->validate([
            'name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required_unless:role,student|nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in(['admin', 'teacher', 'student', 'parent'])],
            'phone_number'=>'required|string|max:20',
            'health_state'=>'nullable|string|max:1000',
            'grade'=>'required_if:role,student|string',
            'past_education'=>'required_if:role,student|string',
            'last_years_mark' => 'required_if:role,student|numeric',
            'status' => 'required_if:role,student|in:active,unactive',
            'course_ids' => 'required_if:role,student,teacher|array', 
            'student_ids' => 'required_if:role,parent|array|min:1',
            'student_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('role', 'student');
                }),
                'unique:parent_student,student_id'
            ],
        ], [
            
            'student_ids.*.exists' => 'One or more selected users are not registered as students.',
            'student_ids.*.unique' => 'One or more students are already linked to another parent.'
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $user = User::create([
                    'name' => $request->name,
                    'father_name' => $request->father_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => $request->role,
                    'phone_number'=>$request->phone_number,
                    'grade' => $request->grade,
                    'past_education' => $request->past_education,
                    'last_years_mark' => $request->last_years_mark,
                    'health_state'=>$request->health_state,
                    'status' => $request->status ?? 'active',
                ]);

                if ($user->role === 'parent') {
                    $user->children()->attach($request->student_ids);

                    foreach ($request->student_ids as $studentId) {
                    $student = User::find($studentId);
                    if ($student) {
                          $student->notify(new SchoolNotification(
                             "Parent Account Linked",
                             "A parent account ($user->name) has been linked to your profile.",
                             "parent_link",
                             $user->id
                ));
                
            }
        }
                }

            
            if ($user->role === 'student' && $request->has('course_ids')) {
                
                $user->courses()->attach($request->course_ids, [
                    'status' => 'booked',
                    'booked_at' => now()
                ]);
            }

            
            if ($user->role === 'teacher' && $request->has('course_ids')) {
                
                \App\Models\Courses::whereIn('id', $request->course_ids)
                    ->update(['teacher_id' => $user->id]);
            }
                return response()->json([
                    'success' => true,
                    'message' => 'User created and linked successfully!',
                    'user' => $user->load('children')
                ], 201);
            });

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Retrieve a list of students who are not yet linked to a parent.
     *
     * The parent creation form should only display students that do not already have
     * a parent. Without this restriction, an admin could attempt to assign a
     * student who is already linked and then receive a confusing validation error.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function availableStudents()
    {
        //only authenticated admins can fetch this list
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'message' => 'Forbidden: Only Administrators can perform this action.'
            ], 403);
        }

        //return students who do not have any parents 
        $students = User::where('role', 'student')
            ->whereDoesntHave('parents')
            ->select('id', 'name', 'email')
            ->get();

        return response()->json($students);
    }
}