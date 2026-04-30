<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanLog extends Model
{
    protected $primaryKey = 'scan_id';

    public $timestamps = false;

    public const CREATED_AT = 'scan_time';

    protected $fillable = [
        'package_id',
        'user_id',
        'scan_time',
        'status',
    ];

    protected $casts = [
        'scan_time' => 'datetime',
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
