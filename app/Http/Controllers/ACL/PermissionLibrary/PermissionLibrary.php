<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\ACL\PermissionLibrary;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\ACL\RoleLibrary\RoleLibrary;
use App\Http\Controllers\ObjLibrary\Schema;

class PermissionLibrary extends AbstractResourceProvider{
    
    const FIELD_RECORD_TYPE = "record_type";
    const FIELD_PERMISSION_TYPE = "type.code";
    const FIELD_TENANT = "_tenant";
    const VALUE_RECORD_TYPE = "PERMISSION";
    
    private $type;
    private $recordType;
    private $tenantId;
    private $schemaRoleLibrary = "TROLELIBRARY";
    
    //Initiate constructor
    public function __construct($tenantId = null, $type = null){
        parent::__construct();
        $this->recordType = self::VALUE_RECORD_TYPE;
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
        if($type != null){
            $this->type = $type;
        }
    }
    
    public function getType(){
        return $this->type;
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    public function getRecordType(){
        return $this->recordType;
    }
    
    //Override abstract method model of abstract resource provider
    public function model(){
        return 'App\RolePermission';
    }
    
    public function save($object) {
        $object->record_type = $this->getRecordType();
        return parent::save($object);
    }
    
    public function getRoleLibrary($tenantId, $role){
        $criteria = new CriteriaOption();
        $roleObject = new RoleLibrary($tenantId);
        $roleSchema = new SchemaRender($this->schemaRoleLibrary);
        $criteria->where(RoleLibrary::FIELD_RECORD_TYPE, $roleObject->getRecordType());
        $criteria->where(RoleLibrary::FIELD_TENANT, $roleObject->getTenantId());
        $criteria->whereRaw($roleObject->getDefaultFilter($role), $roleSchema->getAllFields(), "");
        $roleObject->pushCriteria($criteria);
        $roleObject->applyCriteria();
        return $roleObject->readOne();
    }
    
    public function validateResource($data){
        if(isset($data['resource']) && isset($data['field'])){
            $schemaObj = new Schema();
            $ct = new CriteriaOption();
            $ct->where(Schema::FIELD_RECORD_TYPE, $schemaObj->getRecordType());
            $ct->where(Schema::FIELD_OBJECT_TYPE, $schemaObj->getType());
            $ct->where('resource.plural', $data['resource']);
            $schemaObj->pushCriteria($ct);
            $schemaObj->applyCriteria();
            $result = $schemaObj->readOne();
            if($result){
                $ind = array_search($data['field'], array_column($result->fields, 'external_id'));
                if(!$ind){
                    return array(
                        'status' => false,
                        'message' => 'Invalid field name'
                    );
                }else{
                    return $result->fields[$ind];
                }
            }else{
                return array(
                    'status' => false,
                    'message' => 'Invalid resource name'
                );
            }
        }
    }
}
