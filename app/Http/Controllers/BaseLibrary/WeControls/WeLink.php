<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\BaseLibrary\WeControls;

class WeLink {
    
    private $data;
    private $element;
    
    public function __construct($data, $element) {
        $this->data = $data;
        $this->element = $element;
    }
    /**
     * Function to validate weLink
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
        
        //Setting of weLink for multiple input
        if($element["config"]["custom"]["multiple"]){
            //Loop to get each link value
            foreach($data[$element["external_id"]] as $ctrlLink){
                if(isset($ctrlLink["value"])){
                    if($element["config"]["required"] && !$ctrlLink["value"]){
                        return array('status' => false,
                                    'message'=> $element["external_id"] . ' field is required and should not null or empty.');
                    }else{
                        if(!$this->validateLink($ctrlLink["value"])){
                            return array('status' => false,
                                         'message'=> $element["external_id"] . ' field is not a valid value of link url.');
                        }
                    }
                }
                else{
                    return array('status' => false,
                                'message'=> $element["external_id"] . ' field is not a valid input data of link.');
                }
            }
        }
        //Setting of weLink for single input
        else{
            if(!$this->validateLink($data[$element["external_id"]])){
                return array('status' => false,
                             'message'=> $element["external_id"] . ' field is not a valid value of link url.');
            }
        }
        return array('status' => true,
                    'message'=> 'Successfully validated user input data');
    }
    /**
     * Function to render input of weLink
     * @date 13-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return array
     */
    public function renderInput(){
        $data = $this->data;
        $element = $this->element;
        return $data[$element["external_id"]];
    }
    /**
     * Function to render output of weLink
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
     * Function to validate link url
     * @date 12-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to validate
     * @return boolean
     */
    private function validateLink($data){
        return filter_var($data,FILTER_VALIDATE_URL);
    }
}
