<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\PostLibrary;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;

class PostLibrary extends AbstractResourceProvider{
    
    const FIELD_TENANT = "_tenant";
    const FIELD_RESOURCE = "resource";
    const FIELD_RESOURCE_ID = "resource_id";
    
    private $tenantId;
    private $type;
    
    //Initiate constructor
    public function __construct($tenantId = null, $type = null){
        parent::__construct();
        if($type != null){
            $this->type = $type;
        }
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
    }
    
    public function getType(){
        return $this->type;
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    //Override abstract method model of abstract resource provider
    public function model(){
        return 'App\PostLibrary';
    }

}