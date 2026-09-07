<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'site_name',
        'email',
        'phone',
        'logo_path',
        'favicon_path',
        'description',
        'address',
        'city',
        'state',
        'country',
        'zip',
        'facebook_url',
        'instagram_url',
        'twitter_url',
        'youtube_url',
        'linkedin_url',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_email',
        'smtp_from_name',
    ];

    protected $casts = [
        'smtp_password' => 'encrypted',
    ];
}
