<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public function courses(){
    return $this->belongsToMany(Courses::class, 'user_course','user_id','course_id');

    return $this->belongsToMany(Courses::class, 'user_course', 'user_id', 'course_id')
                ->withPivot('status', 'booked_at')
                ->withTimestamps();
    }

    public function exams() {
    return $this->belongsToMany(Exam::class, 'exam_student','student_id','exam_id')
                ->withPivot('mark')
                ->withTimestamps();
}

    public function pastQuizzes() {
    return $this->belongsToMany(Quiz::class, 'quiz_student', 'student_id', 'quiz_id')
                ->withPivot('points', 'comment')
                ->withTimestamps();
}
    public function complaints()
{
    return $this->hasMany(Complaint::class, 'parent_id');
}  

    public function children()
{
    return $this->belongsToMany(User::class, 'parent_student','parent_id','student_id');
}  

public function quizzes()
{
    // We use 'quiz_student' as the pivot table name 
    // If your migration used 'student_id', specify it as the 3rd parameter [cite: 88]
    return $this->belongsToMany(Quiz::class, 'quiz_student', 'student_id', 'quiz_id')
                ->withPivot('points', 'comment') // This allows access to the marks [cite: 110, 135]
                ->withTimestamps();
}


    use HasApiTokens, HasFactory, Notifiable;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
