<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\BaseLibrary\WeControls;

use App\Http\Controllers\BaseLibrary\SchemaRender;

class WePhone {
    
    private $data;
    private $element;
    private $schemaWePhone = "VWEPHONE";
    
    public function __construct($data, $element) {
        $this->data = $data;
        $this->element = $element;
    }
    /**
     * Function to validate wePhone
     * @date 12-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is user input data to validate
     * @param  var $element is schema for wePhone
     * @return array of validation message
     */
    public function validate(){
        $data = $this->data;
        $element = $this->element;
        //Condition when schema field is a required field and data input is empty/null
        if($element["config"]["required"] && empty($data[$element["external_id"]])){
            return array('status' => false,
                    'message'=> $element["external_id"] . ' field is required and should not null or empty.');
        }
        
        //Setting of wePhone for multiple input
        if($element["config"]["custom"]["multiple"]){
            //loop array of phone data
            foreach($data[$element["external_id"]] as $ctrlPhone){
                if(isset($ctrlPhone["type"]) && isset($ctrlPhone["country"])
                   && isset($ctrlPhone["area"]) && isset($ctrlPhone["number"])){
                    $wePhoneObj  = new SchemaRender($this->schemaWePhone);
                    $validateObj = $wePhoneObj->validate($ctrlPhone);
                    if(!$validateObj["status"]){
                        return $validateObj;
                    }
                    else{
                        $concatPhone = trim($ctrlPhone["country"] . $ctrlPhone["area"] . $ctrlPhone["number"]);
                        if($element["config"]["required"] && !$concatPhone){
                            return array('status' => false,
                                        'message'=> $element["external_id"] . ' field is required and should not null or empty.');
                        }
                    }
                }
                else{
                    return array('status' => false,
                             'message'=> $element["external_id"] . ' field is not a valid input data of phone number.');
                }
            }
        }
        //Setting of wePhone for single input
        else{
            if(isset($data[$element["external_id"]]["country"]) 
                    && isset($data[$element["external_id"]]["area"])
                    && isset($data[$element["external_id"]]["number"])){
                $concatPhone = trim($data[$element["external_id"]]["country"] . 
                                $data[$element["external_id"]]["area"] .
                                $data[$element["external_id"]]["number"]);
                if($element["config"]["required"] && !$concatPhone){
                    return array('status' => false,
                                'message'=> $element["external_id"] . ' field is required and should not null or empty.');
                }
            }
            else{
                return array('status' => false,
                             'message'=> $element["external_id"] . ' field is not a valid input data of phone.');
            }
        }
        return array('status' => true,
                    'message'=> 'Successfully validated user input data');
    }
    /**
     * Function to render input of wePhone
     * @date 13-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  object $user
     * @return array
     */
    public function renderInput($user){
        $data = $this->data;
        $element = $this->element;
        //Setting of wePhone for multiple input
        if($element["config"]["custom"]["multiple"]){
            //loop array of phone data
            $dataResult = array();
            foreach($data[$element["external_id"]] as $ctrlPhone){
                $wePhoneObj  = new SchemaRender($this->schemaWePhone);
                $obj  = new \stdClass();
                $objReturn = $wePhoneObj->renderInput($ctrlPhone, $obj, $user, "");
                array_push($dataResult, (array)$objReturn);
            }
            return $dataResult;
        }
        //Setting of wePhone for single input
        else{
            return $data[$element["external_id"]];
        }
    }
    /**
     * Function to render output of wePhone
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
}
