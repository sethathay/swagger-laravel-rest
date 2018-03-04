<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\BaseLibrary\WeControls;

use App\Http\Controllers\BaseLibrary\SchemaRender;

class WeAddress {
    
    private $data;
    private $element;
    private $schemaWeAddress = "VWEADDRESS";
    
    public function __construct($data, $element) {
        $this->data = $data;
        $this->element = $element;
    }
    /**
     * Function to validate weAddress
     * @date 12-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is user input data to validate
     * @param  var $element is schema for weAddress
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
        
        //Setting of weAddress for multiple input
        if($element["config"]["custom"]["multiple"]){
            //loop array of address data
            foreach($data[$element["external_id"]] as $ctrlAddress){
                if(isset($ctrlAddress["type"]) && isset($ctrlAddress["street"])
                   && isset($ctrlAddress["city"]) && isset($ctrlAddress["state"])
                   && isset($ctrlAddress["zipPostal"]) && isset($ctrlAddress["country"])){
                    $weAddressObj  = new SchemaRender($this->schemaWeAddress);
                    $validateObj   = $weAddressObj->validate($ctrlAddress);
                    if(!$validateObj["status"]){
                        return $validateObj;
                    }else{
                        $concateAddress = trim( $ctrlAddress["street"] . 
                                                $ctrlAddress["city"] . 
                                                $ctrlAddress["state"] . 
                                                $ctrlAddress["zipPostal"] .
                                                $ctrlAddress["country"]
                                            );
                        if($element["config"]["custom"]["attention"]){
                            if(isset($ctrlAddress["attention"])){
                                $concateAddress .= $ctrlAddress["attention"];
                            }else{
                                return array('status' => false,
                                     'message'=> $element["external_id"] . ' field is not a valid input data of address.');
                            }
                        }
                        if($element["config"]["required"] && !$concateAddress){
                            return array('status' => false,
                                        'message'=> $element["external_id"] . ' field is required and should not null or empty.');
                        }
                    }
                }
                else{
                    return array('status' => false,
                            'message'=> $element["external_id"] . ' field is not a valid input data of address.');
                }
            }
        }
        //Setting of weAddress for single input
        else{
            if(isset($data[$element["external_id"]]["street"]) 
                    && isset($data[$element["external_id"]]["city"])
                    && isset($data[$element["external_id"]]["state"])
                    && isset($data[$element["external_id"]]["zipPostal"])
                    && isset($data[$element["external_id"]]["country"])){
                $concateAddress = trim( $data[$element["external_id"]]["street"] . 
                                        $data[$element["external_id"]]["city"] . 
                                        $data[$element["external_id"]]["state"] . 
                                        $data[$element["external_id"]]["zipPostal"] .
                                        $data[$element["external_id"]]["country"]
                                    );
                if($element["config"]["custom"]["attention"]){
                    if(isset($data[$element["external_id"]]["attention"])){
                        $concateAddress .= $data[$element["external_id"]]["attention"];
                    }
                    else{
                        return array('status' => false,
                             'message'=> $element["external_id"] . ' field is not a valid input data of address.');
                    }
                }
                if($element["config"]["required"] && !$concateAddress){
                    return array('status' => false,
                                'message'=> $element["external_id"] . ' field is required and should not null or empty.');
                }
            }
            else{
                return array('status' => false,
                             'message'=> $element["external_id"] . ' field is not a valid input data of address.');
            }
        }
        return array('status' => true,
                    'message'=> 'Successfully validated user input data');
    }
    /**
     * Function to render input of weAddress
     * @date 13-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  object $user
     * @return array
     */
    public function renderInput($user){
        $data = $this->data;
        $element = $this->element;
        //Setting of weAddress for multiple input
        if($element["config"]["custom"]["multiple"]){
            //loop array of address data
            $dataResult = array();
            foreach($data[$element["external_id"]] as $ctrlAddress){
                $weAddressObj  = new SchemaRender($this->schemaWeAddress);
                $obj  = new \stdClass();
                $objReturn = $weAddressObj->renderInput($ctrlAddress, $obj, $user, "");
                if(!$element["config"]["custom"]["attention"]){
                    if(isset($objReturn->attention)){ unset($objReturn->attention); }
                }
                array_push($dataResult, (array)$objReturn);
            }
            return $dataResult;
        }
        //Setting of weAddress for single input
        else{
            return $data[$element["external_id"]];
        }
    }
    /**
     * Function to render output of weAddress
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
