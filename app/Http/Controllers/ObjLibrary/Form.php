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

class Form extends AbstractResourceProvider{
    
    const FIELD_RECORD_TYPE = "record_type";
    const FIELD_PROGRAM = "program_name";
    const VALUE_RECORD_TYPE = "FORM";
    
    private $recordType;
    private $proId;
    private $schemaProgram = "TPROGRAMOBJECT";
    
    public function __construct($proId = null) {
        parent::__construct();
        $this->recordType = self::VALUE_RECORD_TYPE;
        if($proId != null){
            $this->proId = $proId;
        }
    }
    
    public function getRecordType(){
        return $this->recordType;
    }
    
    public function getProId(){
        return $this->proId;
    }
    
    public function model(){
        return 'App\ObjLibrary';
    }
    
    public function save($object) {
        $object->record_type = $this->getRecordType();
        return parent::save($object);
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
