<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PostalCampaign extends Model { use SoftDeletes; protected $fillable=['user_id','name','campaign_type','filters','events','template','interval_seconds','test_mode','status','total','sent','failed','pending']; protected $casts=['filters'=>'array','events'=>'array','test_mode'=>'boolean']; public function messages(){return $this->hasMany(PostalCampaignMessage::class);} }
