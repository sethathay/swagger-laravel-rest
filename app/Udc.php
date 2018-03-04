<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Udc extends Eloquent{
    
    public $timestamps = false;

    protected $primaryKey = 'code';

    protected $collection   =   'udcs';
    
    public function values(){
        return $this->hasMany('App\Udc','udc_code');
    }
    
    public function udc(){
        return $this->belongsTo('App\Udc','udc_code');
    }
}