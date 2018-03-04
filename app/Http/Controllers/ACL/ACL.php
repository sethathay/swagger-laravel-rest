<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\ACL;

use App\Http\Controllers\ACL\Security\User;
use App\Http\Controllers\ACL\Security\Group;
use App\Http\Controllers\ACL\PermissionLibrary\PermissionLibrary;
use App\Http\Controllers\ACL\PermissionLibrary\RowPermission;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\WeControls\WeDate;
use Carbon\Carbon;

class ACL {
    
    private $tenantId;
    private $user;
    private $schema;
    private $dateList = 
    ['Yesterday', 'Last Week', 'Last Month', 'Last Year',
     'Today', 'Current Week', 'Current Month', 'Current Year', 
     'Tomorrow', 'Next Week', 'Next Month', 'Next Year'
    ];
    
    public function __construct($tenantId = null, $user = null, $schema = null) {
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
        if($user != null){
            $this->user = $user;
        }
        if($schema != null){
            $this->schema = $schema;
        }
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    private function getACL(){
        /*
        |--------------------------------------------------------------------------
        | Module to get ACL permission of requested user
        |--------------------------------------------------------------------------
        */
        //Get security setting list of user (USER_SECURITY) 
        $roles = array();
        $userSecurity = new User($this->getTenantId());
        $uSecurityCriteria = new CriteriaOption();
        $uSecurityCriteria->where(User::FIELD_RECORD_TYPE, $userSecurity->getRecordType());
        $uSecurityCriteria->where(User::FIELD_TENANT, $userSecurity->getTenantId());
        $uSecurityCriteria->where("_user", $this->user->id);
        $userSecurity->pushCriteria($uSecurityCriteria);
        $userSecurity->applyCriteria();
        $objUserSecurity = $userSecurity->readOne();
        if($objUserSecurity){
            //Get specific roles assigned to user
            if($objUserSecurity->_role != null){
                $roles = array_merge($roles, $objUserSecurity->_role);
            }
            //If user has been assigned to any groups 
            //we need to get roles of the groups too
            if($objUserSecurity->_group != null){
                $groupSecurity = new Group($this->getTenantId());
                $gSecurityCriteria = new CriteriaOption();
                $gSecurityCriteria->where(Group::FIELD_RECORD_TYPE, $groupSecurity->getRecordType());
                $gSecurityCriteria->where(Group::FIELD_TENANT, $groupSecurity->getTenantId());
                $gSecurityCriteria->whereIn('name', $objUserSecurity->_group);
                $groupSecurity->pushCriteria($gSecurityCriteria);
                $groupSecurity->applyCriteria();
                $objGroupSecurity = $groupSecurity->readAll();
                if($objGroupSecurity){
                    foreach($objGroupSecurity as $itemGroupSecurity){
                        if($itemGroupSecurity->_role != null){
                            $roles = array_merge($roles, $itemGroupSecurity->_role);
                        }
                    }
                }
            }
            //Remove duplicate roles by using method array_unique
            $roles = array_unique($roles);
        }
        //Get all permissions from roles resulting from above process
        $rowPermission = new RowPermission($this->getTenantId());
        $permissionList = new PermissionLibrary($this->getTenantId());
        $pListCriteria = new CriteriaOption();
        $pListCriteria->where(PermissionLibrary::FIELD_RECORD_TYPE, $permissionList->getRecordType());
        $pListCriteria->where(PermissionLibrary::FIELD_TENANT, $permissionList->getTenantId());
        $pListCriteria->where('resource', $this->schema->getPluralName());
        $pListCriteria->whereIn(PermissionLibrary::FIELD_PERMISSION_TYPE, [
            $rowPermission->getType()
        ]);
        $pListCriteria->whereIn("name", $roles);
        $permissionList->pushCriteria($pListCriteria);
        $permissionList->applyCriteria();
        $objRolePermissions = $permissionList->readAll();
        //Get all permisions assigned specific to the user
        $perList = new PermissionLibrary($this->getTenantId());
        $pListCrt = new CriteriaOption();
        $pListCrt->where(PermissionLibrary::FIELD_RECORD_TYPE, $perList->getRecordType());
        $pListCrt->where(PermissionLibrary::FIELD_TENANT, $perList->getTenantId());
        $pListCrt->where('resource', $this->schema->getPluralName());
        $pListCrt->whereIn(PermissionLibrary::FIELD_PERMISSION_TYPE, [
            $rowPermission->getType()
        ]);
        $pListCrt->where("name", $this->user->id);
        $perList->pushCriteria($pListCrt);
        $perList->applyCriteria();
        $objUserPermissions = $perList->readAll();
        //PROCESS TO OVERWRITE PERMISSIONS ASSIGNED TO USER OVER
        //PERMISSIONS ASSIGNED TO ROLE BELONG TO THAT USER
        $aclList = array();
        if(count($objUserPermissions) > 0){
            foreach($objRolePermissions as $itemRolePermission){
                $bool = true;
                foreach($objUserPermissions as $itemUserPermission){
                    if(($itemUserPermission->type['code'] == $itemRolePermission->type['code'])
                        && ($itemUserPermission->resource == $itemRolePermission->resource)
                        && ($itemUserPermission->field == $itemRolePermission->field)){
                        //Excluded this permission from permissions of role
                        $bool = false;
                    }
                }
                if($bool){ $aclList[] =  $itemRolePermission; }
            }
            $aclList[] = $objUserPermissions;
            return $aclList[0];
        }
        return $objRolePermissions;
        /*
        |--------------------------------------------------------------------------
        | End Module of getting ACL permission of requested user
        |--------------------------------------------------------------------------
        */
    }
    
    public function canAddNew($data){
        $aclList = $this->getACL();
        $result = true;
        //APPLYING ACL LIST TO RESOURCES GETTING FROM ABOVE PROCESS
        foreach($aclList as $acl){
            //Search to get index of column external field in fields list
            $schemaFields = $this->schema->getAllFields();
            $ind = array_search($acl->field, array_column($schemaFields, 'external_id'));
            switch($schemaFields[$ind]['edit_type']){
                case "weText" :
                case "weUdc" :
                case "wePhone" :
                case "weEmail" :
                case "weLink" :
                case "weAddress" :
                case "weSocial" :
                case "weNumber" :
                case "weCurrency" :
                    //ALLOW TO ADD NEW
                    if($acl->add){
                        if(isset($data[$acl->field])){
                            if(!($data[$acl->field] >= $acl->from_value && 
                                $data[$acl->field] <= $acl->to_value)){
                                $result = false;
                            }
                        }else{
                            $result = false;
                        }
                    }
                    //DO NOT ALLOW TO ADD NEW
                    else if(!$acl->add){
                        if(isset($data[$acl->field])){
                            if($data[$acl->field] >= $acl->from_value && 
                                $data[$acl->field] <= $acl->to_value){
                                $result = false;
                            }else{
                                //NEED TO CHECK DEFAULT
                            }
                        }else{
                            //No need to check data input of that field
                        }
                    }
                    break;
                case "weDate" :
                    //ALLOW TO ADD NEW
                    if($acl->add){
                        if(isset($data[$acl->field])){
                            $dateObj = new WeDate(null, null);
                            $dateData = $dateObj->setJulianDateTime($data[$acl->field], $this->user);
                            //Check if from date and to value in role permission match any keywords in list
                            if(in_array($acl->from_value, $this->dateList) 
                                && in_array($acl->to_value, $this->dateList)){
                                switch($acl->from_value){
                                    case "Yesterday" :
                                        $startOfDay = $dateObj->setJulianDateTime(Carbon::yesterday()->startOfDay(), $this->user);
                                        $endOfDay = $dateObj->setJulianDateTime(Carbon::yesterday()->endOfDay(), $this->user);
                                        if(!($dateData['datetime'] >= $startOfDay['datetime'] && 
                                            $dateData['datetime'] <= $endOfDay['datetime'])){
                                            return false;
                                        }
                                        break;
                                    case "Last Week":
                                        $monday = $dateObj->setJulianDateTime(Carbon::now()->subDays(7)->startOfWeek(), $this->user);
                                        $sunday = $dateObj->setJulianDateTime(Carbon::now()->subDays(7)->endOfWeek(), $this->user);
                                        if(!($dateData['datetime'] >= $monday['datetime'] && 
                                            $dateData['datetime'] <= $sunday['datetime'])){
                                            return false;
                                        }
                                        break;
                                    case "Last Month":
                                        $startOfMonth = $dateObj->setJulianDateTime(Carbon::now()->subMonth(1)->startOfMonth(), $this->user);
                                        $endOfMonth = $dateObj->setJulianDateTime(Carbon::now()->subMonth(1)->endOfMonth(), $this->user);
                                        if(!($dateData['datetime'] >= $startOfMonth['datetime'] && 
                                            $dateData['datetime'] <= $endOfMonth['datetime'])){
                                            return false;
                                        }
                                        break;
                                    case "Last Year" :
                                        $startOfYear = $dateObj->setJulianDateTime(Carbon::now()->subYear(1)->startOfYear(), $this->user);
                                        $endOfYear = $dateObj->setJulianDateTime(Carbon::now()->subYear(1)->endOfYear(), $this->user);
                                        if(!($dateData['datetime'] >= $startOfYear['datetime'] && 
                                            $dateData['datetime'] <= $endOfYear['datetime'])){
                                            return false;
                                        }
                                        break;
                                    case "Today":
                                        $startOfDay = $dateObj->setJulianDateTime(Carbon::now()->startOfDay(), $this->user);
                                        $endOfDay = $dateObj->setJulianDateTime(Carbon::now()->endOfDay(), $this->user);
                                        if(!($dateData['datetime'] >= $startOfDay['datetime'] && 
                                            $dateData['datetime'] <= $endOfDay['datetime'])){
                                            return false;
                                        }
                                        break;
                                    case "Current Week" :
                                        $monday = $dateObj->setJulianDateTime(Carbon::now()->startOfWeek(), $this->user);
                                        $sunday = $dateObj->setJulianDateTime(Carbon::now()->endOfWeek(), $this->user);
                                        if(!($dateData['datetime'] >= $monday['datetime'] && 
                                            $dateData['datetime'] <= $sunday['datetime'])){
                                            return false;
                                        }
                                        break;
                                    case "Current Month" :
                                        $startOfMonth = $dateObj->setJulianDateTime(Carbon::now()->startOfMonth(), $this->user);
                                        $endOfMonth = $dateObj->setJulianDateTime(Carbon::now()->endOfMonth(), $this->user);
                                        if(!($dateData['datetime'] >= $startOfMonth['datetime'] && 
                                            $dateData['datetime'] <= $endOfMonth['datetime'])){
                                            return false;
                                        }
                                        break;
                                    case "Current Year" :
                                        $startOfYear = $dateObj->setJulianDateTime(Carbon::now()->startOfYear(), $this->user);
                                        $endOfYear = $dateObj->setJulianDateTime(Carbon::now()->endOfYear(), $this->user);
                                        if(!($dateData['datetime'] >= $startOfYear['datetime'] && 
                                            $dateData['datetime'] <= $endOfYear['datetime'])){
                                            return false;
                                        }
                                        break;
                                    case "Tomorrow" :
                                        $startOfDay = $dateObj->setJulianDateTime(Carbon::tomorrow()->startOfDay(), $this->user);
                                        $endOfDay = $dateObj->setJulianDateTime(Carbon::tomorrow()->endOfDay(), $this->user);
                                        if(!($dateData['datetime'] >= $startOfDay['datetime'] && 
                                            $dateData['datetime'] <= $endOfDay['datetime'])){
                                            return false;
                                        }
                                        break;
                                    case "Next Week" :
                                        $monday = $dateObj->setJulianDateTime(Carbon::now()->addDays(7)->startOfWeek(), $this->user);
                                        $sunday = $dateObj->setJulianDateTime(Carbon::now()->addDays(7)->endOfWeek(), $this->user);
                                        if(!($dateData['datetime'] >= $monday['datetime'] && 
                                            $dateData['datetime'] <= $sunday['datetime'])){
                                            return false;
                                        }
                                        break;
                                    case "Next Month" :
                                        $startOfMonth = $dateObj->setJulianDateTime(Carbon::now()->addMonth(1)->startOfMonth(), $this->user);
                                        $endOfMonth = $dateObj->setJulianDateTime(Carbon::now()->addMonth(1)->endOfMonth(), $this->user);
                                        if(!($dateData['datetime'] >= $startOfMonth['datetime'] && 
                                            $dateData['datetime'] <= $endOfMonth['datetime'])){
                                            return false;
                                        }
                                        break;
                                    case "Next Year" :
                                        $startOfYear = $dateObj->setJulianDateTime(Carbon::now()->addYear(1)->startOfYear(), $this->user);
                                        $endOfYear = $dateObj->setJulianDateTime(Carbon::now()->addYear(1)->endOfYear(), $this->user);
                                        if(!($dateData['datetime'] >= $startOfYear['datetime'] && 
                                            $dateData['datetime'] <= $endOfYear['datetime'])){
                                            return false;
                                        }
                                        break;
                                    default:
                                        break;
                                }
                            }else{
                                if(!($dateData['datetime'] >= $acl->from_value['datetime'] && 
                                    $dateData['datetime'] <= $acl->to_value['datetime'])){
                                    $result = false;
                                }
                            }
                        }else{
                            $result = false;
                        }
                    }
                    //DO NOT ALLOW TO ADD NEW
                    else if(!$acl->add){
                        if(isset($data[$acl->field])){
                            $dateObj = new WeDate(null, null);
                            $dateData = $dateObj->setJulianDateTime($data[$acl->field], $this->user);
                            if($dateData['datetime'] >= $acl->from_value['datetime'] && 
                                $dateData['datetime'] <= $acl->to_value['datetime']){
                                $result = false;
                            }else{
                                //NEED TO CHECK DEFAULT
                            }
                        }else{
                            //No need to check data input of that field
                        }
                    }
                    break;
                default:
                    break;
            }
        }
        return $result;
    }
    
    public function canDelete($object){
        $aclList = $this->getACL();
        $result = true;
        //APPLYING ACL LIST TO RESOURCES GETTING FROM ABOVE PROCESS
        foreach($aclList as $acl){
            //Search to get index of column external field in fields list
            $schemaFields = $this->schema->getAllFields();
            $ind = array_search($acl->field, array_column($schemaFields, 'external_id'));
            switch($schemaFields[$ind]['edit_type']){
                case "weUdc" :
                    //ALLOW TO DELETE
                    if($acl->delete){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            $fieldItem = $object->$schemaFields[$ind]['field_id'];
                            if(!($fieldItem['code'] >= $acl->from_value && 
                                $fieldItem['code'] <= $acl->to_value)){
                                $result = false;
                            }
                        }else{
                            $result = false;
                        }
                    }
                    //DO NOT ALLOW TO DELETE
                    else if(!$acl->delete){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            $fieldItem = $object->$schemaFields[$ind]['field_id'];
                            if($fieldItem['code'] >= $acl->from_value && 
                                $fieldItem['code'] <= $acl->to_value){
                                $result = false;
                            }else{
                                //NEED TO CHECK DEFAULT
                            }
                        }else{
                            //No need to check data input of that field
                        }
                    }
                    break;
                case "weText" :
                case "wePhone" :
                case "weEmail" :
                case "weLink" :
                case "weAddress" :
                case "weSocial" :
                case "weNumber" :
                case "weCurrency" :
                    //ALLOW TO DELETE
                    if($acl->delete){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            if(!($object->$schemaFields[$ind]['field_id'] >= $acl->from_value && 
                                $object->$schemaFields[$ind]['field_id'] <= $acl->to_value)){
                                $result = false;
                            }
                        }else{
                            $result = false;
                        }
                    }
                    //DO NOT ALLOW TO DELETE
                    else if(!$acl->delete){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            if($object->$schemaFields[$ind]['field_id'] >= $acl->from_value && 
                                $object->$schemaFields[$ind]['field_id'] <= $acl->to_value){
                                $result = false;
                            }else{
                                //NEED TO CHECK DEFAULT
                            }
                        }else{
                            //No need to check data input of that field
                        }
                    }
                    break;
                case "weDate" :
                    //ALLOW TO DELETE
                    if($acl->delete){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            $fieldItem = $object->$schemaFields[$ind]['field_id'];
                            if(!($fieldItem['datetime'] >= $acl->from_value['datetime'] && 
                                $fieldItem['datetime'] <= $acl->to_value['datetime'])){
                                $result = false;
                            }
                        }else{
                            $result = false;
                        }
                    }
                    //DO NOT ALLOW TO DELETE
                    else if(!$acl->delete){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            $fieldItem = $object->$schemaFields[$ind]['field_id'];
                            if($fieldItem['datetime'] >= $acl->from_value['datetime'] && 
                                $fieldItem['datetime'] <= $acl->to_value['datetime']){
                                $result = false;
                            }else{
                                //NEED TO CHECK DEFAULT
                            }
                        }else{
                            //No need to check data input of that field
                        }
                    }
                    break;
                default:
                    break;
            }
        }
        return $result;
    }
    
    public function canRead($object){
        $aclList = $this->getACL();
        $result = true;
        //APPLYING ACL LIST TO RESOURCES GETTING FROM ABOVE PROCESS
        foreach($aclList as $acl){
            //Search to get index of column external field in fields list
            $schemaFields = $this->schema->getAllFields();
            $ind = array_search($acl->field, array_column($schemaFields, 'external_id'));
            switch($schemaFields[$ind]['edit_type']){
                case "weUdc" :
                    //ALLOW TO VIEW
                    if($acl->view){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            $fieldItem = $object->$schemaFields[$ind]['field_id'];
                            if(!($fieldItem['code'] >= $acl->from_value && 
                                $fieldItem['code'] <= $acl->to_value)){
                                $result = false;
                            }
                        }else{
                            $result = false;
                        }
                    }
                    //DO NOT ALLOW TO VIEW
                    else if(!$acl->view){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            $fieldItem = $object->$schemaFields[$ind]['field_id'];
                            if($fieldItem['code'] >= $acl->from_value && 
                                $fieldItem['code'] <= $acl->to_value){
                                $result = false;
                            }else{
                                //NEED TO CHECK DEFAULT
                            }
                        }else{
                            //No need to check data input of that field
                        }
                    }
                    break;
                case "weText" :
                case "wePhone" :
                case "weEmail" :
                case "weLink" :
                case "weAddress" :
                case "weSocial" :
                case "weNumber" :
                case "weCurrency" :
                    //ALLOW TO VIEW
                    if($acl->view){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            if(!($object->$schemaFields[$ind]['field_id'] >= $acl->from_value && 
                                $object->$schemaFields[$ind]['field_id'] <= $acl->to_value)){
                                $result = false;
                            }
                        }else{
                            $result = false;
                        }
                    }
                    //DO NOT ALLOW TO VIEW
                    else if(!$acl->view){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            if($object->$schemaFields[$ind]['field_id'] >= $acl->from_value && 
                                $object->$schemaFields[$ind]['field_id'] <= $acl->to_value){
                                $result = false;
                            }else{
                                //NEED TO CHECK DEFAULT
                            }
                        }else{
                            //No need to check data input of that field
                        }
                    }
                    break;
                case "weDate" :
                    //ALLOW TO VIEW
                    if($acl->view){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            $fieldItem = $object->$schemaFields[$ind]['field_id'];
                            if(!($fieldItem['datetime'] >= $acl->from_value['datetime'] && 
                                $fieldItem['datetime'] <= $acl->to_value['datetime'])){
                                $result = false;
                            }
                        }else{
                            $result = false;
                        }
                    }
                    //DO NOT ALLOW TO VIEW
                    else if(!$acl->view){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            $fieldItem = $object->$schemaFields[$ind]['field_id'];
                            if($fieldItem['datetime'] >= $acl->from_value['datetime'] && 
                                $fieldItem['datetime'] <= $acl->to_value['datetime']){
                                $result = false;
                            }else{
                                //NEED TO CHECK DEFAULT
                            }
                        }else{
                            //No need to check data input of that field
                        }
                    }
                    break;
                default:
                    break;
            }
        }
        return $result;
    }
    
    public function canChange($object){
        $aclList = $this->getACL();
        $result = true;
        //APPLYING ACL LIST TO RESOURCES GETTING FROM ABOVE PROCESS
        foreach($aclList as $acl){
            //Search to get index of column external field in fields list
            $schemaFields = $this->schema->getAllFields();
            $ind = array_search($acl->field, array_column($schemaFields, 'external_id'));
            switch($schemaFields[$ind]['edit_type']){
                case "weUdc" :
                    //ALLOW TO CHANGE
                    if($acl->change){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            $fieldItem = $object->$schemaFields[$ind]['field_id'];
                            if(!($fieldItem['code'] >= $acl->from_value && 
                                $fieldItem['code'] <= $acl->to_value)){
                                $result = false;
                            }
                        }else{
                            $result = false;
                        }
                    }
                    //DO NOT ALLOW TO CHANGE
                    else if(!$acl->change){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            $fieldItem = $object->$schemaFields[$ind]['field_id'];
                            if($fieldItem['code'] >= $acl->from_value && 
                                $fieldItem['code'] <= $acl->to_value){
                                $result = false;
                            }else{
                                //NEED TO CHECK DEFAULT
                            }
                        }else{
                            //No need to check data input of that field
                        }
                    }
                    break;
                case "weText" :
                case "wePhone" :
                case "weEmail" :
                case "weLink" :
                case "weAddress" :
                case "weSocial" :
                case "weNumber" :
                case "weCurrency" :
                    //ALLOW TO CHANGE
                    if($acl->change){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            if(!($object->$schemaFields[$ind]['field_id'] >= $acl->from_value && 
                                $object->$schemaFields[$ind]['field_id'] <= $acl->to_value)){
                                $result = false;
                            }
                        }else{
                            $result = false;
                        }
                    }
                    //DO NOT ALLOW TO CHANGE
                    else if(!$acl->change){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            if($object->$schemaFields[$ind]['field_id'] >= $acl->from_value && 
                                $object->$schemaFields[$ind]['field_id'] <= $acl->to_value){
                                $result = false;
                            }else{
                                //NEED TO CHECK DEFAULT
                            }
                        }else{
                            //No need to check data input of that field
                        }
                    }
                    break;
                case "weDate" :
                    //ALLOW TO CHANGE
                    if($acl->change){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            $fieldItem = $object->$schemaFields[$ind]['field_id'];
                            if(!($fieldItem['datetime'] >= $acl->from_value['datetime'] && 
                                $fieldItem['datetime'] <= $acl->to_value['datetime'])){
                                $result = false;
                            }
                        }else{
                            $result = false;
                        }
                    }
                    //DO NOT ALLOW TO CHANGE
                    else if(!$acl->change){
                        if(isset($object->$schemaFields[$ind]['field_id'])){
                            $fieldItem = $object->$schemaFields[$ind]['field_id'];
                            if($fieldItem['datetime'] >= $acl->from_value['datetime'] && 
                                $fieldItem['datetime'] <= $acl->to_value['datetime']){
                                $result = false;
                            }else{
                                //NEED TO CHECK DEFAULT
                            }
                        }else{
                            //No need to check data input of that field
                        }
                    }
                    break;
                default:
                    break;
            }
        }
        return $result;
    }
    
    public function getFiltersOfACL(){
        $aclList = $this->getACL();
        $result = array();
        $fields = array();
        //APPLYING ACL LIST TO RESOURCES GETTING FROM ABOVE PROCESS
        foreach($aclList as $acl){
            //Search to get index of column external field in fields list
            $schemaFields = $this->schema->getAllFields();
            $ind = array_search($acl->field, array_column($schemaFields, 'external_id'));
            switch($schemaFields[$ind]['edit_type']){
                case "weText" :
                case "weUdc" :
                case "wePhone" :
                case "weEmail" :
                case "weLink" :
                case "weAddress" :
                case "weSocial" :
                case "weNumber" :
                case "weCurrency" :
                    //ALLOW TO VIEW
                    if($acl->view){
                        $fields[] = [
                                        "K" => $acl->field,
                                        "L" => "and",
                                        "F" => [
                                            [
                                                "K" => $acl->field,
                                                "O" => "gte",
                                                "V" => $acl->from_value
                                            ],
                                            [
                                                "K" => $acl->field,
                                                "O" => "lte",
                                                "V" => $acl->to_value
                                            ]
                                        ]
                                    ];
                    }
                    //DO NOT ALLOW TO VIEW
                    else if(!$acl->view){
                        $fields[] = [
                                        "K" => $acl->field,
                                        "L" => "and",
                                        "F" => [
                                            [
                                                "K" => $acl->field,
                                                "O" => "lt",
                                                "V" => $acl->from_value
                                            ],
                                            [
                                                "K" => $acl->field,
                                                "O" => "gt",
                                                "V" => $acl->to_value
                                            ]
                                        ]
                                    ];
                    }
                    break;
                case "weDate" :
                    if(isset($acl->from_value)){
                        $inputDataFrom = new \stdClass();
                        $inputDataFrom->$schemaFields[$ind]['field_id'] = $acl->from_value;
                        $dateObjFrom = new WeDate($inputDataFrom, $schemaFields[$ind]);
                    }
                    if(isset($acl->to_value)){
                        $inputDataTo = new \stdClass();
                        $inputDataTo->$schemaFields[$ind]['field_id'] = $acl->to_value;
                        $dateObjTo = new WeDate($inputDataTo, $schemaFields[$ind]);
                    }
                    //ALLOW TO VIEW
                    if($acl->view){
                        $fields[] = [
                                        "K" => $acl->field,
                                        "L" => "and",
                                        "F" => [
                                            [
                                                "K" => $acl->field,
                                                "O" => "gte",
                                                "V" => $dateObjFrom->renderOutput($this->user)
                                            ],
                                            [
                                                "K" => $acl->field,
                                                "O" => "lte",
                                                "V" => $dateObjTo->renderOutput($this->user)
                                            ]
                                        ]
                                    ];
                    }
                    //DO NOT ALLOW TO VIEW
                    else if(!$acl->view){
                        $fields[] = [
                                        "K" => $acl->field,
                                        "L" => "and",
                                        "F" => [
                                            [
                                                "K" => $acl->field,
                                                "O" => "lt",
                                                "V" => $dateObjFrom->renderOutput($this->user)
                                            ],
                                            [
                                                "K" => $acl->field,
                                                "O" => "gt",
                                                "V" => $dateObjTo->renderOutput($this->user)
                                            ]
                                        ]
                                    ];
                    }
                    break;
                default:
                    break;
            }
        }
        if(count($fields)){
            $result['L'] = 'and';
            $result['F'] = $fields;
        }
        return $result;
    }
}
