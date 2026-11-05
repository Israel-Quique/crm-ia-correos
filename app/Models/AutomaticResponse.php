<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AutomaticResponse extends Model { protected $fillable=['user_id','scope','type','key','name','template','trigger_phrases','active']; protected $casts=['active'=>'boolean','trigger_phrases'=>'array']; }
