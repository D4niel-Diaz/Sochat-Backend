<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $primaryKey = 'message_id';

    protected $fillable = ['chat_id', 'sender_guest_id', 'content', 'is_flagged'];

    protected $casts = [
        'created_at' => 'datetime',
        'is_flagged' => 'boolean',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender()
    {
        return $this->belongsTo(Guest::class, 'sender_guest_id');
    }

    public function scopeNotFlagged($query)
    {
        return $query->where('is_flagged', false);
    }

    public function scopeForChat($query, int $chatId)
    {
        return $query->where('chat_id', $chatId);
    }
}
