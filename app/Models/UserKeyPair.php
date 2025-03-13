<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserKeyPair extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'public_key',
        'encrypted_private_key',
        'key_pair_salt',
    ];

    /**
     * Get the user that owns the key pair.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
