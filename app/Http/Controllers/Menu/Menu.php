<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;
use App\Http\Controllers\Util\MongoUtil;

class Menu extends AbstractResourceProvider{
    
    const FIELD_RECORD_TYPE = "record_type";
    const FIELD_TENANT = "_tenant";
    const VALUE_RECORD_TYPE = "MENU";
    
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
    
    //Override abstract method model of abstract resource provider
    public function model(){
        return 'App\Menu';
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
                            "K" => "code",
                            "L" => "or",
                            "F" => [
                                [
                                    "K" => "code",
                                    "O" => "eq",
                                    "V" => $str
                                ]
                            ]
                        ]
                    ]
                ];
    }
}
