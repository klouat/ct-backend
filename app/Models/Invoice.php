<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'invoice_id';

    public $timestamps = false;

    protected $fillable = [
        'invoice_code',
        'po_number',
        'vendor_id',
        'product_id',
        'product_name',
        'qr_text',
        'status',
        'target_box_count',
        'scanned_box_count',
        'last_scanned_at',
        'estimated_arrival_date',
    ];

    protected function casts(): array
    {
        return [
            'target_box_count' => 'integer',
            'scanned_box_count' => 'integer',
            'last_scanned_at' => 'datetime',
            'estimated_arrival_date' => 'date',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class, 'invoice_id', 'invoice_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'vendor_id');
    }
}
