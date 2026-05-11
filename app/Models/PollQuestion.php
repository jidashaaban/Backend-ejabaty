<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PollQuestion extends Model
{
    use HasFactory;
    protected $fillable = ['poll_id', 'question_text'];

public function options()
{
    return $this->hasMany(PollOption::class);
}

public function poll()
{
    return $this->belongsTo(Poll::class);
}
}
