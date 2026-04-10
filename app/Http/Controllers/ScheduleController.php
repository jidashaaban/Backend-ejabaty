<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ScheduleGenerator;
use App\Models\Schedule;

class ScheduleController extends Controller
{
    /**
     * Handle the generation request and return JSON.
     */
    public function store(Request $request, ScheduleGenerator $generator)
    {
        // Validate the incoming request [cite: 155]
        $request->validate([
            'type' => 'required|in:exam,course'
        ]);

        try {
            // Trigger the generation engine [cite: 142, 155]
            $schedule = $generator->generate($request->type);

            // Load the sessions to include them in the JSON output 
            return response()->json([
                'success' => true,
                'message' => 'Schedule generated successfully',
                'data' => $schedule->load('sessions')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Generation failed: ' . $e->getMessage()
            ], 500);
        }
    }
}