<?php

/**
* Class of ObjectLibraries , Super class that will all properties and methods for type of object like
* Program, Report, Table, and Project
*
* @since      Class available since Release 1.0.0
* @deprecated Class deprecated in Release 1.0.0
*/
namespace App\Http\Controllers\BaseLibrary;
use DateTime;
use DateTimeZone;
use App\Http\Controllers\Helper\ErrorHelper;
use App\Http\Controllers\ObjLibrary\Schema;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\WeControls\WeText;
use App\Http\Controllers\BaseLibrary\WeControls\WeUdc;
use App\Http\Controllers\BaseLibrary\WeControls\WePhone;
use App\Http\Controllers\BaseLibrary\WeControls\WeEmail;
use App\Http\Controllers\BaseLibrary\WeControls\WeLink;
use App\Http\Controllers\BaseLibrary\WeControls\WeAddress;
use App\Http\Controllers\BaseLibrary\WeControls\WeSocial;
use App\Http\Controllers\BaseLibrary\WeControls\WeNumber;
use App\Http\Controllers\BaseLibrary\WeControls\WeCurrency;
use App\Http\Controllers\BaseLibrary\WeControls\WeDate;
use App\Http\Controllers\BaseLibrary\WeControls\WeLookup;
use App\Http\Controllers\BaseLibrary\WeControls\WeEmbedded;

class SchemaRender{
   
    private $schema;
    private $fields;
    
    public function __construct($requestedSchema = "") {
        $this->schema = $this->getSchema($requestedSchema);
        if($this->schema){
            $this->setAllFields($this->schema->fields);
        }
    }
    
    public function getSingularName(){
        return $this->schema->resource["singular"];
    }
    
    public function getPluralName(){
        return $this->schema->resource["plural"];
    }
    
    public function setAllFields($fields){
        $this->fields = $fields;
    }
    /**
     * Function get schema from object library collection using Model Schema
     * @date 12-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var requestedSchema is name of schema
     * @return object of schema
     */
    private function getSchema($requestedSchema){
        $obj = new Schema();
        $criteria = new CriteriaOption();
        $criteria->where('name',$requestedSchema);
        $obj->pushCriteria($criteria);
        $obj->applyCriteria();
        //Read all but return only first found result
        return $obj->readOne();
    }
    /**
     * Function get field id (attributes) of schema from object library collection
     * @date 31-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return array
     */
    public function getAttributes(){
        $fieldList = array();
        foreach($this->fields as $field){
            array_push($fieldList, $field["field_id"]);
        }
        return $fieldList;
    }
    /**
     * Function get all fields of schema from object library collection
     * @date 31-Oct-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  -
     * @return array
     */
    public function getAllFields(){
        return $this->fields;
    }
    /**
     * Function to validate input data againt schema JSON
     * @date 12-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is user input data to validate
     * @return array of validation message
     */
    public function validate($data){
        if($data){
            //check existing of schema json collection
            if(isset($this->fields) && $this->fields != ""){
                foreach($this->fields as $element){
                    //Condition of schema field is not exist in data input
                    if(!array_key_exists($element["external_id"], $data)){
                        //Condition when schema field is a required field
                        if($element["config"]["required"]){
                            return array('status' => false,
                                    'message'=> $element["external_id"] . ' field is required.');
                        }
                    }
                    //Condition of schema field is exist in data input
                    else{
                        //Allow null value for data input for all weControl (Purpose for specific of weEmbedded in function renderOutput)
                        //No need to validate by weControl item
                        if($data[$element["external_id"]] == null){ continue; }
                        //Condition when schema field is a required field and data input is empty
                        if($element["config"]["required"] && empty($data[$element["external_id"]])){
                            return array('status' => false,
                                    'message'=> $element["external_id"] . ' field is required and should not empty.');
                        }
                        switch($element["edit_type"]){
                            case "weText" :
                                $weTextObj = new WeText($data, $element);
                                $resultValidate = $weTextObj->validate();
                                if(!$resultValidate['status']){                                     
                                    return $resultValidate; }
                                break;
                            case "weUdc" :
                                $weUdcObj = new WeUdc($data, $element);
                                $resultValidate = $weUdcObj->validate();
                                if(!$resultValidate['status']){ return $resultValidate; }
                                break;
                            case "wePhone" :
                                $wePhoneObj = new WePhone($data, $element);
                                $resultValidate = $wePhoneObj->validate();
                                if(!$resultValidate['status']){ return $resultValidate; }
                                break;
                            case "weEmail" :
                                $weEmailObj = new WeEmail($data, $element);
                                $resultValidate = $weEmailObj->validate();
                                if(!$resultValidate['status']){ return $resultValidate; }
                                break;
                            case "weLink" :
                                $weLinkObj = new WeLink($data, $element);
                                $resultValidate = $weLinkObj->validate();
                                if(!$resultValidate['status']){ return $resultValidate; }
                                break;
                            case "weAddress" :
                                $weAddressObj = new WeAddress($data, $element);
                                $resultValidate = $weAddressObj->validate();
                                if(!$resultValidate['status']){ return $resultValidate; }
                                break;
                            case "weSocial" :
                                $weSocialObj = new WeSocial($data, $element);
                                $resultValidate = $weSocialObj->validate();
                                if(!$resultValidate['status']){ return $resultValidate; }
                                break;
                            case "weNumber" :
                                $weNumberObj = new WeNumber($data, $element);
                                $resultValidate = $weNumberObj->validate();
                                if(!$resultValidate['status']){ return $resultValidate; }
                                break;
                            case "weCurrency" :
                                $weCurrencyObj = new WeCurrency($data, $element);
                                $resultValidate = $weCurrencyObj->validate();
                                if(!$resultValidate['status']){ return $resultValidate; }
                                break;
                            case "weDate" :
                                $weDateObj = new WeDate($data, $element);
                                $resultValidate = $weDateObj->validate();
                                if(!$resultValidate['status']){ return $resultValidate; }
                                break;
                            case "weLookup" :
                                $weLookupObj = new WeLookup($data, $element);
                                $resultValidate = $weLookupObj->validate();
                                if(!$resultValidate['status']){ return $resultValidate; }
                                break;
                            case "weEmbedded" :
                                $weEmbeddedObj = new WeEmbedded($data, $element);
                                $resultValidate = $weEmbeddedObj->validate();
                                if(!$resultValidate['status']){ return $resultValidate; }
                                break;
                            default :
                                break;
                        }
                    }
                }
                return array('status' => true,
                             'message'=> 'Successfully validated user input data');
            }            
        }else{
            return array('status' => false,
                        'message'=> 'There is no input data.');
        }
    }
    /**
     * Function to automatic add timestamps and userstamps
     * @date 12-Sept-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is user input data to validate     
     * @param  object $user is a user object
     * @param  string $crud is crud operator ("C" for create, "U" for update)
     * @return data with timestamps and userstamps
     */
    private function addTimeStamps($data, $user, $crud){
        //Automatic added timestamps and userstamps 
        $tz = "UTC";
        if(isset($user->time_zone)) $tz = $user->time_zone["label"];
        $dt = new DateTime("now",new DateTimeZone($tz));
        switch($crud){
            //CRUD process is "CREATE"
            case "C" :
                $data["createdBy"] = $user->id;
                $data["updatedBy"] = $user->id;
                $data["createdAt"] = $dt->format('Y-m-d H:i:s');
                $data["updatedAt"] = $dt->format('Y-m-d H:i:s');
                break;
            //CRUD process is "UPDATE"
            case "U" :
                $data["updatedBy"] = $user->id;
                $data["updatedAt"] = $dt->format('Y-m-d H:i:s');
                break;
        }
        return $data;
    }
    /**
     * Function to render input data before saving to database
     * @date 12-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is user input data to validate
     * @param  object $obj is a eloquent object
     * @param  object $user is a user object
     * @param  string $crud is crud operator ("C" for create, "U" for update)
     * @return eloquent object to be save to database
     */
    public function renderInput($data, $obj, $user, $crud){        
        if($data){
            //Automatically adding timestamps and userstamps
            $data = $this->addTimeStamps($data, $user, $crud);
            //check existing of schema json collection
            if(isset($this->fields) && $this->fields != ""){
                foreach($this->fields as $element){
                    if(array_key_exists($element["external_id"], $data)){
                        //Checking edit type of each schema json
                        switch($element["edit_type"]){
                            case "weText" :
                                $weTextObj = new WeText($data, $element);
                                $obj->$element["field_id"] = $weTextObj->renderInput();                                                                
                                break;
                            case "weUdc" :
                                $weUdcObj = new WeUdc($data, $element);
                                $obj->$element["field_id"] = $weUdcObj->renderInput();
                                break;
                            case "wePhone" :
                                $wePhoneObj = new WePhone($data, $element);
                                $obj->$element["field_id"] = $wePhoneObj->renderInput($user);
                                break;
                            case "weEmail" :
                                $weEmailObj = new WeEmail($data, $element);
                                $obj->$element["field_id"] = $weEmailObj->renderInput($user);
                                break;
                            case "weLink" :
                                $weLinkObj = new WeLink($data, $element);
                                $obj->$element["field_id"] = $weLinkObj->renderInput();
                                break;
                            case "weAddress" :
                                $weAddressObj = new WeAddress($data, $element);
                                $obj->$element["field_id"] = $weAddressObj->renderInput($user);
                                break;
                            case "weSocial" :
                                $weSocialObj = new WeSocial($data, $element);
                                $obj->$element["field_id"] = $weSocialObj->renderInput($user);
                                break;
                            case "weNumber" :
                                $weNumberObj = new WeNumber($data, $element);
                                $obj->$element["field_id"] = $weNumberObj->renderInput();
                                break;
                            case "weCurrency" :
                                $weCurrencyObj = new WeCurrency($data, $element);
                                $obj->$element["field_id"] = $weCurrencyObj->renderInput($user);
                                break;
                            case "weDate" :
                                $weDateObj = new WeDate($data, $element);
                                $obj->$element["field_id"] = $weDateObj->renderInput($user);
                                break;
                            case "weLookup" :
                                $weLookupObj = new WeLookup($data, $element);
                                $obj->$element["field_id"] = $weLookupObj->renderInput();
                                break;
                            case "weEmbedded" :
                                $weEmbeddedObj = new WeEmbedded($data, $element);
                                $obj->$element["field_id"] = $weEmbeddedObj->renderInput($user);
                                break;
                            default:
                                $obj->$element["field_id"] = $data[$element["external_id"]];
                                break;
                        }
                    }else{
                        //Add default value to field that is not exist in user's request data
                        //execpt _id field and crud operation is "C" (CREATE) or "" (FOR SUB SCHEMA)
                        if($element["field_id"] != "_id" && ($crud == "C" || $crud == "")){
                            $obj->$element["field_id"] = $element["config"]["default_value"];
                        }
                    }
                }
            }
        }else{
            return ErrorHelper::getRequestBodyError();
        }
        return $obj;
    }
    /**
     * Function to render output json data before sending out to client call
     * @date 12-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  object $obj is a eloquent object
     * @param  string $href is a unique string represent restful resource
     * @return array to be send out to client call
     */
    public function renderOutput($obj,$href,$user){
        $outputJson = array();
        //check existing of schema json collection
        if(isset($this->fields) && $this->fields != ""){
            if($href != ""){ $outputJson["href"] = $href; }
            foreach($this->fields as $element){
                if(isset($obj->$element["field_id"])){
                    switch($element["edit_type"]){
                        case "weText" :
                            $weTextObj = new WeText($obj, $element);
                            $outputJson[$element["external_id"]] = $weTextObj->renderOutput();
                            break;
                        case "weUdc" :
                            $weUdcObj = new WeUdc($obj, $element);
                            $outputJson[$element["external_id"]] = $weUdcObj->renderOutput();
                            break;
                        case "wePhone" :
                            $wePhoneObj = new WePhone($obj, $element);
                            $outputJson[$element["external_id"]] = $wePhoneObj->renderOutput();
                            break;
                        case "weEmail" :
                            $weEmailObj = new WeEmail($obj, $element);
                            $outputJson[$element["external_id"]] = $weEmailObj->renderOutput();
                            break;
                        case "weLink" :
                            $weLinkObj = new WeLink($obj, $element);
                            $outputJson[$element["external_id"]] = $weLinkObj->renderOutput();
                            break;
                        case "weAddress" :
                            $weAddressObj = new WeAddress($obj, $element);
                            $outputJson[$element["external_id"]] = $weAddressObj->renderOutput();
                            break;
                        case "weSocial" :
                            $weSocialObj = new WeSocial($obj, $element);
                            $outputJson[$element["external_id"]] = $weSocialObj->renderOutput();
                            break;
                        case "weNumber" :
                            $weNumberObj = new WeNumber($obj, $element);
                            $outputJson[$element["external_id"]] = $weNumberObj->renderOutput();
                            break;
                        case "weCurrency" :
                            $weCurrencyObj = new WeCurrency($obj, $element);
                            $outputJson[$element["external_id"]] = $weCurrencyObj->renderOutput();
                            break;
                        case "weDate" :
                            $weDateObj = new WeDate($obj, $element);
                            $outputJson[$element["external_id"]] = $weDateObj->renderOutput($user);
                            break;
                        case "weLookup" :
                            $weLookupObj = new WeLookup($obj, $element);
                            $outputJson[$element["external_id"]] = $weLookupObj->renderOutput();
                            break;
                        case "weEmbedded" :
                            $weEmbeddedObj = new WeEmbedded($obj, $element);
                            $outputJson[$element["external_id"]] = $weEmbeddedObj->renderOutput($user);
                            break;
                        default :
                            $outputJson[$element["external_id"]] = $obj->$element["field_id"];
                            break;
                    }
                }
                else{
                    $outputJson[$element["external_id"]] = $obj->$element["field_id"];
                }
            }
        }
        return $outputJson;
    }
    /**
     * Function to render output json dataset before sending out to client call
     * @date 24-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  schema $schema is a object schema for rendering
     * @param  string $dsHref is a href of dataset
     * @param  int $offset is offset of dataset
     * @param  int $limit is limit of dataset
     * @return array to be send out to client call
     */
    public function renderOutputDS($schema, $ds, $dsHref, $dsCount, $offset, $limit,
                                   $user, $expandText = "", $expandSchema = "", 
                                   $expandField = "", $expandObj = ""){
        // Organize Data
        $resultSet = array();
        $resultSet['href'] = $dsHref;
        $resultSet['offset'] = $offset;
        $resultSet['limit'] = $limit;
        $resultSet['size'] = $dsCount;
        $resultSet['items'] = [];
        
        foreach ($ds as $dataSource) {
            $href = $dsHref . "/" . $dataSource->_id;
            $arr = $schema->renderOutput($dataSource,$href,$user);
            //Expand items with embeded model (Ex: project embeded task)
            //Expand list of items (Ex: expand task)
            if(!empty($expandText) && empty($expandObj)){
                $expandRender = new SchemaRender($expandSchema);
                $criteria = new CriteriaOption();
                $expandHref = $href . "/" . $expandText;
                $arr[$expandText] = $this->renderOutputDS($expandRender,$dataSource->$expandField,$expandHref,
                                    count($dataSource->$expandField),$criteria->offset(),$criteria->limit(),$user);
            }
            //Expand items with embeded model (Ex: project embeded task)
            //Expand one of master item (Ex: expand project)
            elseif(!empty($expandText) && !empty($expandObj)){
                $expandRender = new SchemaRender($expandSchema);
                $url = explode("/", $dsHref);
                array_pop($url);
                $expandHref = implode("/", $url);
                $arr[$expandText] = $expandRender->renderOutput($expandObj, $expandHref, $user);
            }
            $resultSet['items'][] = $arr;
        }
        return $resultSet;
    }
}
