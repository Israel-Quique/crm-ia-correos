<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AiPromptSection extends Model { protected $fillable=['slug','name','content','active']; protected $casts=['active'=>'boolean']; }
