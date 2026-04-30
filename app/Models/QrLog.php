<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrLog extends Model
{
    protected $primaryKey = 'qr_log_id';

    public $timestamps = false;

    protected $fillable = [
        'package_id',
        'user_id',
        'qr_text',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id', 'package_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
