<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class RolePermission extends Eloquent{
    
    public $timestamps = false;
    
    protected   $collection = "role_permissions";
    
}
