<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\ObjLibrary;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;
use App\Http\Controllers\ObjLibrary\Program;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\Util\MongoUtil;

class Version extends AbstractResourceProvider{
    
    const FIELD_TENANT = "_tenant";
    const FIELD_PROGRAM = "program_name";
    
    private $tenantId;
    private $proId;
    private $schemaProgram = "TPROGRAMOBJECT";
    
    public function __construct($tenantId = null,$proId = null) {
        parent::__construct();
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
        if($proId != null){
            $this->proId = $proId;
        }
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    public function getProId(){
        return $this->proId;
    }
    
    //Override abstract method model of abstract resource provider
    public function model(){
        return 'App\Version';
    }
    
    public function getProgram($proId){
        //Get program from object library based on program id
        $criteria = new CriteriaOption();
        $programObject = new Program();
        $programSchema = new SchemaRender($this->schemaProgram);
        $criteria->where(Program::FIELD_RECORD_TYPE, $programObject->getRecordType());
        $criteria->whereRaw($programObject->getDefaultFilter($proId), $programSchema->getAllFields(),"");
        $programObject->pushCriteria($criteria);
        $programObject->applyCriteria();
        return $programObject->readOne();
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