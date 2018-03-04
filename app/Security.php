<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Security extends Eloquent{
    
    public $timestamps = false;
    
    protected   $collection = "securities";
    
}
