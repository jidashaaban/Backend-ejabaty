<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Poll;
use App\Models\User;
use App\Notifications\SchoolNotification;

class PollController extends Controller
{
    public function store(Request $request)
    {
        $admin = $request->user();

        if (!$admin || $admin->role !== 'admin') {
            return response()->json([
               'message' => 'Forbidden: Only Administrators can perform this action.'
    ], 403);
}
        // 1. Validate the input
        $request->validate([
            'question' => 'required|string',
            'options' => 'required|array|min:2', // At least two answer options
            'options.*' => 'required|string'
        ]);

        // 2. Save the Question
        $poll = Poll::create(['question' => $request->question]);

        // 3. Save the Answers (Options)
        foreach ($request->options as $optionText) {
            $poll->options()->create(['option_text' => $optionText]);
        }
        $usersToNotify = User::where('role', 'student')->get();

        foreach ($usersToNotify as $user) {
            $user->notify(new SchoolNotification(
               "New Poll Available!",
               "The Admin has posted a new poll: " . $poll->question,
               "new_poll",
               $poll->id
        ));
    }
        return response()->json([
            'success' => true,
            'message' => 'Poll created successfully!',
            'data' => $poll->load('options')
        ], 201);
    }

    public function index()
{
    // Retrieve all polls with their options so the student can see the answers
    $polls = Poll::with('options')->latest()->get();

    return response()->json([
        'success' => true,
        'data' => $polls
    ]);
}

public function show($id) {
    $poll = Poll::with('options')->find($id);
    if (!$poll) return response()->json(['message' => 'Poll not found'], 404);
    return response()->json($poll, 200);
}
public function update(Request $request, $id) {
    $poll = Poll::find($id);
    if (!$poll) return response()->json(['message' => 'Poll not found'], 404);

    $poll->update(['question' => $request->question]);
    $students = User::where('role', 'student')->get();
    foreach ($students as $student) {
        $student->notify(new SchoolNotification("Poll Update", "A poll you are eligible for has been updated.", "new_poll"));
    }
    // Note: If updating options, you may need to delete old ones and re-add [cite: 121]
    return response()->json(['message' => 'Poll updated successfully', 'poll' => $poll], 200);
}
public function destroy($id) {
    $poll = Poll::find($id);
    if (!$poll) return response()->json(['message' => 'Poll not found'], 404);

    $poll->delete(); // onDelete('cascade') handles the options [cite: 121, 134]
    return response()->json(['message' => 'Poll deleted successfully'], 200);
}
}
