<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Complaint; 
use App\Models\User;
use App\Notifications\SchoolNotification;

class ComplaintController extends Controller
{
    public function submitComplaint(Request $request)
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
            'parent_id' => $user->id,
            'subject' => $request->subject,
            'complaint_text' => $request->complaint_text,
        ]);
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
        $admin->notify(new SchoolNotification(
            "New Parent Complaint",
            "A parent has submitted a complaint regarding: " . $request->subject,
            "new_complaint",
             $request->subject
        ));
    }

        return response()->json(['success' => true, 'message' => 'Complaint sent to administration.', 'complaint' => $complaint]);
    }

    // Parent views their complaints and admin answers
    public function viewComplaints(Request $request)
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'parent') {
             return response()->json([
                 'message' => 'Forbidden: Only Parents can view this dashboard.'
    ], 403);
}
        $complaints = Complaint::where('parent_id', $user->id)->get();
        return response()->json(['success' => true, 'complaints' => $complaints]);
    }

    // Admin answers a complaint
    public function answerComplaint(Request $request, $complaintId)
    {
        $user = auth()->user();

          if (!$user || $user->role !== 'admin') {
              return response()->json([
                 'message' => 'Forbidden: Only Administrators can perform this action.'
    ], 403);
}
        

        $request->validate([
            'answer_text' => 'required|string',
        ]);

        $complaint = Complaint::findOrFail($complaintId);
        $adminId = $request->user()->id;
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
            "complaint_response",
            $complaint->subject
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
        "complaint_response",
        $complaint->subject
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
    
    $complaints = Complaint::with('parent:id,name,email')
        ->latest()
        ->get();

    return response()->json([
        'success' => true,
        'count' => $complaints->count(),
        'data' => $complaints
    ]);
}
public function updateComplaint(Request $request, $id)
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'parent') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Find the complaint belonging to this parent
        $complaint = Complaint::where('id', $id)->where('parent_id', $user->id)->first();

        if (!$complaint) {
            return response()->json(['message' => 'Complaint not found.'], 404);
        }

        // Logic: Cannot update if the admin has already answered or resolved it
        if ($complaint->answer_text !== null && $complaint->answer_text !== '') {
            return response()->json(['message' => 'Cannot update a complaint that has already been answered.'], 400);
        }

        $request->validate([
            'subject' => 'required|string',
            'complaint_text' => 'required|string',
        ]);

        $complaint->update([
            'subject' => $request->subject,
            'complaint_text' => $request->complaint_text,
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Complaint updated successfully.', 
            'complaint' => $complaint
        ]);
    }

    // 2. Parent deletes a complaint
    public function deleteComplaint($id)
{
    $user = auth()->user();
    $complaint = Complaint::find($id);

    // 1. Check if complaint exists
    if (!$complaint) {
        return response()->json(['message' => 'Complaint not found.'], 404);
    }

    // 2. ADMIN LOGIC: Can delete anything
    if ($user->role === 'admin') {
        $complaint->delete();
        return response()->json([
            'success' => true, 
            'message' => 'Administrative Action: Complaint deleted successfully.'
        ]);
    }

    // 3. PARENT LOGIC: Can only delete their OWN if it's still PENDING
    if ($user->role === 'parent') {
        // Ownership check
        if ($complaint->parent_id !== $user->id) {
            return response()->json(['message' => 'Access Denied: You can only delete your own complaints.'], 403);
        }

        // Status check: Prevent deleting if Admin already started working on it
        if ($complaint->answer_text !== null || $complaint->status === 'resolved') {
            return response()->json(['message' => 'Cannot delete a complaint that has already been answered.'], 400);
        }

        $complaint->delete();
        return response()->json(['success' => true, 'message' => 'Your complaint has been removed.']);
    }

    // 4. Default Forbidden for other roles (Teachers, etc.)
    return response()->json(['message' => 'Forbidden: You do not have permission to delete this record.'], 403);
}
}
