<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Token extends Eloquent
{
    protected $connection   =   'weCloudFoundation';
    
    protected $collection = 'tokens';
    
    public $timestamps = false;
}
