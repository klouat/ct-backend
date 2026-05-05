<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoxLocation extends Model
{
    protected $primaryKey = 'box_location_id';

    public $timestamps = false;

    public const CREATED_AT = 'recorded_at';

    protected $fillable = [
        'box_id',
        'location_name',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class, 'box_id', 'box_id');
    }
}
