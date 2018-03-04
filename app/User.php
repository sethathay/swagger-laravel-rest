<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class User extends Eloquent
{
    public $timestamps = false;
    
    protected $connection   =   'weCloudFoundation';
    
    protected $collection = 'users';
    
    public function tenants(){
        return $this->embedsMany('App\Tenant');
    }
}
