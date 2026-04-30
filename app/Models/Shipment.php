<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'shipment_id';

    public $timestamps = false;

    protected $fillable = [
        'shipment_code',
        'vendor_id',
        'shipment_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'shipment_date' => 'date',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'vendor_id');
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class, 'shipment_id', 'shipment_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ShipmentLocation::class, 'shipment_id', 'shipment_id');
    }
}
