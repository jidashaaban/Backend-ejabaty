<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hall;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class HallController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validate the input
        // Expecting an array like: [['name' => 'Hall 1', 'capacity' => 50], ...]
        $request->validate([
            'halls' => 'required|array|min:1',
            'halls.*.name' => 'required|string|distinct',
            'halls.*.capacity' => 'required|integer|min:1',
        ]);

        Schema::disableForeignKeyConstraints();

        DB::table('hall_assignments')->truncate(); // Clear exam assignments
        DB::table('sessions')->update(['hall_id' => null]); // Unlink halls from course sessions
        // 2. Clear old halls (optional: remove if you want to keep adding new ones)
        // Usually, when an admin "enters the halls," they are setting up the building.
        Hall::truncate(); 

        Schema::enableForeignKeyConstraints();

        // 3. Save the new halls
        foreach ($request->halls as $hallData) {
            Hall::create([
                'name' => $hallData['name'],
                'capacity' => $hallData['capacity']
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => count($request->halls) . ' halls have been configured successfully.'
        ]);
    }

    /**
     * List all halls currently in the system.
     */
    public function index()
    {
        return response()->json(Hall::all());
    }
}
