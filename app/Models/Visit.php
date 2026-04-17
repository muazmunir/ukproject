<?php

// app/Models/Visit.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    /**
     * After db:split-multi, `visits` is a physical table on pii_db; auth_db only holds a view that
     * often breaks on shared hosts unless the auth user is granted SELECT on pii_db.
     */
    public function getConnectionName(): ?string
    {
        return config('database.split_multi.topology') === 'multi' ? 'pii_db' : parent::getConnectionName();
    }

    protected $fillable = [
        'visitor_id',
        'user_id',
        'ip',
        'user_agent',
        'path',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
