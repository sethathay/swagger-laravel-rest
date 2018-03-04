<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class ObjLibrary extends Eloquent
{
    public $timestamps = false;
    
    protected   $primaryKey = 'name';
    
    protected   $collection = "obj_libraries";
    
    public function versions(){
       return $this->hasMany("App\Version","program_name");
    }
    //Object library type "program" has many "forms"
    public function forms(){
        return $this->hasMany("App\ObjLibrary","program_name");
    }
    //Object library "form" belong to "program"
    public function program(){
        return $this->belongsTo("App\ObjLibrary","program_name");
    }
}
