<?php

/**
* Class of Programs that extend class ObjectLibraries
*
* 
* 
* LICENSE: Some license information
*
* @category   PHP
* @package    
* @copyright  Copyright (c) 2016 WorkEvolve
* @license    http://workevolve.com/license   BSD License
* @version    1.0.0
* @since      File available since Release 1.0.0

*/
namespace App\Http\Controllers\BaseLibrary\CriteriaOption;

use App\Http\Controllers\BaseLibrary\AbstractCriteria;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\BaseLibrary\WeControls\WeDate;

class CriteriaOption extends AbstractCriteria
{
    private $limit = 25;
    
    private $offset = 0;
    
    private $orderBy;
    
    private $orderMode;

    private $groupBy;

    private $where = array();
    
    private $orWhere = array();
    
    private $whereIn = array();
    
    private $whereBetween = array();
    
    private $whereRaw = array();

    /**
     * Use for implement criteria option.
     * Ex: where, orWhere, whereBetween, orderBy, groupBy,...
     * @date 20-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @param  $model
     * @return object model.
     */
    public function apply($model)
    {
        // This condtion use to apply where clause to object filter data 
        if(!empty($this->where))
        {
            foreach($this->where as $value)
            {
                $count = count($value);
                switch ($count){
                    case 2:
                        foreach ($value as $key => $val){
                            $model->where($key, $val);
                        }
                        break;
                    case 3:
                        foreach ($value as $key => $val){
                            $model->where($value[0], $value[1], $value[2]);
                        }
                        break;
                    default:
                        foreach ($value as $key => $val){
                            $model->where($key, $val);
                        }
                        break;
                }
            }
        }

        // This condtion use to apply orWhere clause to object filter data
        if(!empty($this->orWhere))
        {
            foreach($this->orWhere as $value)
            {
                $count = count($value);
                switch ($count){
                    case 2:
                        foreach ($value as $key => $val){
                            $model->orWhere($key, $val);
                        }
                        break;
                    case 3:
                        foreach ($value as $key => $val){
                            $model->orWhere($value[0], $value[1], $value[2]);
                        }
                        break;
                    default:
                        foreach ($value as $key => $val){
                            $model->orWhere($key, $val);
                        }
                        break;
                }
            }
        }
        
        // This condtion use to apply the kind of where custom to filter data
        if(!empty($this->whereIn))
        {
            foreach($this->whereIn as $param)
            {
                for($i=1; $i<count($param); $i++)
                {
                    $model->whereIn($param[0], $param[1]);
                }
            }
        }
        
        // This condtion use to apply whereBetween clause to object filter data
        if(!empty($this->whereBetween))
        {
            
            foreach($this->whereBetween as $param)
            {
                for($i=1; $i<count($param); $i++)
                {
                    $model->where(function($query) use($param, $i){
                        $query->where($param[0], $param[1], $param[2]);
                    });
                }
            }
        }
        
        if(!empty($this->whereRaw))
        {
            $condition = $this->whereRaw['condition'];
            $fields = $this->whereRaw['field'];
            $user = $this->whereRaw['user'];
            $qResult = $this->buildQuery($condition, $fields, $user);
//            $lv1Match = array();
//            $lv2Match = array();
//            foreach($condition['F'] as $lv2Filter){
//                foreach($fields as $field){
//                    if($lv2Filter['K'] == $field['external_id']){
//                        $lv3Match = array();
//                        $searchField = $this->getSearchField($field);
//                        foreach($lv2Filter['F'] as $lv3Filter){
//                            $operation = $lv3Filter['O']; //Assign Query Operation
//                            $value = $this->getQueryValue($field, $lv3Filter['V'], $user); //Assign Query Value
//                            switch($operation){
//                                case "eq" : //Equal Operation
//                                    array_push($lv3Match, [$searchField => ['$eq' => $value]]);
//                                    break;
//                                case "ne" : //Not Equal Operation
//                                    array_push($lv3Match, [$searchField => ['$ne' => $value]]);
//                                    break;
//                                case "gte" : //Greater Than or Equal Operation
//                                    array_push($lv3Match, [$searchField => ['$gte' => $value]]);
//                                    break;
//                                case "gt" : //Greater Than Operation
//                                    array_push($lv3Match, [$searchField => ['$gt' => $value]]);
//                                    break;
//                                case "lte" : //Less Than or Equal Operation
//                                    array_push($lv3Match, [$searchField => ['$lte' => $value]]);
//                                    break;
//                                case "lt" : //Less Than Operation
//                                    array_push($lv3Match, [$searchField => ['$lt' => $value]]);
//                                    break;
//                                case "contains" : //Contains Operation
//                                    array_push($lv3Match, [$searchField => ['$regex' => $value, '$options' => 'i']]);
//                                    break;
//                                case "startswith" : //Start With Operation
//                                    array_push($lv3Match, [$searchField => ['$regex' => '^'. $value, '$options' => 'i']]);
//                                    break;
//                                case "endswith" : //End With Operation
//                                    array_push($lv3Match, [$searchField => ['$regex' => $value . '$', '$options' => 'i']]);
//                                    break;
//                                case "doesnotcontain" : //Does Not Contain Operation
//                                    array_push($lv3Match, [$searchField => ['$regex' => '^((?!'. $value . ').)*$', '$options' => 'i']]);
//                                    break;
//                            }
//                        }
//                        switch($lv2Filter['L']){
//                            case "or" :
//                                array_push($lv2Match, ['$or' => $lv3Match]);
//                                break;
//                            case "and" :
//                                array_push($lv2Match, ['$and' => $lv3Match]);
//                                break;
//                        }
//                    }
//                }
//            }
//            switch($condition['L']){
//                case "or" :
//                    array_push($lv1Match, ['$or' => $lv2Match]);
//                    break;
//                case "and" :
//                    array_push($lv1Match, ['$and' => $lv2Match]);
//                    break;
//            }
            //Apply whereRaw to model
            $model->whereRaw(end($qResult));
        }
        // This condtion use to apply orderBy to filter data 
        if(!empty($this->orderBy))
        {
            $model->orderBy($this->orderBy, $this->orderMode);
        }
        
        // This condtion use to apply groupBy to filter data
        if(!empty($this->groupBy))
        {
            $model->groupBy($this->groupBy);
        }
        
        // This condtion use to apply limit to filter data
        if(!empty($this->limit))
        {
            $model->limit($this->limit);
        }
        
        // This condtion use to apply offset to filter data
        if(!empty($this->offset))
        {
            $model->skip($this->offset);
        }
        
        return $model->newQuery();
    }
    
    private function buildQuery($condition, $fields, $user){
        $qResult = array();
        $qStr = array();
        foreach($condition['F'] as $cond){
            if(isset($cond['F'])){
                //Merge result of previouse query otherwise it is empty as 
                //declearation of $qResult = array(); in recursive function
                $qResult = array_merge($qResult, $this->buildQuery($cond, $fields, $user));
            }else{
                foreach($fields as $field){
                    if($cond['K'] == $field['external_id']){
                        $searchField = $this->getSearchField($field);
                        $operation = $cond['O']; //Assign Query Operation
                        $value = $this->getQueryValue($field, $cond['V'], $user); //Assign Query Value
                        switch($operation){
                            case "eq" : //Equal Operation
                                array_push($qStr, [$searchField => ['$eq' => $value]]);
                                break;
                            case "ne" : //Not Equal Operation
                                array_push($qStr, [$searchField => ['$ne' => $value]]);
                                break;
                            case "gte" : //Greater Than or Equal Operation
                                array_push($qStr, [$searchField => ['$gte' => $value]]);
                                break;
                            case "gt" : //Greater Than Operation
                                array_push($qStr, [$searchField => ['$gt' => $value]]);
                                break;
                            case "lte" : //Less Than or Equal Operation
                                array_push($qStr, [$searchField => ['$lte' => $value]]);
                                break;
                            case "lt" : //Less Than Operation
                                array_push($qStr, [$searchField => ['$lt' => $value]]);
                                break;
                            case "contains" : //Contains Operation
                                array_push($qStr, [$searchField => ['$regex' => $value, '$options' => 'i']]);
                                break;
                            case "startswith" : //Start With Operation
                                array_push($qStr, [$searchField => ['$regex' => '^'. $value, '$options' => 'i']]);
                                break;
                            case "endswith" : //End With Operation
                                array_push($qStr, [$searchField => ['$regex' => $value . '$', '$options' => 'i']]);
                                break;
                            case "doesnotcontain" : //Does Not Contain Operation
                                array_push($qStr, [$searchField => ['$regex' => '^((?!'. $value . ').)*$', '$options' => 'i']]);
                                break;
                        }
                        break;
                    }
                }
            }
        }
        //Reverse build query with logical operator and/or
        //Work with multiple level of condition
        if(count($qStr) == 0){
            switch($condition['L']){
                case "or" :
                    $qResult[count($qResult) - 1] = ['$or' => $qResult];
                    break;
                case "and" :
                    $qResult[count($qResult) - 1] = ['$and' => $qResult];
                    break;
            }
        }else{
            //Atomic query with logical operator and/or
            //Work with last level of condition with K,O,V
            switch($condition['L']){
                case "or" :
                    array_push($qResult, ['$or' => $qStr]);
                    break;
                case "and" :
                    array_push($qResult, ['$and' => $qStr]);
                    break;
            }
        }
        return $qResult;
    }
    /**
     * Get name of search field based on we-control
     * @date 27-Sept-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  $field field in schema
     */
    private function getSearchField($field){
        $searchField = "";
        switch($field["edit_type"]){
            case "weText" :
                $searchField = $this->getWeTextField($field);
                break;
            case "weUdc" :
                $searchField = $this->getWeUdcField($field);
                break;
            case "wePhone" :
                $searchField = $field["field_id"];
                break;
            case "weEmail" :
                $searchField = $field["field_id"];
                break;
            case "weLink" :
                $searchField = $field["field_id"];
                break;
            case "weAddress" :
                $searchField = $field["field_id"];
                break;
            case "weSocial" :
                $searchField = $field["field_id"];
                break;
            case "weNumber" :
                $searchField = $this->getWeNumberField($field);
                break;
            case "weCurrency" :
                $searchField = $field["field_id"];
                break;
            case "weDate" :
                $searchField = $this->getWeDateField($field);
                break;
            default:
                $searchField = $field["field_id"];
                break;
        }
        return $searchField;
    }
    
    private function getQueryValue($field, $qValue, $user){
        $value = "";
        switch($field["edit_type"]){
            case "weDate" :
                $value = $this->getWeDateValue($field, $qValue, $user);
                break;
            default:
                $value = $qValue;
                break;
        }
        return $value;
    }
    
    private function getWeDateValue($field, $qValue, $user){
        $qValueArr = [$field["external_id"] => $qValue];
        $weDateObj = new WeDate($qValueArr, $field);
        $valDate = $weDateObj->renderInput($user);
        //weDate with config hide time
        if($field["config"]["custom"]["time"] == "hide"){
            return $valDate["datetime"];
        }
        //weDate with config show time
        elseif($field["config"]["custom"]["time"] == "show-time"){
            return $valDate["datetime"];
        }
        //weDate with config show time only
        elseif($field["config"]["custom"]["time"] == "show-time-only"){
            return $valDate;
        }
        //weDate with config required time
        elseif($field["config"]["custom"]["time"] == "required"){
            return $valDate["datetime"];
        }
    }
    
    private function getWeTextField($field){
        return $field["field_id"];
    }
    
    private function getWeNumberField($field){
        return $field["field_id"];
    }
    
    private function getWeUdcField($field){
        //Condition when control type is "default dropdown" or "dropdown search"
        if($field["config"]["custom"]["dropdown_type"] == "default" ||
           $field["config"]["custom"]["dropdown_type"] == "filterable"){
           //Multiple selection
            if($field["config"]["custom"]["multiple"]){
                
            }
            //Single selection
            else{
                return $field["field_id"] . ".code";
            }
        }
        //Condition when control type is "popup"
        else if($field["config"]["custom"]["dropdown_type"] == "auto-complete"){
            
        }
        //Conditon when control type is "switch"
        else if($field["config"]["custom"]["dropdown_type"] == "switch"){
            
        }
    }
    
    private function getWeDateField($field){
        //weDate with config hide time
        if($field["config"]["custom"]["time"] == "hide"){
            return $field["field_id"] . ".datetime";
        }
        //weDate with config show time
        elseif($field["config"]["custom"]["time"] == "show-time"){
            return $field["field_id"] . ".datetime";
        }
        //weDate with config show time only
        elseif($field["config"]["custom"]["time"] == "show-time-only"){
            return $field["field_id"];
        }
        //weDate with config required time
        elseif($field["config"]["custom"]["time"] == "required"){
            return $field["field_id"] . ".datetime";
        }
    }
    /**
     * This function use for set value to orderBy option filter data
     * @date 20-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @param  $orderBy
     */
    public function orderBy($orderBy, $orderMode = 'ASC')
    {
        $this->orderBy = $orderBy;
        $this->orderMode = $orderMode;
    }
        
    /**
     * This function use for set value to groupBy option filter data
     * @date 20-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @param  $groupBy
     */ 
    public function groupBy($groupBy)
    {
        $this->groupBy = $groupBy;
    }
    
    /**
     * This function use for set value to limit option filter data
     * @date 20-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @param  $limit = null
     */ 
    public function limit($limit = null)
    {
        //Validate url param limit
        if(is_numeric($limit))
        {
            return $this->limit = (int)$limit;
        }else{
            return $this->limit;
        }
    }
    
    /**
     * This function use for set value to offset option filter data
     * @date 20-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @param  $offset = null
     */
    public function offset($offset = null)
    {
        //validate url param offset
        if(is_numeric($offset))
        {
            return $this->offset = (int)$offset;
        }else{
            return $this->offset;
        }
    }
     
    /**
     * Function overloading logic for function name overlodedFunction
     * @date 20-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @param  $methodName
     * @param  $parameter
     */       
    public function __call($methodName, $parameter){
        if ($methodName == "where"){ 
            $count = count($parameter);
            switch ($count){
                case 1:
                    throw new \Exception("You are passing only one argument. Criteria 'where' required two arguments.");
                case 2:
                    $this->where[][$parameter[0]] = $parameter[1];
                    break;
                case 3:
                    $this->where[] = $parameter;
                    break;
                default:
                    $this->where[][$parameter[0]] = $parameter[1];
                    break;
            }
        }
        else if($methodName == "whereIn"){
            $this->whereIn[] = $parameter;
        }
        else if($methodName == "whereBetween"){
            $this->whereBetween[] = $parameter;
        }
        else if($methodName == "whereRaw"){
            $this->whereRaw['condition'] = $parameter[0];
            $this->whereRaw['field'] = $parameter[1];
            $this->whereRaw['user'] = $parameter[2];
        }
        else if($methodName == "orWhere"){
            $count = count($parameter);
            switch ($count){
                case 1:
                    throw new \Exception("You are passing only one argument. Criteria 'orWhere' required two arguments.");
                case 2:
                    $this->orWhere[][$parameter[0]] = $parameter[1];
                    break;
                case 3:
                    $this->orWhere[] = $parameter;
                    break;
                default:
                    $this->orWhere[][$parameter[0]] = $parameter[1];
                    break;
            }
        }
        else{
            throw new \Exception("This criteria methd '". $methodName . "' does not exists.");
        }
    }
}

