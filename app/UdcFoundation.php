<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
        
class UdcFoundation extends Eloquent{
    
    public $timestamps = false;
    
    protected $connection   =  'weCloudFoundation' ;
    
    protected $primaryKey = 'code';
    
    protected $collection   =   'udcs';
    
    public function values()
    {
        return $this->hasMany('App\UdcFoundation','udc_code');
    }
    
    public function udc(){
        return $this->belongsTo('App\UdcFoundation','udc_code');
    }
}