<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;

class Relationship extends AbstractResourceProvider{
    
    private $tenantId;
    
    public function __construct($tenantId = null) {
        parent::__construct();
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    //Override abstract method model of abstract resource provider
    public function model(){
        return 'App\Relationship';
    }
    
    public function save($object) {
        $object->_tenant = $this->getTenantId();
        return parent::save($object);
    }
}
