<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\Relationship;

use App\Http\Controllers\Relationship\Relationship;
use App\Http\Controllers\ObjLibrary\Schema;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\Udc\UdcValue;
use Illuminate\Http\Request;
use App\Http\Controllers\Udc\UdcValueFoundation;

class RelationshipManager {
    
    private $tenantId;
    private $schemaRelationship = "TRELATIONSHIP";
    
    public function __construct($tenantId = null) {
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    private function getUdc($element, $data){
        if($element["config"]["custom"]["use_shared_data"]){
            $udcValue = UdcValueFoundation::getUdcValue(
                                            $element["config"]["custom"]["sys_code"],
                                            $element["config"]["custom"]["udc_type"],
                                            $data,
                                            true
                                        );
        }else{
            $udcValue = UdcValue::getUdcValue($this->getTenantId(),
                                            $element["config"]["custom"]["sys_code"],
                                            $element["config"]["custom"]["udc_type"],
                                            $data,
                                            true
                                        );
        }
        return $udcValue;
    }
    
    private function getLookup($element, $data){
        $valueParams = array();
        $datasource = $element["config"]["custom"]["datasource"];
        $obj = new Schema();
        $criteria = new CriteriaOption();
        $criteria->where('datasource', $datasource);
        $obj->pushCriteria($criteria);
        $obj->applyCriteria();
        $schemaObj = $obj->readOne();
        $controller = $schemaObj->crud['controller'];
        $function = $schemaObj->crud["o"]['function'];
        $params = $schemaObj->crud["o"]['params'];
        $controllerObj = new $controller(new Request());
        foreach($params as $param){
            $key = $param['key'];
            //For $data type is udc
            if(gettype($data[$key]) == "array"){
                if(isset($data[$key]['code']) && isset($data[$key]['label'])){
                    array_push($valueParams, $data[$key]['code']);
                }else{
                    array_push($valueParams, $data[$key]);
                }
            }else{
                array_push($valueParams, $data[$key]);
            }
        }
        $lookupObj = $controllerObj->callAction($function, $valueParams);
        return array(
            'resource' => $schemaObj->resource["plural"],
            'id' => end((explode("/", $lookupObj->getData()->href)))
        );
    }
    
    public function insertData($data, $user){
        $dataRelation = [];
        $relationObj = new Relationship($this->getTenantId());
        $relationSchema = new SchemaRender($this->schemaRelationship);
        foreach($data as $item){
            $itemObj = $relationSchema->renderInput($item, $relationObj->getModel(), $user, "C");
            $itemObj->_tenant = $relationObj->getTenantId();
            $dataRelation[] = $itemObj->toArray();
        }
        $relationObj->saveMany($dataRelation);
    }
    
    private function updateData($data, $obj, $user){
        $newRelationObj = new Relationship($this->getTenantId());
        $relationSchema = new SchemaRender($this->schemaRelationship);
        $dataRelation = $relationSchema->renderInput($data, $obj, $user, "U");
        $newRelationObj->save($dataRelation);
    }
    
    public function getRelationship($targetResource, $targetResourceId, $targetField, $originResource, $originResourceId, $originField){
        return array(
            'targetResource' => $targetResource,
            'targetResourceId' => $targetResourceId,
            'targetField' => $targetField,
            'originResource' => $originResource,
            'originResourceId' => $originResourceId,
            'originField' => $originField 
        );
    }
    
    private function isRelationshipExist($originResource, $originField, $originId, $targetResource, $targetField, $targetId){
        $relationObj = new Relationship($this->getTenantId());
        $criteria = new CriteriaOption();
        $criteria->where("_tenant", $relationObj->getTenantId());
        $criteria->where("origin_resource", $originResource);
        $criteria->where("origin_field", $originField);
        $criteria->where("origin_resource_id", $originId);
        $criteria->where("target_resource", $targetResource);
        $criteria->where("target_field", $targetField);
        $criteria->where("target_resource_id", $targetId);
        $relationObj->pushCriteria($criteria);
        $relationObj->applyCriteria();
        $oldRelationship = $relationObj->readOne();
        if($oldRelationship){
            return $oldRelationship;
        }else{
            return false;
        }
    }
    
    public function removeRelationship($originResource, $originField, $originId, $targetResource, $targetField, $targetId){
        $relationObj = new Relationship($this->getTenantId());
        $criteria = new CriteriaOption();
        $criteria->where("_tenant", $relationObj->getTenantId());
        $criteria->where("origin_resource", $originResource);
        $criteria->where("origin_field", $originField);
        $criteria->where("origin_resource_id", $originId);
        $criteria->where("target_resource", $targetResource);
        $criteria->where("target_field", $targetField);
        $criteria->where("target_resource_id", $targetId);
        $relationObj->pushCriteria($criteria);
        $relationObj->applyCriteria();
        $relationObj->remove();
    }
    
    public function save($fields, $data, $targetObj, $targetResource, $user){
        $relationData = [];
        if($data){
            if(isset($fields) && $fields != ""){
                foreach($fields as $element){
                    if(array_key_exists($element["external_id"], $data)){
                        //Checking edit type of each schema json
                        switch($element["edit_type"]){
                            case "weUdc" :
                                if($element["config"]["custom"]["dropdown_type"] == "default" ||
                                    $element["config"]["custom"]["dropdown_type"] == "filterable"){
                                    //Multiple selection
                                    if($element["config"]["custom"]["multiple"]){

                                    }
                                    //Single selection
                                    else{
                                        $udcValue = $this->getUdc($element, $data[$element["external_id"]]);
                                        $relationData[] = $this->getRelationship(
                                                            $targetResource, 
                                                            $targetObj->_id, 
                                                            $element["external_id"], 
                                                            'udcs', 
                                                            $udcValue["id"], 
                                                            'code');
                                    }
                                }
                                break;
                            case "weLookup":
                                if($element["config"]["custom"]["multiple"]){
                                    //Loop to get each lookup value
                                    $lookupData = $data;
                                    foreach($data[$element["external_id"]] as $ctrlLookup){
                                        $lookupData[$element["external_id"]] = $ctrlLookup;
                                        $lookup = $this->getLookup($element, $lookupData);
                                        $relationData[] = $this->getRelationship(
                                                        $targetResource, 
                                                        $targetObj->_id, 
                                                        $element["external_id"], 
                                                        $lookup['resource'], 
                                                        $lookup['id'], 
                                                        '_id');
                                    }
                                }else{
                                    $lookup = $this->getLookup($element, $data);
                                    $relationData[] = $this->getRelationship(
                                                        $targetResource, 
                                                        $targetObj->_id, 
                                                        $element["external_id"], 
                                                        $lookup['resource'], 
                                                        $lookup['id'], 
                                                        '_id');
                                }
                                break;
                            default:
                                break;
                        }
                    }
                }
                $this->insertData($relationData, $user);
            }
        }else{
            return ErrorHelper::getRequestBodyError();
        }
    }
    
    public function update($fields, $data, $oldTargetObj, $newTargetObj, $targetResource, $user){
        if($data){
            if(isset($fields) && $fields != ""){
                foreach($fields as $element){
                    if(array_key_exists($element["external_id"], $data)){
                        //Checking edit type of each schema json
                        switch($element["edit_type"]){
                            case "weUdc" :
                                if($element["config"]["custom"]["dropdown_type"] == "default" ||
                                    $element["config"]["custom"]["dropdown_type"] == "filterable"){
                                    //Multiple selection
                                    if($element["config"]["custom"]["multiple"]){

                                    }
                                    //Single selection
                                    else{
                                        $udcValue = $this->getUdc($element, $data[$element["external_id"]]);
                                        $relationData = $this->getRelationship(
                                                            $targetResource, 
                                                            $newTargetObj->_id, 
                                                            $element["external_id"], 
                                                            'udcs', 
                                                            $udcValue["id"], 
                                                            'code');
                                        $f = $oldTargetObj[$element["external_id"]];
                                        if(isset($f)){
                                            $udc = $this->getUdc($element, $f["code"]);
                                            $oldRelationship = $this->isRelationshipExist(
                                                                    "udcs", 
                                                                    "code", 
                                                                    $udc["id"], 
                                                                    $targetResource, 
                                                                    $element["external_id"], 
                                                                    $newTargetObj->_id);
                                            if($oldRelationship){
                                                $this->updateData($relationData, $oldRelationship, $user);
                                            }else{
                                                $relData[] = $relationData;
                                                $this->insertData($relData, $user);
                                            }
                                        }else{
                                            $relData[] = $relationData;
                                            $this->insertData($relData, $user);
                                        }
                                    }
                                }
                                break;
                            case "weLookup":
                                $lookup = $this->getLookup($element, $data);
                                $relationData = $this->getRelationship(
                                                    $targetResource, 
                                                    $newTargetObj->_id, 
                                                    $element["external_id"], 
                                                    $lookup['resource'], 
                                                    $lookup['id'], 
                                                    '_id');
                                $lookupValue = $this->getLookup($element, $oldTargetObj);
                                $oldRelationship = $this->isRelationshipExist(
                                                        $lookupValue['resource'], 
                                                        "_id", 
                                                        $lookupValue["id"], 
                                                        $targetResource, 
                                                        $element["external_id"], 
                                                        $newTargetObj->_id);
                                if($oldRelationship){
                                    $this->updateData($relationData, $oldRelationship, $user);
                                }else{
                                    $relData[] = $relationData;
                                    $this->insertData($relData, $user);
                                }
                                break;
                            default:
                                break;
                        }
                    }
                }
            }
        }else{
            return ErrorHelper::getRequestBodyError();
        }
    }
    
    public function remove($fields, $targetObj, $targetResource){
        if(isset($fields) && $fields != ""){
            foreach($fields as $element){
                //Checking edit type of each schema json
                switch($element["edit_type"]){
                    case "weUdc" :
                        if($element["config"]["custom"]["dropdown_type"] == "default" ||
                            $element["config"]["custom"]["dropdown_type"] == "filterable"){
                            //Multiple selection
                            if($element["config"]["custom"]["multiple"]){

                            }
                            //Single selection
                            else{
                                $f = $targetObj[$element["external_id"]];
                                if(isset($f)){
                                    $udc = $this->getUdc($element, $f["code"]);
                                    $this->removeRelationship(
                                            "udcs", 
                                            "code", 
                                            $udc["id"], 
                                            $targetResource, 
                                            $element["external_id"],
                                            end((explode("/", $targetObj["href"])))
                                        );
                                }
                            }
                        }
                        break;
                    case "weLookup":
                        $lookupValue = $this->getLookup($element, $targetObj);
                        $this->removeRelationship(
                                $lookupValue['resource'], 
                                "_id", 
                                $lookupValue["id"], 
                                $targetResource, 
                                $element["external_id"], 
                                end((explode("/", $targetObj["href"])))
                            );
                        break;
                    default:
                        break;
                }
            }
        }
    }
    
    public function inUsed($object, $resource){
        $relationObj = new Relationship($this->getTenantId());
        $criteria = new CriteriaOption();
        $criteria->where('_tenant', $relationObj->getTenantId());
        $criteria->where('origin_resource', $resource);
        $criteria->where('origin_resource_id', $object->_id);
        $relationObj->pushCriteria($criteria);
        $relationObj->applyCriteria();
        $result = $relationObj->readOne();
        if($result){
            return true;
        }
        return false;
    }
}
