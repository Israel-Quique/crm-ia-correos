<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppDecisionTreeSession extends Model
{
    protected $table = 'whatsapp_decision_tree_sessions';

    protected $fillable = ['phone', 'flow_key', 'step', 'context', 'expires_at'];

    protected $casts = [
        'context' => 'array',
        'expires_at' => 'datetime',
    ];
}
