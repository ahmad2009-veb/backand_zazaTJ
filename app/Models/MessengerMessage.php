<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessengerMessage extends Model
{
    use HasFactory;

    protected $table = 'messenger_messages';

    protected $fillable = [
        'connected_social_account_id',
        'sender_id',
        'recipient_id',
        'message',
        'media_type', // image, video, audio, document
        'media_url',
        'media_id', // Facebook attachment ID
        'direction', // in = входящее, out = исходящее
        'sent_at',
        'is_seen',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function connectedSocialAccount()
    {
        return $this->belongsTo(ConnectedSocialAccount::class);
    }
}
