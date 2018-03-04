<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;

class Tenant extends AbstractResourceProvider{
    
    const VALUE_SYS_CODE = 'F00';
    const TYPE_ENV = 'WE';
    const VALUE_ENV_PROD = 'PROD';
    const TYPE_APP = 'WA';
    const VALUE_APP_PROJ = 'PROJ';
    const TYPE_USR = 'UT';
    const VALUE_EMPLOYEE = 'EMP';
    const VALUE_EXTERNAL = 'EXT';
    const TYPE_STATUS = 'EN';
    const VALUE_USR_ACTIVE = '01';
    const VALUE_USR_PENDING = '00';
    const VALUE_CONT_EMAIL = '01';
    
    //Call parent constructor
    public function __construct(){
        parent::__construct();
    }
    //Override abstract method model of abstract resource provider
    public function model(){
        return 'App\Tenant';
    }
}