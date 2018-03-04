<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\ObjLibrary;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;
use App\Http\Controllers\Util\MongoUtil;

class ObjLibrary extends AbstractResourceProvider{
    
    const FIELD_RECORD_TYPE = "record_type";
    const FIELD_OBJECT_TYPE = "type.code";
    const VALUE_RECORD_TYPE = "OBJECT_LIBRARY";
    
    private $type;
    private $recordType;
    
    //Initiate constructor
    public function __construct($type = null){
        parent::__construct();
        $this->recordType = self::VALUE_RECORD_TYPE;
        if($type != null){
            $this->type = $type;
        }
    }
    
    public function getType(){
        return $this->type;
    }
    
    public function getRecordType(){
        return $this->recordType;
    }
    
    //Override abstract method model of abstract resource provider
    public function model(){
        return 'App\ObjLibrary';
    }
    
    public function save($object) {
        $object->record_type = $this->getRecordType();
        return parent::save($object);
    }
    /**
     * Default filter of resource object library
     * @date 13-Sept-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  string $str
     * @return string filter
     */
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