<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Guest extends Model
{
    use HasFactory;

    protected $table = 'guests';

    protected $primaryKey = 'guest_id';

    protected $keyType    = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'guest_id',
        'session_token',
        'ip_address',
        'status',
        'role',
        'subject',
        'availability',
        'expires_at',
    ];

    protected $casts = [
        'expires_at'   => 'datetime',
        'availability' => 'array',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    /**
     * Transient (not persisted). The unhashed raw token is set here so that
     * GuestController::create() can return it to the caller exactly once.
     * The DB column stores only the SHA-256 hash.
     */
    public ?string $rawToken = null;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->guest_id)) {
                $model->guest_id = (string) Str::uuid();
            }

            if (empty($model->session_token)) {
                // SECURITY: Generate 256-bit raw token and store only its SHA-256 hash.
                // The raw token is exposed to the caller ONCE via the `rawToken` transient
                // property and never stored in the DB — prevents session hijack via DB dump.
                $rawToken           = bin2hex(random_bytes(32));
                $model->rawToken    = $rawToken;           // transient, returned to caller
                $model->session_token = hash('sha256', $rawToken); // stored in DB
            }
        });
    }

    // ── Relationships ──────────────────────────────────────────────────────

    public function chatsAsGuest1()
    {
        return $this->hasMany(Chat::class, 'guest_id_1');
    }

    public function chatsAsGuest2()
    {
        return $this->hasMany(Chat::class, 'guest_id_2');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_guest_id');
    }

    public function reportsAsReporter()
    {
        return $this->hasMany(Report::class, 'reporter_guest_id');
    }

    public function reportsAsReported()
    {
        return $this->hasMany(Report::class, 'reported_guest_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('expires_at', '>', now());
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting')->where('expires_at', '>', now());
    }

    public function scopeBanned($query)
    {
        return $query->where('status', 'banned');
    }

    public function scopeIdle($query)
    {
        return $query->where('status', 'idle')->where('expires_at', '>', now());
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isBanned(): bool
    {
        return $this->status === 'banned';
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->expires_at
            && $this->expires_at->isFuture();
    }
}
