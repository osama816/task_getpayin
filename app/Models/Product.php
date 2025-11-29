<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

      protected $fillable = [
        'name',
        'price',
        'stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function holds(): HasMany
    {
        return $this->hasMany(Hold::class);
    }

    public function activeHolds(): HasMany
    {
        return $this->hasMany(Hold::class)->where('status', 'reserved');
    }
}
