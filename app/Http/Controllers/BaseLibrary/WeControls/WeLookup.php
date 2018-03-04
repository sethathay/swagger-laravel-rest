<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\BaseLibrary\WeControls;

use Illuminate\Http\Request;
use App\Http\Controllers\ObjLibrary\Schema;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;

class WeLookup {
    
    private $data;
    private $element;
    
    public function __construct($data, $element) {
        $this->data = $data;
        $this->element = $element;
    }
    /**
     * Function to validate weLookup
     * @date 31-Oct-2017
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
        
        //Setting of weLookup for multiple input
        if($element["config"]["custom"]["multiple"]){
            //Loop to get each lookup value
            $lookupData = $data;
            foreach($data[$element["external_id"]] as $ctrlLookup){
                $lookupData[$element["external_id"]] = $ctrlLookup;
                $validateObj = $this->validateLookup($lookupData, $element);
                if(!$validateObj["status"]){
                    return $validateObj;
                }
            }
        }
        //Setting of weLookup for single input
        else{
            $validateObj = $this->validateLookup($data, $element);
            if(!$validateObj["status"]){
                return $validateObj;
            }
        }
        return array('status' => true,
                    'message'=> 'Successfully validated user input data');
    }
    /**
     * Function to render input of weLookup
     * @date 01-Nov-2017
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
     * Function to validate lookup for multiple value
     * @date 15-Dec-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to validate
     * @return array of validation message
     */
    private function validateLookup($data, $element){
        $valueParams = array();
        $datasource = $element["config"]["custom"]["datasource"];
        $obj = new Schema();
        $criteria = new CriteriaOption();
        $criteria->where('datasource', $datasource);
        $obj->pushCriteria($criteria);
        $obj->applyCriteria();
        $schemaObj = $obj->readOne();
        $controller = $schemaObj->crud['controller'];
        $function = $schemaObj->crud["o"]['function'];
        $params = $schemaObj->crud["o"]['params'];
        $controllerObj = new $controller(new Request());
        foreach($params as $param){
            $key = $param['key'];
            array_push($valueParams, $data[$key]);
        }
        $lookupObj = $controllerObj->callAction($function, $valueParams);
        if(isset($lookupObj->getData()->href)){
            return array('status' => true,
                        'message'=> 'Successfully validated user input data');
        }
        return array('status' => false,
                    'message'=> $element["external_id"] . ' field is not a valid value.');
    }
}
