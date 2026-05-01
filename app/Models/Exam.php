<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use HasFactory;
    protected $fillable = ['course_id', 'title', 'is_published'];
    public function course() {
        return $this->belongsTo(Courses::class);
    }

    public function questions() {
        return $this->belongsToMany(ExamQuestion::class)
                    ->withPivot('answer')
                    ->withTimestamps();
    }

    public function students() {
    return $this->belongsToMany(User::class, 'exam_student','exam_id','student_id') // adjust Student::class if needed
                ->withPivot('mark')
                ->withTimestamps();
}
}
