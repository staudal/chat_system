<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Broadcasting\BroadcastableEvent;
use Illuminate\Database\Eloquent\BroadcastsEvents;

class Message extends Model
{
    use HasFactory, BroadcastsEvents;

    protected $fillable = [
        'chat_id',
        'sender_id',
        'encrypted_content',
        'iv',
        'is_read',
    ];

    /**
     * Get the chat that owns the message.
     */
    public function chat(): BelongsTo
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * Get the user that sent the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the channels that model events should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(string $event): array
    {
        return [
            new \Illuminate\Broadcasting\PrivateChannel('chat.' . $this->chat_id),
        ];
    }
    
    /**
     * The data to broadcast with the event.
     */
    public function broadcastWith(string $event): array
    {
        return [
            'id' => $this->id,
            'chat_id' => $this->chat_id,
            'sender_id' => $this->sender_id,
            'sender_name' => $this->sender->name,
            'encrypted_content' => $this->encrypted_content,
            'iv' => $this->iv,
            'created_at' => $this->created_at->format('M d, H:i'),
        ];
    }
    
    /**
     * Get the event name that should be broadcast with this model's events.
     */
    public function broadcastAs(string $event): string
    {
        return 'message.created';
    }
}
