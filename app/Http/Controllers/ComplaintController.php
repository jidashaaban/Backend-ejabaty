<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Complaint; 
use App\Models\User;

class ComplaintController extends Controller
{
    public function submitComplaint(Request $request, $parentId)
    {
        $requester = User::find($request->query('requester_id'));

        if (!$requester || $requester->role !== 'parent') {
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

        return response()->json(['success' => true, 'message' => 'Complaint sent to administration.', 'complaint' => $complaint]);
    }

    // Parent views their complaints and admin answers
    public function viewComplaints($parentId)
    {
        $requester = User::find($request->query('requester_id'));

        if (!$requester || $requester->role !== 'parent') {
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
        $requester = User::find($request->query('requester_id'));

          if (!$requester || $requester->role !== 'admin') {
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

        return response()->json(['success' => true, 'message' => 'Complaint answered successfully.']);
    }
}
