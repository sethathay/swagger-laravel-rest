<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Version extends Eloquent
{
    public $timestamps = false;
    
    protected   $collection = "versions";
    
    public function program(){
        return $this->belongsTo("App\ObjLibrary","program_name");
    }
}