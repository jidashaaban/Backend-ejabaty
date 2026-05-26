<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Courses extends Model
{
    protected $fillable = ['name', 'code','capacity','teacher_id','is_active'];
    use HasFactory;
    public function students()
{

    return $this->belongsToMany(User::class, 'user_course', 'course_id', 'user_id')
                 ->withPivot('status', 'booked_at','is_active')
                ->withTimestamps();
}
public function teacher() {
    return $this->belongsTo(User::class,'teacher_id');
}

public function sessions()
{
    return $this->hasMany(Session::class, 'course_id');
}

}
