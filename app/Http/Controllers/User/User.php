<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;
use App\Http\Controllers\Tenant\Tenant;

class User extends AbstractResourceProvider{
    
    public function __construct() {
        parent::__construct();
    }
    
    //Override abstract method model of abstract resource provider
    public function model(){
        return 'App\User';
    }
    
    /**
     * Get user is tenant's user or customer's user and list customer id in the specified environment 
     * @date 06-Dec-2016
     * @author Chomnan Im <chomnan.im@workevolve.com> 
     * @param array $environments Array of environment
     * @return array User is and list of customer id
     */
    public static function getUserType($environments){
        $data = $listAddressBookId = array();    
        $userType = Tenant::VALUE_EXTERNAL; 
        foreach ($environments as $env) {
            if($env['user_type']['code'] == Tenant::VALUE_EMPLOYEE){
                $userType = Tenant::VALUE_EMPLOYEE;
                break;
            }   
        }
        if($userType == Tenant::VALUE_EXTERNAL){
            foreach ($environments as $env){
                $listAddressBookId[] = $env['addressbook_id'];
            }
        }
        $data['type'] = $userType;
        $data['listAddressBookId'] = $listAddressBookId;
        return  $data;
    }
    /**
     * Checking error json data type
     * @date 17-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param string $object
     * @return boolean
     */
    public static function isError($object){
        if(gettype($object) == 'object' && get_class($object) == "Illuminate\Http\JsonResponse"){ return true; }
        return false;
    }
}
