<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule; // Import this!

class UserController extends Controller
{
    public function store(Request $request)
    {
        $requester = User::find($request->query('requester_id'));

        if (!$requester || $requester->role !== 'admin') {
             return response()->json([
                'message' => 'Forbidden: Only Administrators can perform this action.'
    ], 403);
}
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in(['admin', 'teacher', 'student', 'parent'])],

            // THE FIX:
            // 1. Array is required if role is parent.
            // 2. Each ID must exist in users table AND have the role 'student'.
            'student_ids' => 'required_if:role,parent|array|min:1',
            'student_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('role', 'student');
                }),
                'unique:parent_student,student_id'
            ],
        ], [
            // Custom error message so the Admin knows WHY it failed
            'student_ids.*.exists' => 'One or more selected users are not registered as students.',
            'student_ids.*.unique' => 'One or more students are already linked to another parent.'
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => $request->role,
                ]);

                if ($user->role === 'parent') {
                    $user->children()->attach($request->student_ids);
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
}