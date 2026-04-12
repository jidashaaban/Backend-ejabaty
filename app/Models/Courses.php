<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Courses extends Model
{
    protected $fillable = ['name', 'code'];
    use HasFactory;
    public function students()
{
    return $this->belongsToMany(User::class, 'course_student','course_id','user_id');
}
public function teacher() {
    return $this->belongsTo(User::class,'teacher_id');
}

}
