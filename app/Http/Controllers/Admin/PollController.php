<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Poll;

class PollController extends Controller
{
    public function store(Request $request)
    {
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
}
