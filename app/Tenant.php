<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
use Laravel\Cashier\Billable;

class Tenant extends Eloquent
{
    use Billable;
    
    public $timestamps = false;
    
    protected $connection   =   'weCloudFoundation';
    
    protected $collection = 'tenants';
    
    protected $dates = ['trial_ends_at'];
    
    protected $fillable = [
        'trial_ends_at',
    ];
}
