<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Project extends Eloquent
{
    public $timestamps = false;
    
    protected   $primaryKey = 'no';
    
    protected $collection = 'projects';    

}