<?php

/**
* Class of Programs that extend class ObjectLibraries
*
* 
* 
* LICENSE: Some license information
*
* @category   PHP
* @package    
* @copyright  Copyright (c) 2016 WorkEvolve
* @license    http://workevolve.com/license   BSD License
* @version    1.0.0
* @since      File available since Release 1.0.0

*/
namespace App\Http\Controllers\AddressBook;

use App\Http\Controllers\Tenant\Tenant;

class Customer extends AddressBook{
    
    public function __construct($tenantId) 
    {
        $customerType = 'C';
        parent::__construct($tenantId,$customerType);
    }
    
    /**
     * Get list id of user customer
     * @date 03-sep-2016
     * @author Kosal Tim <kosal.tim@workevolve.com>
     * @param object $user is object of user get from strompath
     * @param string $tenantId is value of tenant id
     * @return array List id of customer
     */
    public static function getCurrentCustomerIdOfUser($tenantInUser)
    {
        //get array of weCloudProjectTracking
        foreach($tenantInUser['apps'] as $app)
        {
            if($app['app']['code']== Tenant::VALUE_APP_PROJ)
            {
                $currentAppOfTenant = $app; break;
            }
        }
        
        $cusList = array();
        
        foreach ($app['envs'] as $env)
        {
            if($env['user_type']['code'] == Tenant::VALUE_EMPLOYEE) return array();
            
            if($env['env']['code'] != Tenant::VALUE_ENV_PROD)  continue;
            
            if($env['status']['code'] != Tenant::VALUE_USR_ACTIVE) continue;
            
            array_push($cusList, $env['_external']);  
        }        
        return $cusList; 
    }
}
