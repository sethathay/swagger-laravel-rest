<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\Udc;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;
use App\Http\Controllers\Util\MongoUtil;

class UdcFoundation extends AbstractResourceProvider{
    
    const FIELD_RECORD_TYPE = "record_type";
    const FIELD_SYS_CODE = "sys_code.code";
    const VALUE_RECORD_TYPE = "UDC";
    
    private $recordType;
    private $sysCode;
    
    public function __construct($sysCode = null){
        parent::__construct();
        $this->recordType = self::VALUE_RECORD_TYPE;
        if($sysCode != null){
            $this->sysCode = $sysCode;
        }
    }
    
    public function getRecordType(){
        return $this->recordType;
    }
    
    public function getSysCode(){
        return $this->sysCode;
    }
    
    //Override abstract method model of abstract resource provider
    public function model(){
        return 'App\UdcFoundation';
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