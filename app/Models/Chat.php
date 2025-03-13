<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\CryptoService;
use Illuminate\Support\Facades\Auth;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user1_id',
        'user2_id',
        'encrypted_chat_key_user1',
        'encrypted_chat_key_user2',
    ];

    /**
     * Get the first user that owns the chat.
     */
    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    /**
     * Get the second user that owns the chat.
     */
    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    /**
     * Get all messages for this chat.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
    
    /**
     * Get the other user in the chat.
     */
    public function getOtherUser(int $userId): User
    {
        return $userId === $this->user1_id ? $this->user2 : $this->user1;
    }
    
    /**
     * Get the encrypted chat key for the specified user
     *
     * @param int $userId
     * @return string|null
     */
    public function getEncryptedChatKeyForUser(int $userId): ?string
    {
        if ($this->user1_id === $userId) {
            return $this->encrypted_chat_key_user1;
        } elseif ($this->user2_id === $userId) {
            return $this->encrypted_chat_key_user2;
        }
        
        return null;
    }
}
