<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class PostLibrary extends Eloquent
{
    public $timestamps = false;
    
    protected $collection = "posts";
    
    public function replies(){
        return $this->embedsMany("App\PostLibrary");
    }
}