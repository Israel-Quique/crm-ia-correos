<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PostalEvent extends Model { protected $fillable=['postal_package_id','source_event','event_name','event_at','raw_payload']; protected $casts=['raw_payload'=>'array','event_at'=>'datetime']; public function package(){return $this->belongsTo(PostalPackage::class,'postal_package_id');} }
