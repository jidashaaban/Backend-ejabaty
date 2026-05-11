<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Poll;
use App\Models\User;
use App\Models\PollQuestion;
use App\Models\PollOption;
use App\Notifications\SchoolNotification;

class PollController extends Controller
{
    // Private helper to avoid repeating the admin check code
    private function checkAdmin(Request $request) {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return false;
        }
        return true;
    }

    // 1. CREATE POLL
    public function store(Request $request)
    {
        if (!$this->checkAdmin($request)) {
            return response()->json(['message' => 'Forbidden: Admin only.'], 403);
        }

        $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'expires_at' => 'required|date|after:now',
            'questions' => 'required|array|min:1',
            'questions.*.question_text' => 'required|string',
            'questions.*.options' => 'required|array|min:2',
        ]);

        $poll = Poll::create($request->only('title', 'description', 'expires_at'));

        foreach ($request->questions as $qData) {
            $question = $poll->questions()->create(['question_text' => $qData['question_text']]);

            foreach ($qData['options'] as $optionText) {
                $question->options()->create(['option_text' => $optionText]);
            }
        }

        // Notify Students
        $usersToNotify = User::where('role', 'student')->get();
        foreach ($usersToNotify as $user) {
            $user->notify(new SchoolNotification(
                "New Poll Available!",
                "The Admin posted a new survey: " . $poll->title, // Fixed: title instead of question
                "new_poll",
                $poll->id
            ));
        }

        return response()->json(['success' => true, 'data' => $poll->load('questions.options')], 201);
    }

    // 2. LIST ALL POLLS
    public function index()
    {
        $polls = Poll::withCount('questions')
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $polls]);
    }

    // 3. SHOW SINGLE POLL
    public function show($id)
    {
        $poll = Poll::with(['questions.options'])->find($id);
        if (!$poll) return response()->json(['message' => 'Poll not found'], 404);
        return response()->json($poll, 200);
    }

    // 4. UPDATE POLL HEADER (Title/Description)
    public function update(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) return response()->json(['message' => 'Forbidden'], 403);

        $poll = Poll::find($id);
        if (!$poll) return response()->json(['message' => 'Poll not found'], 404);

        $request->validate([
            'title' => 'required|string',
            'expires_at' => 'required|date|after:now',
        ]);

        $poll->update($request->only('title', 'description', 'expires_at'));
        return response()->json(['message' => 'Poll header updated', 'poll' => $poll]);
    }

    // 5. UPDATE QUESTION & OPTIONS
    public function updateQuestion(Request $request, $id, $questionId)
    {
        if (!$this->checkAdmin($request)) return response()->json(['message' => 'Forbidden'], 403);

        $question = PollQuestion::where('poll_id', $id)->find($questionId);
        if (!$question) return response()->json(['message' => 'Question not found'], 404);

        $request->validate([
            'question_text' => 'required|string',
            'options' => 'required|array|min:2',
        ]);

        $question->update(['question_text' => $request->question_text]);
        $question->options()->delete();
        foreach ($request->options as $optionText) {
            $question->options()->create(['option_text' => $optionText]);
        }

        return response()->json(['message' => 'Question and options updated']);
    }

    // 6. DELETE QUESTION
    public function destroyQuestion(Request $request, $id, $questionId)
    {
        if (!$this->checkAdmin($request)) return response()->json(['message' => 'Forbidden'], 403);

        $question = PollQuestion::where('poll_id', $id)->find($questionId);
        if (!$question) return response()->json(['message' => 'Question not found'], 404);

        $question->delete();
        return response()->json(['message' => 'Question deleted']);
    }

    // 7. DELETE FULL POLL
    public function destroy(Request $request, $id)
    {
        if (!$this->checkAdmin($request)) return response()->json(['message' => 'Forbidden'], 403);

        $poll = Poll::find($id);
        if (!$poll) return response()->json(['message' => 'Poll not found'], 404);

        $poll->delete();
        return response()->json(['message' => 'Full poll deleted']);
    }

    // 8. UPDATE SINGLE OPTION
    public function updateOption(Request $request, $id, $questionId, $optionId)
    {
        if (!$this->checkAdmin($request)) return response()->json(['message' => 'Forbidden'], 403);

        $option = PollOption::whereHas('question', function ($q) use ($id, $questionId) {
            $q->where('id', $questionId)->where('poll_id', $id);
        })->find($optionId);

        if (!$option) return response()->json(['message' => 'Option not found'], 404);

        $request->validate(['option_text' => 'required|string']);
        $option->update(['option_text' => $request->option_text]);

        return response()->json(['message' => 'Option updated', 'option' => $option]);
    }

    // 9. DELETE SINGLE OPTION
    public function destroyOption(Request $request, $id, $questionId, $optionId)
    {
        if (!$this->checkAdmin($request)) return response()->json(['message' => 'Forbidden'], 403);

        $option = PollOption::whereHas('question', function ($q) use ($id, $questionId) {
            $q->where('id', $questionId)->where('poll_id', $id);
        })->find($optionId);

        if (!$option) return response()->json(['message' => 'Option not found'], 404);

        // Rule: Question must have at least 2 options
        if (PollOption::where('poll_question_id', $questionId)->count() <= 2) {
            return response()->json(['message' => 'Minimum 2 options required.'], 400);
        }

        $option->delete();
        return response()->json(['message' => 'Option deleted']);
    }
}