<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $primaryKey = 'report_id';

    protected $fillable = ['chat_id', 'reporter_guest_id', 'reported_guest_id', 'reason', 'ip_address', 'status'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function reporter()
    {
        return $this->belongsTo(Guest::class, 'reporter_guest_id');
    }

    public function reported()
    {
        return $this->belongsTo(Guest::class, 'reported_guest_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }
}
