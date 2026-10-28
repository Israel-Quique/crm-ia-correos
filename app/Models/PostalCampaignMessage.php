<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PostalCampaignMessage extends Model { protected $fillable=['postal_campaign_id','postal_package_id','phone','message','status','provider_message_id','error_message','sent_at']; protected $casts=['sent_at'=>'datetime']; public function package(){return $this->belongsTo(PostalPackage::class,'postal_package_id');} public function campaign(){return $this->belongsTo(PostalCampaign::class,'postal_campaign_id');} }
