<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    
    /**
     * Get the key pair associated with the user.
     */
    public function keyPair(): HasOne
    {
        return $this->hasOne(UserKeyPair::class);
    }
    
    /**
     * Get all chats where this user is user1.
     */
    public function chatsAsUser1(): HasMany
    {
        return $this->hasMany(Chat::class, 'user1_id');
    }
    
    /**
     * Get all chats where this user is user2.
     */
    public function chatsAsUser2(): HasMany
    {
        return $this->hasMany(Chat::class, 'user2_id');
    }
    
    /**
     * Get all messages sent by this user.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
    
    /**
     * Get all chats for the user.
     */
    public function chats()
    {
        return $this->chatsAsUser1->merge($this->chatsAsUser2);
    }
}
