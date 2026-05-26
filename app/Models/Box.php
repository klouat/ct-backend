<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Box extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'box_id';

    public $timestamps = false;

    protected $fillable = [
        'box_code',
        'invoice_id',
        'vendor_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'vendor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BoxItem::class, 'box_id', 'box_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(BoxLocation::class, 'box_id', 'box_id');
    }
}
