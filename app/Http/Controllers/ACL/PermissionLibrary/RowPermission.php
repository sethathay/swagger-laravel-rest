<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\ACL\PermissionLibrary;

use App\Http\Controllers\BaseLibrary\WeControls\WeDate;

class RowPermission extends PermissionLibrary {
        
    public function __construct($tenantId = null) {
        $rowPermission = 'TBLE';
        parent::__construct($tenantId, $rowPermission);
    }
    /**
     * Rendering before input of from value and to value based on resource and field
     * @date 22-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  array $field
     * @param  array $data
     * @param  object $user
     * @return transformed from value and to value
     */
    public function renderInputValue($field, $data, $user){
        switch($field['edit_type']){
            case "weNumber" :
            case "weCurrency" :
                if(isset($data['fromValue'])){
                    $data['fromValue'] = (float)$data['fromValue']; 
                }
                if(isset($data['toValue'])){
                    $data['toValue'] = (float)$data['toValue'];
                }
                break;
            case "weDate" :
                if(isset($data['fromValue'])){
                    $inputData[$field['external_id']] = $data['fromValue'];
                    $dateObj = new WeDate($inputData, $field);
                    $data['fromValue'] = $dateObj->renderInput($user);
                }
                if(isset($data['toValue'])){
                    $inputData[$field['external_id']] = $data['toValue'];
                    $dateObj = new WeDate($inputData, $field);
                    $data['toValue'] = $dateObj->renderInput($user);
                }
                break;
            default:
                break;
        }
        return $data;
    }
    /**
     * Rendering output of from value and to value based on resource and field
     * @date 22-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  array $field
     * @param  array $data
     * @param  object $user
     * @return transformed from value and to value
     */
    public function renderOutputValue($field, $data, $user){
        switch($field['edit_type']){
            case "weNumber" :
            case "weCurrency" :
                if(isset($data->from_value)){
                    $data->from_value = (float)$data->from_value; 
                }
                if(isset($data->to_value)){
                    $data->to_value = (float)$data->to_value;
                }
                break;
            case "weDate" :
                if(isset($data->from_value)){
                    $inputData = new \stdClass();
                    $inputData->$field['field_id'] = $data->from_value;
                    $dateObj = new WeDate($inputData, $field);
                    $data->from_value = $dateObj->renderOutput($user);
                }
                if(isset($data->to_value)){
                    $inputData = new \stdClass();
                    $inputData->$field['field_id'] = $data->to_value;
                    $dateObj = new WeDate($inputData, $field);
                    $data->to_value = $dateObj->renderOutput($user);
                }
                break;
            default:
                break;
        }
        return $data;
    }
    /**
     * Rendering output datasource of from value and to value based on resource and field
     * @date 22-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  array $ds
     * @param  object $user
     * @return transformed output datasource of from value and to value
     */
    public function renderDSValues($ds, $user){
        foreach($ds as $dsource){
            //validate resource and field of column permission data input
            $resource = $this->validateResource([
                'resource' => $dsource->resource,
                'field' => $dsource->field
            ]);
            if(!$resource['status']){
                return ErrorHelper::getExceptionError($resource["message"]);
            }
            //Rendering output value of from value and to value
            $dsource = $this->renderOutputValue($resource, $dsource, $user);
        }
        return $ds;
    }
}
