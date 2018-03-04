<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\PostLibrary;

use App\Http\Controllers\ObjLibrary\Schema;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;

class Rate extends PostLibrary{
    
    private $resource;
    private $resourceId;
    
    public function __construct($tenantId, $resource, $resourceId){
        $rateType = 'RT';
        parent::__construct($tenantId, $rateType);
        if($resource != null){
            $this->resource = $resource;
        }
        if($resourceId != null){
            $this->resourceId = $resourceId;
        }
    }
    
    public function getResourceName(){
        return $this->resource;
    }
    
    public function getResourceId(){
        return $this->resourceId;
    }
    
    public function getResource(){
        //Getting allowed resources
        $schemaObj = new Schema();
        $cObj = new CriteriaOption();
        $cObj->where('resource.plural', $this->getResourceName());
        $cObj->where('modules.rating', true);
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
                return $dataObj;
            }
        }
        return false;
    }
}
