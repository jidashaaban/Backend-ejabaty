<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hall;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class HallController extends Controller
{
    // 1. ADD / CREATE (Modified to stop deleting old halls)
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'name' => 'required|string|unique:halls,name',
            'capacity' => 'required|integer|min:1',
        ]);

        $hall = Hall::create([
            'name' => $request->name,
            'capacity' => $request->capacity
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Hall added successfully!',
            'data' => $hall
        ]);
    }

    // 2. LIST ALL
    public function index()
    {
        return response()->json(Hall::all());
    }

    // 3. UPDATE
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $hall = Hall::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:halls,name,' . $id,
            'capacity' => 'required|integer|min:1',
        ]);

        $hall->update($request->only(['name', 'capacity']));

        return response()->json([
            'success' => true,
            'message' => 'Hall updated successfully!',
            'data' => $hall
        ]);
    }

    // 4. DELETE
    public function destroy($id)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $hall = Hall::findOrFail($id);

        // Safety check: Unlink this hall from any sessions before deleting
        Schema::disableForeignKeyConstraints();
        DB::table('sessions')->where('hall_id', $id)->update(['hall_id' => null]);
        DB::table('hall_assignments')->where('hall_id', $id)->delete();
        
        $hall->delete();
        Schema::enableForeignKeyConstraints();

        return response()->json([
            'success' => true,
            'message' => 'Hall deleted successfully.'
        ]);
    }
}