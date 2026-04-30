<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountingResult extends Model
{
    protected $primaryKey = 'counting_id';

    public $timestamps = false;

    public const CREATED_AT = 'counted_time';

    protected $fillable = [
        'package_id',
        'counted_qty',
        'counted_time',
    ];

    protected $casts = [
        'counted_qty' => 'integer',
        'counted_time' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id', 'package_id');
    }
}
