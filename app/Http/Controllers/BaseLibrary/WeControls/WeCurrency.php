<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\BaseLibrary\WeControls;

use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\BaseLibrary\WeControls\WeNumber;

class WeCurrency {
    
    private $data;
    private $element;
    private $schemaWeCurrency = "VWECURRENCY";
    
    public function __construct($data, $element) {
        $this->data = $data;
        $this->element = $element;
    }
    /**
     * Function to validate weCurrency
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
        
        if(isset($data[$element["external_id"]]["type"]) && isset($data[$element["external_id"]]["value"])){
            $weCurrencyObj  = new SchemaRender($this->schemaWeCurrency);
            $validateObj   = $weCurrencyObj->validate($data[$element["external_id"]]);
            if(!$validateObj["status"]){
                return $validateObj;
            }else{
                if($element["config"]["required"] && !$data[$element["external_id"]]["value"]){
                    return array('status' => false,
                                'message'=> $element["external_id"] . ' field is required and should not null or empty.');
                }
                else{
                    $weNumberObj = new WeNumber(null, null);
                    if(!$weNumberObj->validateNumber($data[$element["external_id"]]["value"])){
                        return array('status' => false,
                                     'message'=> $element["external_id"] . ' field is requird number data type.');
                    }
                }
            }
        }else{
            return array('status' => false,
                        'message'=> $element["external_id"] . ' field is not a valid input data of currency.');
        }
        return array('status' => true,
                    'message'=> 'Successfully validated user input data');
    }
    /**
     * Function to render input of weCurrency
     * @date 13-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  object $user
     * @return currency
     */
    public function renderInput($user){
        $data = $this->data;
        $element = $this->element;
        $weCurrencyObj  = new SchemaRender($this->schemaWeCurrency);
        $obj  = new \stdClass();
        $objReturn = $weCurrencyObj->renderInput($data[$element["external_id"]], $obj, $user, "");
        return $objReturn;
    }
    /**
     * Function to render output of weCurrency
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
