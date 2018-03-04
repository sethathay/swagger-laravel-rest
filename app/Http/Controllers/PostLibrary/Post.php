<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\PostLibrary;

use App\Http\Controllers\ObjLibrary\Schema;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;

class Post extends PostLibrary{
    
    private $resource;
    private $resourceId;
    
    public function __construct($tenantId, $resource, $resourceId){
        $postType = 'PT';
        parent::__construct($tenantId, $postType);
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
        $cObj->where('modules.post', true);
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
    
    public function getPost($id){
        $obj = new Post($this->getTenantId(),
                        $this->getResourceName(),
                        $this->getResourceId());
        $ct = new CriteriaOption();
        $ct->where(self::FIELD_TENANT, $this->getTenantId());
        $ct->where('type.code', $this->getType());
        $ct->where(self::FIELD_RESOURCE, $this->getResourceName());
        $ct->where(self::FIELD_RESOURCE_ID, $this->getResourceId());
        $ct->where('_id', $id);
        $obj->pushCriteria($ct);
        $obj->applyCriteria();
        return $obj->readOne();
    }
}
