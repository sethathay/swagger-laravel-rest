<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\Udc;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;
use App\Http\Controllers\Udc\Udc;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\Util\MongoUtil;

class UdcValue extends AbstractResourceProvider {
    
    const FIELD_RECORD_TYPE = "record_type";
    const FIELD_TENANT = "_tenant";
    const FIELD_SYS_CODE = "sys_code.code";
    const FIELD_UDC = "udc_code";
    const VALUE_RECORD_TYPE = "UDC_VALUE";
    
    private $recordType;
    private $tenantId;
    private $sysCode;
    private $typeId;
    private $schemaUdc = "TUDCTYPE";
    
    public function __construct($tenantId = null, $sysCode = null, $typeId = null) {
        parent::__construct();
        $this->recordType = self::VALUE_RECORD_TYPE;
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
        if($sysCode != null){
            $this->sysCode = $sysCode;
        }
        if($typeId != null){
            $this->typeId = $typeId;
        }
    }
    
    public function getRecordType(){
        return $this->recordType;
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    public function getSysCode(){
        return $this->sysCode;
    }
    
    public function getTypeId(){
        return $this->typeId;
    }
    
    public function model() {
        return 'App\Udc';
    }
    
    public function save($object) {
        $object->record_type = $this->getRecordType();
        return parent::save($object);
    }
    
    public static function getUdcValue($tenant, $sysCode, $udc, $value, $includedId = false){
        $criteria = new CriteriaOption();
        $me = new UdcValue();
        $schema = new SchemaRender("TUDCVALUE");
        $criteria->where(UdcValue::FIELD_RECORD_TYPE, $me->getRecordType());
        $criteria->where(UdcValue::FIELD_TENANT, $tenant);
        $criteria->where(UdcValue::FIELD_SYS_CODE, $sysCode);
        $criteria->where(UdcValue::FIELD_UDC, $udc);
        $criteria->whereRaw($me->getDefaultFilter($value), $schema->getAllFields(), "");
        $me->pushCriteria($criteria);
        $me->applyCriteria();
        $result = $me->readOne();
        if($result){
            if($includedId){
                return array(
                    "id" => $result->id,
                    "code" => $result->code,
                    "label" => $result->label
                );
            }else{
                return array(
                    "code" => $result->code,
                    "label" => $result->label
                );
            }
        }
        return [];
    }
    
    public function getUdc($tenantId, $sysCode, $typeId){
        $criteria = new CriteriaOption();
        $udcObject = new Udc($tenantId,$sysCode);
        $udcSchema = new SchemaRender($this->schemaUdc);
        $criteria->where(Udc::FIELD_RECORD_TYPE, $udcObject->getRecordType());
        $criteria->where(Udc::FIELD_TENANT, $udcObject->getTenantId());
        $criteria->where(Udc::FIELD_SYS_CODE, $udcObject->getSysCode());
        $criteria->whereRaw($udcObject->getDefaultFilter($typeId), $udcSchema->getAllFields(), "");
        $udcObject->pushCriteria($criteria);
        $udcObject->applyCriteria();
        return $udcObject->readOne();
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
