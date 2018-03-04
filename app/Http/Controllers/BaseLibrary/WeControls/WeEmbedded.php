<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\BaseLibrary\WeControls;

use App\Http\Controllers\BaseLibrary\SchemaRender;

class WeEmbedded {
    
    private $data;
    private $element;
    private $subSchema = "";
    
    public function __construct($data, $element) {
        $this->data = $data;
        $this->element = $element;
    }
    /**
     * Function to validate weEmbedded
     * @date 15-Nov-2017
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
        //Setting of weEmbedded for multiple input
        if($element["config"]["custom"]["multiple"]){
            //Loop to get each embedded value
            foreach($data[$element["external_id"]] as $ctrlEmbedded){
                $validateObj = $this->validateEmbedded($ctrlEmbedded, $element);
                if(!$validateObj["status"]){
                    return $validateObj;
                }
            }
        }
        //Setting of weEmbedded for single input
        else{
            $validateObj = $this->validateEmbedded($data[$element["external_id"]], $element);
            if(!$validateObj["status"]){
                return $validateObj;
            }
        }
        return array('status' => true,
                    'message'=> 'Successfully validated user input data');
    }
    /**
     * Function to render input of weEmbedded
     * @date 15-Nov-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return array of validation message
     */
    public function renderInput($user){
        $data = $this->data;
        $element = $this->element;
        //Setting of weEmbedded for multiple input
        if($element["config"]["custom"]["multiple"]){
            //Loop to get each embedded value
            $dataResult = array();
            foreach($data[$element["external_id"]] as $ctrlEmbedded){
                $this->validateEmbedded($ctrlEmbedded, $element);
                $weEmbeddedObj  = new SchemaRender($this->subSchema);
                $obj  = new \stdClass();
                $objReturn = $weEmbeddedObj->renderInput($ctrlEmbedded, $obj, $user, "");
                array_push($dataResult, $objReturn);
            }
            return $dataResult;
        }
        //Setting of weEmbedded for single input
        else{
            $this->validateEmbedded($data[$element["external_id"]], $element);
            $weEmbeddedObj  = new SchemaRender($this->subSchema);
            $obj  = new \stdClass();
            $objReturn = $weEmbeddedObj->renderInput($data[$element["external_id"]], $obj, $user, "");
            return $objReturn;
        }
    }
    /**
     * Function to render output of weEmbedded
     * @date 15-Nov-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return array of validation message
     */
    public function renderOutput($user){
        $data = $this->data;
        $element = $this->element;
        //Setting of weEmbedded for multiple input
        if($element["config"]["custom"]["multiple"]){
            //Loop to get each embedded value
            $dataResult = array();
            foreach($data->$element["external_id"] as $ctrlEmbedded){
                $this->validateEmbedded($ctrlEmbedded, $element);
                $weEmbeddedObj  = new SchemaRender($this->subSchema);
                $objReturn = $weEmbeddedObj->renderOutput((object)$ctrlEmbedded, "", $user);
                array_push($dataResult, $objReturn);
            }
            return $dataResult;
        }
        //Setting of weEmbedded for single input
        else{
            $this->validateEmbedded($data->$element["field_id"], $element);
            $weEmbeddedObj  = new SchemaRender($this->subSchema);
            return $weEmbeddedObj->renderOutput((object)$data->$element["field_id"], "", $user);
        }
    }
    /**
     * Function to validate weEmbedded
     * @date 15-Nov-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return array of validation message
     */
    private function validateEmbedded($data, $element){
        if(gettype($data) == "array"){
            $i = 1;
            foreach($element["config"]["custom"]["schemas"] as $subSchema){
                $weEmbeddedObj = new SchemaRender($subSchema["value"]);
                $validateObj = $weEmbeddedObj->validate($data);
                if(!$validateObj["status"] && count($element["config"]["custom"]["schemas"]) == $i){
                    return $validateObj;
                }elseif($validateObj["status"]){
                    $this->subSchema = $subSchema["value"];
                    break;
                }
                $i++;
            }
        }else{
            return array('status' => false,
                         'message'=> $element["external_id"] . ' field is not a valid input data.');
        }
        return array('status' => true,
                    'message'=> 'Successfully validated user input data');
    }
}
