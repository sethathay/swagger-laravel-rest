<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class File extends Eloquent
{   
    protected $collection = 'files';
    
    public $timestamps = false;
}
