<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'status',
        'target_box_count',
        'estimated_arrival_date',
    ];

    protected function casts(): array
    {
        return [
            'target_box_count' => 'integer',
            'estimated_arrival_date' => 'date',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(Box::class, 'invoice_id', 'invoice_id');
    }
}
