<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\ACL\Security;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;
use App\Http\Controllers\Util\MongoUtil;

class Group extends AbstractResourceProvider{
    
    const FIELD_RECORD_TYPE = "record_type";
    const FIELD_TENANT = "_tenant";
    const VALUE_RECORD_TYPE = "GROUP_SECURITY";
    
    private $recordType;
    private $tenantId;
    
    //Initiate constructor
    public function __construct($tenantId = null){
        parent::__construct();
        $this->recordType = self::VALUE_RECORD_TYPE;
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
    }
    
    public function getRecordType(){
        return $this->recordType;
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    public function model(){
        return 'App\Security';
    }
    
    public function save($object) {
        $object->record_type = $this->getRecordType();
        return parent::save($object);
    }
    
    public function getDefaultFilter($str){
        return  [
                    "L" => "or",
                    "F" => [
                        [
                            "K" => "id",
                            "L" => "or",
                            "F" => [
                                [
                                    "K" => "id",
                                    "O" => "eq",
                                    "V" => MongoUtil::getObjectID($str)
                                ]
                            ]
                        ],
                        [
                            "K" => "name",
                            "L" => "or",
                            "F" => [
                                [
                                    "K" => "name",
                                    "O" => "eq",
                                    "V" => $str
                                ]
                            ]
                        ]
                    ]
                ];
    }
}
