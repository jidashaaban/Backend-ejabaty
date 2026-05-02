<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;
    protected $fillable = ['admin_id', 'category', 'report_data'];

    protected $casts = [
        'report_data' => 'array', 
    ];

    public function admin() {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
