<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'package_id';

    public $timestamps = false;

    protected $fillable = [
        'package_code',
        'box_id',
        'qr_text',
        'qty',
    ];

    protected $casts = [
        'qty' => 'integer',
        'created_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class, 'box_id', 'box_id');
    }

    public function qrLogs(): HasMany
    {
        return $this->hasMany(QrLog::class, 'package_id', 'package_id');
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class, 'package_id', 'package_id');
    }

    public function countingResults(): HasMany
    {
        return $this->hasMany(CountingResult::class, 'package_id', 'package_id');
    }
}
