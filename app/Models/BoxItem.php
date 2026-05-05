<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BoxItem extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'box_item_id';

    public $timestamps = false;

    protected $fillable = [
        'box_id',
        'sku',
        'item_name',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class, 'box_id', 'box_id');
    }
}
