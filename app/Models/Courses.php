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
    return $this->belongsToMany(User::class, 'course_student');
}
}
