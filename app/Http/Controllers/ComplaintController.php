<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Complaint; 
use App\Models\User;
use App\Notifications\SchoolNotification;

class ComplaintController extends Controller
{
    public function submitComplaint(Request $request, $parentId)
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'parent') {
             return response()->json([
                 'message' => 'Forbidden: Only Parents can view this dashboard.'
    ], 403);
}
        $request->validate([
            'subject' => 'required|string',
            'complaint_text' => 'required|string',
        ]);

        $complaint = Complaint::create([
            'parent_id' => $parentId,
            'subject' => $request->subject,
            'complaint_text' => $request->complaint_text,
        ]);
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
        $admin->notify(new SchoolNotification(
            "New Parent Complaint",
            "A parent has submitted a complaint regarding: " . $request->subject,
            "new_complaint"
        ));
    }

        return response()->json(['success' => true, 'message' => 'Complaint sent to administration.', 'complaint' => $complaint]);
    }

    // Parent views their complaints and admin answers
    public function viewComplaints(Request $request,$parentId)
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'parent') {
             return response()->json([
                 'message' => 'Forbidden: Only Parents can view this dashboard.'
    ], 403);
}
        $complaints = Complaint::where('parent_id', $parentId)->get();
        return response()->json(['success' => true, 'complaints' => $complaints]);
    }

    // Admin answers a complaint
    public function answerComplaint(Request $request, $adminId, $complaintId)
    {
        $user = auth()->user();

          if (!$user || $user->role !== 'admin') {
              return response()->json([
                 'message' => 'Forbidden: Only Administrators can perform this action.'
    ], 403);
}
        $admin = User::where('id', $adminId)->where('role', 'admin')->first();
    
    if (!$admin) {
        return response()->json([
            'success' => false, 
            'message' => 'Unauthorized. Only administrators can answer complaints.'
        ], 403); // 403 Forbidden
    }

        $request->validate([
            'answer_text' => 'required|string',
        ]);

        $complaint = Complaint::findOrFail($complaintId);

        if ($complaint->answer_text !== null) {
        return response()->json([
            'success' => false, 
            'message' => 'Conflict. This complaint has already been answered by another administrator.'
        ], 409); // 409 Conflict
    }
        $complaint->update([
            'admin_id' => $adminId,
            'answer_text' => $request->answer_text,
        ]);

        $parent = User::find($complaint->parent_id);
        if ($parent) {
        $parent->notify(new SchoolNotification(
            "Complaint Answered",
            "The administration has responded to your inquiry: " . $complaint->subject,
            "complaint_response"
        ));
    }

        return response()->json(['success' => true, 'message' => 'Complaint answered successfully.']);
    }
    public function updateAnswer(Request $request, $id) {
    $complaint = Complaint::find($id);
    if (!$complaint) return response()->json(['message' => 'Complaint not found'], 404);

    $complaint->update([
        'answer_text' => $request->answer_text,
        'status' => 'resolved'
    ]);

    // Notify the parent [cite: 1056]
    $parent = User::find($complaint->parent_id);
    $parent->notify(new SchoolNotification(
        "Complaint Answered", 
        "An admin has responded to your complaint regarding: {$complaint->subject}", 
        "complaint_response"
    ));

    return response()->json(['message' => 'Answer saved and parent notified'], 200);
}
public function getAllComplaints(Request $request)
{
    $user = auth()->user();

    // 1. Security Check: Only admins allowed
    if (!$user || $user->role !== 'admin') {
        return response()->json([
            'message' => 'Forbidden: Only Administrators can view the master complaint list.'
        ], 403);
    }

    // 2. Fetch all complaints with parent details
    // We assume you have a 'parent' relationship defined in your Complaint model
    $complaints = Complaint::with('parent:id,name,email')
        ->latest()
        ->get();

    return response()->json([
        'success' => true,
        'count' => $complaints->count(),
        'data' => $complaints
    ]);
}
}
