<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Revision extends Eloquent
{   
    protected $collection = 'revisions';
    
    public $timestamps = false;
}
