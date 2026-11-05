<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostalCampaignTemplate extends Model
{
    protected $fillable = ['name', 'template', 'scope', 'user_id'];
}
