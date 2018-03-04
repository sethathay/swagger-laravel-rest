<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\BaseLibrary\WeControls;

use App\Http\Controllers\Udc\UdcValue;
use App\Http\Controllers\Udc\UdcValueFoundation;

class WeUdc {
    
    private $data;
    private $element;
    private $tenantId   = "577cd4229dc8c0c114124f7a";
    
    public function __construct($data, $element) {
        $this->data = $data;
        $this->element = $element;
    }
    /**
     * Function to validate weUdc
     * @date 12-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return array of validation message
     */
    public function validate(){
        $data = $this->data;
        $element = $this->element;
        //Condition when schema field is a required field and data input is empty/null
        if($element["config"]["required"] && ($data[$element["external_id"]] == "" || $data[$element["external_id"]] == null)){
            return array('status' => false,
                    'message'=> $element["external_id"] . ' field is required and should not null or empty.');
        }
        //dd($element);
        //Condition when control type is "default dropdown" or "dropdown search"
        if($element["config"]["custom"]["dropdown_type"] == "default" ||
           $element["config"]["custom"]["dropdown_type"] == "filterable"){
            //Multiple selection
            if($element["config"]["custom"]["multiple"]){
                
            }
            //Single selection
            else{
                if(!$this->validateUdc($data[$element["external_id"]], 
                        $element["config"]["custom"]["udc_type"],
                        $element["config"]["custom"]["sys_code"],
                        $element["config"]["custom"]["use_shared_data"])){
                    return array('status' => false,
                                 'message'=> $element["external_id"] . ' field is not a valid value of udc type.');
                }
            }
        }
        //Condition when control type is "popup"
        else if($element["config"]["custom"]["dropdown_type"] == "auto-complete"){
            
        }
        //Conditon when control type is "switch"
        else if($element["config"]["custom"]["dropdown_type"] == "switch"){
            if(!$this->validateBoolean($data[$element["external_id"]])){
                return array('status' => false,
                             'message'=> $element["external_id"] . ' field is requird boolean data type.');
            }
        }
        return array('status' => true,
                    'message'=> 'Successfully validated user input data');
    }
    /**
     * Function to render input of weUdc
     * @date 13-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return array
     */
    public function renderInput(){
        $data = $this->data;
        $element = $this->element;
        //Condition when control type is "default dropdown" or "dropdown search"
        if($element["config"]["custom"]["dropdown_type"] == "default" ||
           $element["config"]["custom"]["dropdown_type"] == "filterable"){
           //Multiple selection
            if($element["config"]["custom"]["multiple"]){
                
            }
            //Single selection
            else{
                if($element["config"]["custom"]["use_shared_data"]){
                    return UdcValueFoundation::getUdcValue(
                                                        $element["config"]["custom"]["sys_code"],
                                                        $element["config"]["custom"]["udc_type"],
                                                        $data[$element["external_id"]]
                                                    );
                }else{
                    return UdcValue::getUdcValue(  $this->tenantId,
                                                    $element["config"]["custom"]["sys_code"],
                                                    $element["config"]["custom"]["udc_type"],
                                                    $data[$element["external_id"]]
                                                );
                }
            }
        }
        //Condition when control type is "popup"
        else if($element["config"]["custom"]["dropdown_type"] == "auto-complete"){
            
        }
        //Conditon when control type is "switch"
        else if($element["config"]["custom"]["dropdown_type"] == "switch"){
            return $data[$element["external_id"]];
        }
    }
    /**
     * Function to render output of weTextUdc
     * @date 14-Nov-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return string
     */
    public function renderOutput(){
        $data = $this->data;
        $element = $this->element;
        return $data->$element["field_id"];
    }
    /**
     * Function to validate udc data type
     * @date 12-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to validate
     * @param  string $udcType is value of udc type
     * @param  string $sysCode is value of system code
     * @return boolean
     */
    private function validateUdc($data, $udcType, $sysCode,$shared){
        if($shared){
            $result = UdcValueFoundation::getUdcValue($sysCode, $udcType, $data);
        }else{
            $result = UdcValue::getUdcValue($this->tenantId, $sysCode, $udcType, $data);
        }
        return count($result) > 0 ? true : false;
    }
    /**
     * Function to validate boolean (true/false)
     * @date 12-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to validate
     * @return boolean
     */
    private function validateBoolean($data){
        return is_bool($data);
    }
}
