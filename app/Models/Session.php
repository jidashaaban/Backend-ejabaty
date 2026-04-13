<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_id', 
        'course_id', 
        'day', 
        'start_time', 
        'end_time'
    ];

    public function schedule(){
        return $this->belongsTo(Schedule::class);
    }

    public function course()
    {
        // Use 'Courses' to match your plural model name
        return $this->belongsTo(Courses::class);
    }

    
}
