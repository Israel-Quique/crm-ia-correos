<?php

namespace App\Models;
use App\Models\User;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';
    protected $fillable = ['provider_message_id', 'phone', 'contact_name', 'direction', 'message_type', 'message', 'status', 'raw_payload', 'received_at', 'auto_reply_status', 'read_at', 'source', 'source_id', 'sender_user_id', 'language_code'];
    protected $casts = ['raw_payload' => 'array', 'received_at' => 'datetime', 'read_at' => 'datetime'];
    public function sender() { return $this->belongsTo(User::class, 'sender_user_id'); }
}
