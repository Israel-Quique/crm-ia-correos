<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ApiRecord extends Model
{
    protected $fillable = ['source', 'external_id', 'status', 'payload', 'api_updated_at', 'last_synced_at'];
    protected $casts = ['payload' => 'array', 'api_updated_at' => 'datetime', 'last_synced_at' => 'datetime'];
}
