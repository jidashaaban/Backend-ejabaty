<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * When creating a poll we also need to persist the admin
     * responsible for the poll, so include `admin_id` here. Without
     * making `admin_id` fillable Laravel will silently ignore the
     * attribute which results in a null value being stored in the
     * database and can lead to confusing 500 errors when foreign
     * key constraints are applied. See PollController@store for
     * where this is set.
     */
    protected $fillable = [
        'title',
        'description',
        'expires_at',
        'admin_id',
    ];

public function options()
{
    return $this->hasMany(PollOption::class);
}
public function questions()
{
    return $this->hasMany(PollQuestion::class);
}
}
