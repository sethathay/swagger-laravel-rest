<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Menu extends Eloquent
{
    public $timestamps = false;
    
    protected $primaryKey = 'code';

    protected   $collection = "menus";
    
    public function items(){
        return $this->hasMany("App\Menu","menu_code");
    }
    
    public function menu(){
        return $this->belongsTo("App\Menu","menu_code");
    }
}
