<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'description',
        'value',
        'created_at',
        'user_id',
    ];

    /**
     * Get the user that owns the Transaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}