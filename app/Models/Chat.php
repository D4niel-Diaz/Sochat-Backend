<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $primaryKey = 'chat_id';

    protected $fillable = [
        'guest_id_1',
        'guest_id_2',
        'started_at',
        'ended_at',
        'status',
        'ended_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function guest1()
    {
        return $this->belongsTo(Guest::class, 'guest_id_1');
    }

    public function guest2()
    {
        return $this->belongsTo(Guest::class, 'guest_id_2');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->whereNull('ended_at');
    }

    public function scopeEnded($query)
    {
        return $query->whereNotNull('ended_at');
    }

    public function isParticipant(string $guestId): bool
    {
        return $this->guest_id_1 === $guestId || $this->guest_id_2 === $guestId;
    }

    public function getPartnerId(string $guestId): ?string
    {
        if ($this->guest_id_1 === $guestId) {
            return $this->guest_id_2;
        }
        if ($this->guest_id_2 === $guestId) {
            return $this->guest_id_1;
        }
        return null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && is_null($this->ended_at);
    }

    public function end(string $endedBy): void
    {
        $this->update([
            'ended_at' => now(),
            'ended_by' => $endedBy,
            'status' => 'ended',
        ]);
    }
}
