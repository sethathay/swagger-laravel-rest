<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\BaseLibrary\WeControls;

use App\Http\Controllers\BaseLibrary\SchemaRender;

class WeSocial {
    
    private $data;
    private $element;
    private $schemaWeSocial = "VWESOCIAL";
    
    public function __construct($data, $element) {
        $this->data = $data;
        $this->element = $element;
    }
    /**
     * Function to validate weSocial
     * @date 12-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
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
        
        //Setting of weSocial for multiple input
        if($element["config"]["custom"]["multiple"]){
            //Loop to get each social value
            foreach($data[$element["external_id"]] as $ctrlSocial){
                $validateObj = $this->validateSocial($ctrlSocial, $element);
                if(!$validateObj["status"]){
                    return $validateObj;
                }
            }
        }
        //Setting of weSocial for single input
        else{
            $validateObj = $this->validateSocial($data[$element["external_id"]], $element);
            if(!$validateObj["status"]){
                return $validateObj;
            }
        }
        return array('status' => true,
                    'message'=> 'Successfully validated user input data');
    }
    /**
     * Function to render input of weSocial
     * @date 13-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  object $user
     * @return array
     */
    public function renderInput($user){
        $data = $this->data;
        $element = $this->element;
        //Setting of weSocial for multiple input
        if($element["config"]["custom"]["multiple"]){
            //Loop to get each social value
            $dataResult = array();
            foreach($data[$element["external_id"]] as $ctrlSocial){
                $weSocialObj  = new SchemaRender($this->schemaWeSocial);
                $obj  = new \stdClass();
                $objReturn = $weSocialObj->renderInput($ctrlSocial, $obj, $user, "");
                array_push($dataResult, (array)$objReturn);
            }
            return $dataResult;
        }
        //Setting of weSocial for single input
        else{
            $weSocialObj  = new SchemaRender($this->schemaWeSocial);
            $obj  = new \stdClass();
            $objReturn = $weSocialObj->renderInput($data[$element["external_id"]], $obj, $user, "");
            return $objReturn;
        }
    }
    /**
     * Function to render output of weSocial
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
     * Function to validate social
     * @date 13-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to validate
     * @return array of validation message
     */
    private function validateSocial($data,$element){
        if(isset($data["type"]) && isset($data["value"])){
            $weSocialObj  = new SchemaRender($this->schemaWeSocial);
            $validateObj = $weSocialObj->validate($data);
            if(!$validateObj["status"]){
                return $validateObj;
            }
            else{
                if($element["config"]["required"] && !$data["value"]){
                    return array('status' => false,
                                'message'=> $element["external_id"] . ' field is required and should not null or empty.');
                }
            }
        }else{
            return array('status' => false,
                         'message'=> $element["external_id"] . ' field is not a valid input data of social.');
        }
        return array('status' => true,
                    'message'=> 'Successfully validated user input data');
    }
}
