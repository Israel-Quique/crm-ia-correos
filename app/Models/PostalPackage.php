<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PostalPackage extends Model { protected $fillable=['source','scope','external_id','tracking_number','sender','recipient','origin_phone','phone','cds_sender','cds_recipient','cds_phone','cds_synced_at','current_event','destination_branch','updated_at_api','raw_payload','last_synced_at']; protected $casts=['raw_payload'=>'array','updated_at_api'=>'datetime','last_synced_at'=>'datetime','cds_synced_at'=>'datetime']; public function events(){return $this->hasMany(PostalEvent::class);} public function latestEvent(){return $this->hasOne(PostalEvent::class)->ofMany(['event_at'=>'max','id'=>'max']);} }
