<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppConversationSetting extends Model
{
    protected $table = 'whatsapp_conversation_settings';
    protected $fillable = ['phone', 'mode', 'updated_by'];
}
