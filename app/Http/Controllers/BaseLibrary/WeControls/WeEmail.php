<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\BaseLibrary\WeControls;

use App\Http\Controllers\BaseLibrary\SchemaRender;

class WeEmail {
    
    private $data;
    private $element;
    private $schemaWeEmail = "VWEEMAIL";
    
    public function __construct($data, $element) {
        $this->data = $data;
        $this->element = $element;
    }
    /**
     * Function to validate weEmail
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
        
        //Setting of weEmail for multiple input
        if($element["config"]["custom"]["multiple"]){
            //Loop to get each email value
            foreach($data[$element["external_id"]] as $ctrlEmail){
                //It's required to have field type and value
                if(isset($ctrlEmail["type"]) && isset($ctrlEmail["value"])){
                    //Condition invalid of validation input data of udc type email
                    $weEmailObj  = new SchemaRender($this->schemaWeEmail);
                    $validateObj = $weEmailObj->validate($ctrlEmail);
                    if(!$validateObj["status"]){
                        return $validateObj;
                    }
                    //Condition valid of validation input data of udc type email
                    else{
                        //Schema field is required and input data is empty/null
                        if($element["config"]["required"] && !$ctrlEmail["value"]){
                            return array('status' => false,
                                        'message'=> $element["external_id"] . ' field is required and should not null or empty.');
                        }
                        else{
                            //Condition invalid of validation input email value
                            if(!$this->validateEmail($ctrlEmail["value"])){
                                return array('status' => false,
                                             'message'=> $element["external_id"] . ' field is not a valid value of email address.');
                            }
                        }
                    }
                }
                else{
                    return array('status' => false,
                             'message'=> $element["external_id"] . ' field is not a valid input data of email address.');
                }
            }
        }
        //Setting of weEmail for single input
        else{
            if(!$this->validateEmail($data[$element["external_id"]])){
                return array('status' => false,
                             'message'=> $element["external_id"] . ' field is not a valid value of email address.');
            }
        }
        return array('status' => true,
                    'message'=> 'Successfully validated user input data');
    }
    /**
     * Function to render input of weEmail
     * @date 13-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  object $user
     * @return array
     */
    public function renderInput($user){
        $data = $this->data;
        $element = $this->element;
        //Setting of weEmail for multiple input
        if($element["config"]["custom"]["multiple"]){
            //Loop to get each email value
            $dataResult = array();
            foreach($data[$element["external_id"]] as $ctrlEmail){
                $weEmailObj  = new SchemaRender($this->schemaWeEmail);
                $obj  = new \stdClass();
                $objReturn = $weEmailObj->renderInput($ctrlEmail, $obj, $user, "");
                array_push($dataResult, (array)$objReturn);
            }
            return $dataResult;
        }
        //Setting of weEmail for single input
        else{
            return $data[$element["external_id"]];
        }
    }
    /**
     * Function to render output of weEmail
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
     * Function to validate email address
     * @date 12-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to validate
     * @return boolean
     */
    private function validateEmail($data){
        return filter_var($data,FILTER_VALIDATE_EMAIL);
    }
}
