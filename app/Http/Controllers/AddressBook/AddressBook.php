<?php

/**
* Class of AddressBook , Super class that will provide all properties and methods for child class like
* Customer, Supplieer, ContactPoint, Employee, ...
*
* @since      Class available since Release 1.0.0
* @deprecated Class deprecated in Release 1.0.0
*/
namespace App\Http\Controllers\AddressBook;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;

class AddressBook extends AbstractResourceProvider {
            
    const FIELD_TENANT = "_tenant";
    
    private $tenantId;
    private $type;
    
    public function __construct($tenantId = null, $type = null) 
    {
        parent::__construct();
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
        if($type != null){
            $this->type = $type;
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
        return 'App\AddressBook';
    }
}