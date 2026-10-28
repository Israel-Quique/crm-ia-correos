<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PostalCampaign extends Model { protected $fillable=['user_id','name','filters','events','template','interval_seconds','test_mode','status','total','sent','failed','pending']; protected $casts=['filters'=>'array','events'=>'array','test_mode'=>'boolean']; public function messages(){return $this->hasMany(PostalCampaignMessage::class);} }
