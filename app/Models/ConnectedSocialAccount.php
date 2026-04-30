<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ConnectedSocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'facebook_user_id',
        'platform',
        'page_id',
        'ig_business_id',
        'access_token',
        'token_expires_at',
        'webhook_features',
        'subscribed',
        'connected_at',
    ];

    protected $casts = [
        'webhook_features' => 'array',
        'subscribed' => 'boolean',
        'connected_at' => 'datetime',
        'token_expires_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function instagramMessages()
    {
        return $this->hasMany(InstagramMessage::class);
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    public function messengerMessages()
    {
        return $this->hasMany(MessengerMessage::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
