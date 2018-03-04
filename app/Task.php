<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Task extends Eloquent {
    
    public $timestamps = false;
    
    protected   $collection = "tasks";

}
