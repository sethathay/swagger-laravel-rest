<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\Revision;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;

class Event extends AbstractResourceProvider{
    
    const VALUE_EVENT_RECORD_TYPE = "EVENT";
    const FIELD_RECORD_TYPE = "record_type";
    const FIELD_TENANT = "_tenant";
    const FIELD_EVENT = "event_id";
    
    private $tenantId;
    private $recordType;
    
    public function __construct($tenantId = null) {
        parent::__construct();
        $this->recordType = self::VALUE_EVENT_RECORD_TYPE;
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    public function getRecordType(){
        return $this->recordType;
    }
    
    //Override abstract method model of abstract resource provider
    public function model(){
        return 'App\Revision';
    }
    
    public function save($object) {
        $object->record_type = $this->getRecordType();
        return parent::save($object);
    }
}
