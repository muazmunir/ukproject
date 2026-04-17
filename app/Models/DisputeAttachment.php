<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisputeAttachment extends Model
{
    protected $fillable = [
        'dispute_id','message_id',
        'disk','path','filename','mime','size',
    ];

    public function dispute() { return $this->belongsTo(Dispute::class); }
    public function message() { return $this->belongsTo(DisputeMessage::class, 'message_id'); }

    public function url(): string
    {
        return \Storage::disk($this->disk)->url($this->path);
    }
}
