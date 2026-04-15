<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;
    protected $fillable = [
        'course_id',
        'teacher_id',
        'quiz_date',
        'start_time',
        'included_content'
    ];
    public function course()
    {
        return $this->belongsTo(Courses::class);
    }

}
