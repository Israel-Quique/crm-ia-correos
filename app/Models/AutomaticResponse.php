<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AutomaticResponse extends Model { protected $fillable=['user_id','type','key','name','template','active']; protected $casts=['active'=>'boolean']; }
