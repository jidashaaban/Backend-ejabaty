<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamQuestion extends Model
{
    use HasFactory;
    protected $fillable = ['question', 'answer'];
    public function exams(){
        return $this->belongsToMany(Exam::class)
                    ->withPivot('answer')
                    ->withTimestamps();
    }
}
