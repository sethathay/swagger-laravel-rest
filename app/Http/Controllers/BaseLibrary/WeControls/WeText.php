<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\BaseLibrary\WeControls;

class WeText {
    
    private $data;
    private $element;
    
    public function __construct($data, $element) {
        $this->data = $data;
        $this->element = $element;
    }
    /**
     * Function to validate weText
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
        
        if(!$this->validateString($data[$element["external_id"]])){            
            return array('status' => false,
                         'message'=> $element["external_id"] . ' field is requird string data type.');
        }
        return array('status' => true,
                    'message'=> 'Successfully validated user input data');
    }
    /**
     * Function to render input of weText
     * @date 13-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return string
     */
    public function renderInput(){
        $data = $this->data;
        $element = $this->element;
        return $data[$element["external_id"]];
    }
    /**
     * Function to render output of weText
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
     * Function to validate string data type
     * @date 12-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to validate
     * @return boolean
     */
    private function validateString($data){
        return is_string($data);
    }
}
