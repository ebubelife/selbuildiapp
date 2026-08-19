<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['path', 'user_id', 'session_id', 'ip_address', 'referrer'])]
class PageView extends Model
{
    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
