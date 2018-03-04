<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Relationship extends Eloquent
{   
    protected $collection = 'relationships';
    
    public $timestamps = false;
}
