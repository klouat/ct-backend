<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'vendor_id';

    public $timestamps = false;

    protected $fillable = [
        'vendor_name',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'vendor_id', 'vendor_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'vendor_id', 'vendor_id');
    }
}
