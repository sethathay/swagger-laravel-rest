<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\Project;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;
use App\Http\Controllers\ObjLibrary\Schema;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\Util\MongoUtil;

class Task extends AbstractResourceProvider {
    
    const FIELD_TENANT = "_tenant";
    const FIELD_RESOURCE = "resource";
    const FIELD_RESOURCE_ID = "resource_id";
    
    private $tenantId;
    private $resource;
    private $resourceId;
    
    public function __construct($tenantId = null, $resource = null, $resourceId = null) {
        parent::__construct();
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
        if($resource != null){
            $this->resource = $resource;
        }
        if($resourceId != null){
            $this->resourceId = $resourceId;
        }
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    public function getResourceName(){
        return $this->resource;
    }
    
    public function getResourceId(){
        return $this->resourceId;
    }
    
    public function model(){
        return 'App\Task';
    }
    
    public function getResource(){
        //Getting allowed resources
        $schemaObj = new Schema();
        $cObj = new CriteriaOption();
        $cObj->where('resource.plural', $this->getResourceName());
        $cObj->where('modules.task', true);
        $schemaObj->pushCriteria($cObj);
        $schemaObj->applyCriteria();
        $reObj = $schemaObj->readOne();
        if($reObj){
            //Validate resource
            $model = $reObj->crud['model'];
            $resourceObj = new $model;
            $criteria = new CriteriaOption();
            $criteria->where('_id', $this->getResourceId());
            $resourceObj->pushCriteria($criteria);
            $resourceObj->applyCriteria();
            $dataObj = $resourceObj->readOne();
            if($dataObj){
                return array(
                    "resource" => $dataObj,
                    "schema" => $reObj
                );
            }
        }
        return false;
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
                            "K" => "no",
                            "L" => "or",
                            "F" => [
                                [
                                    "K" => "no",
                                    "O" => "eq",
                                    "V" => $str
                                ]
                            ]
                        ]
                    ]
                ];
    }
}
