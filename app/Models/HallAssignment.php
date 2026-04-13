<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HallAssignment extends Model
{
    use HasFactory;
    protected $fillable = ['session_id','student_id','hall_id'];
    public function hall()
    {
        return $this->belongsTo(Hall::class, 'hall_id');
    }
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
