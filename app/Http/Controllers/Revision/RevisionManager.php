<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\Revision;

use App\Http\Controllers\Revision\Revision;
use App\Http\Controllers\Revision\Event;
use App\Http\Controllers\Util\MongoUtil;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\WeControls\WeDate;

class RevisionManager {
    
    private $tenantId;
    private $revisionNo = null;
    private $schemaRevision = "TREVISION";
    private $schemaEvent = "TEVENT";
    
    public function __construct($tenantId = null) {
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    public function getRevision($eventId, $resource, $resourceId, $field, $oldValue, $newValue, $action){
        return array(
            'tenant' => $this->getTenantId(),
            'eventId' => $eventId,
            'resource' => $resource,
            'resourceId' => $resourceId,
            'field' => $field,
            'oldValue' => $oldValue,
            'newValue' => $newValue,
            'revisionNo' => $this->revisionNo == null ? 
                            $this->getRevisionNo($resource, $resourceId) :
                            $this->revisionNo,
            'action' => $action
        );
    }
    
    private function getRevisionNo($resource, $resourceId){
        $revisionObj = new Revision($this->getTenantId());
        $criteria = new CriteriaOption();
        $criteria->where('record_type', $revisionObj->getRecordType());
        $criteria->where('_tenant', $this->getTenantId());
        $criteria->where('resource', $resource);
        $criteria->where('resource_id', $resourceId);
        $criteria->orderBy('revision_no', 'DESC');
        $revisionObj->pushCriteria($criteria);
        $revisionObj->applyCriteria();
        $result = $revisionObj->readOne();
        if($result){
            $this->revisionNo = $result['revision_no'] + 1;
        }else{
            $this->revisionNo = 1;
        }
        return $this->revisionNo;
    }
    
    public function insertData($data, $user){
        $dataRevision = [];
        $revisionObj = new Revision($this->getTenantId());
        $revisionSchema = new SchemaRender($this->schemaRevision);
        foreach($data as $item){
            $itemObj = $revisionSchema->renderInput($item, $revisionObj->getModel(), $user, "C");
            $itemObj->record_type = $revisionObj->getRecordType();
            $dataRevision[] = $itemObj->toArray();
        }
        $revisionObj->saveMany($dataRevision);
    }
    
    public function newEvent($name, $user){
        $eventId = (string)MongoUtil::getObjectID();
        $data = array(
            'tenant' => $this->getTenantId(),
            'name' => $name,
            'eventId' => $eventId
        );
        $eventObj = new Event($this->getTenantId());
        $eventSchema = new SchemaRender($this->schemaEvent);
        $dataEvent = $eventSchema->renderInput($data, $eventObj->getModel(), $user, "C");
        $eventObj->save($dataEvent);
        return $eventId;
    }
    
    public function save($eventId, $fields, $oldObj, $newObj, $action, $resource, $user){
        $revisionData = [];
        if(isset($fields) && $fields != ""){
            foreach($fields as $element){
                switch($element["edit_type"]){
                    case "weText" :
                        if($oldObj == null){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                null, 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }else if($oldObj[$element['external_id']] != $newObj[$element['external_id']]){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                $oldObj[$element['external_id']], 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }
                        break;
                    case "weUdc" :
                        if($oldObj == null){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                null, 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }else if($element["config"]["custom"]["dropdown_type"] == "default" ||
                                $element["config"]["custom"]["dropdown_type"] == "filterable"){
                                //Multiple selection
                                if($element["config"]["custom"]["multiple"]){

                                }
                                //Single selection
                                else{
                                    if($oldObj[$element['external_id']]['code'] != $newObj[$element['external_id']]['code']){
                                        $revisionData[] = $this->getRevision(
                                                            $eventId,
                                                            $resource, 
                                                            $newObj["id"], 
                                                            $element['field_id'], 
                                                            $oldObj[$element['external_id']], 
                                                            $newObj[$element['external_id']], 
                                                            $action);
                                    }

                                }
                        }else if($element["config"]["custom"]["dropdown_type"] == "switch"){
                            if($oldObj[$element['external_id']] != $newObj[$element['external_id']]){
                                $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                $oldObj[$element['external_id']], 
                                                $newObj[$element['external_id']], 
                                                $action);
                            }
                        }
                        break;
                    case "wePhone" :
                        if($oldObj == null){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                null, 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }else if($oldObj[$element['external_id']] !== $newObj[$element['external_id']]){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                $oldObj[$element['external_id']], 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }
                        break;
                    case "weEmail" :
                        if($oldObj == null){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                null, 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }else if($oldObj[$element['external_id']] !== $newObj[$element['external_id']]){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                $oldObj[$element['external_id']], 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }
                        break;
                    case "weLink" :
                        if($oldObj == null){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                null, 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }else if($oldObj[$element['external_id']] !== $newObj[$element['external_id']]){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                $oldObj[$element['external_id']], 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }
                        break;
                    case "weAddress" :
                        if($oldObj == null){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                null, 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }else if($oldObj[$element['external_id']] !== $newObj[$element['external_id']]){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                $oldObj[$element['external_id']], 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }
                        break;
                    case "weSocial" :
                        if($oldObj == null){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                null, 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }else if($oldObj[$element['external_id']] !== $newObj[$element['external_id']]){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                $oldObj[$element['external_id']], 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }
                        break;
                    case "weNumber" :
                        if($oldObj == null){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                null, 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }else if($oldObj[$element['external_id']] != $newObj[$element['external_id']]){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                $oldObj[$element['external_id']], 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }
                        break;
                    case "weCurrency" :
                        if($oldObj == null){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                null, 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }else if($oldObj[$element['external_id']] !== $newObj[$element['external_id']]){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                $oldObj[$element['external_id']], 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }
                        break;
                    case "weDate" :
                        //Excluded createdAt & updatedAt field from data revision
                        if($element['external_id'] == 'createdAt' ||
                           $element['external_id'] == 'updatedAt'){ break; }
                        if($oldObj == null){
                            $dateObj = new WeDate(null, null);
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                null, 
                                                $dateObj->setJulianDateTime($newObj[$element['external_id']], $user), 
                                                $action);
                        }else if($oldObj[$element['external_id']] != $newObj[$element['external_id']]){
                            $dateObj = new WeDate(null, null);
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                $dateObj->setJulianDateTime($oldObj[$element['external_id']], $user), 
                                                $dateObj->setJulianDateTime($newObj[$element['external_id']], $user), 
                                                $action);
                        }
                        break;
                    case "weLookup" :
                        if($oldObj == null){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                null, 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }else if($oldObj[$element['external_id']] != $newObj[$element['external_id']]){
                            $revisionData[] = $this->getRevision(
                                                $eventId,
                                                $resource, 
                                                $newObj["id"], 
                                                $element['field_id'], 
                                                $oldObj[$element['external_id']], 
                                                $newObj[$element['external_id']], 
                                                $action);
                        }
                        break;
                    case "weEmbedded" :
                        break;
                    default:
                        break;
                }
            }
            if(count($revisionData) > 0){
                $this->insertData($revisionData, $user);
            }
        }
    }
}
