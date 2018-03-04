<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\BaseLibrary\WeControls;

use DateTime;
use DateTimeZone;

class WeDate {
    
    private $data;
    private $element;
    
    public function __construct($data, $element) {
        $this->data = $data;
        $this->element = $element;
    }
    /**
     * Function to validate weDate
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
        
        //weDate with config hide time
        if($element["config"]["custom"]["time"] == "hide"){
            if(!$this->validateDate($data[$element["external_id"]])){
                return array('status' => false,
                             'message'=> $element["external_id"] . ' field is not a valid value or incorrect format (Y-m-d) of date.');
            }
        }
        //weDate with config show time
        elseif($element["config"]["custom"]["time"] == "show-time"){
            if( !$this->validateDateTime($data[$element["external_id"]]) &&
                !$this->validateDate($data[$element["external_id"]])){
                return array('status' => false,
                             'message'=> $element["external_id"] . ' field is not a valid value or incorrect format (Y-m-d H:i:s / Y-m-d) of datetime.');
            }
        }
        //weDate with config show time only
        elseif($element["config"]["custom"]["time"] == "show-time-only"){
            if(!$this->validateTime($data[$element["external_id"]])){
                return array('status' => false,
                             'message'=> $element["external_id"] . ' field is not a valid value or incorrect format (H:i:s) of time.');
            }
        }
        //weDate with config required time
        elseif($element["config"]["custom"]["time"] == "required"){
            if(!$this->validateDateTime($data[$element["external_id"]])){
                return array('status' => false,
                             'message'=> $element["external_id"] . ' field is not a valid value or incorrect format (Y-m-d H:i:s) of datetime.');
            }
        }
        return array('status' => true,
                    'message'=> 'Successfully validated user input data');
    }
    /**
     * Function to render input of weDate
     * @date 19-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  object $user
     * @return date
     */
    public function renderInput($user){
        $data = $this->data;
        $element = $this->element;
        //weDate with config hide time
        if($element["config"]["custom"]["time"] == "hide"){
            return $this->setJulianDate($data[$element["external_id"]]);
        }
        //weDate with config show time
        elseif($element["config"]["custom"]["time"] == "show-time"){
            if($this->validateDateTime($data[$element["external_id"]])){
                return $this->setJulianDateTime($data[$element["external_id"]],$user);
            }
            if($this->validateDate($data[$element["external_id"]])){
                return $this->setJulianDate($data[$element["external_id"]]);
            }
        }
        //weDate with config show time only
        elseif($element["config"]["custom"]["time"] == "show-time-only"){
            //To get timezone of current user
            $tz_from = "UTC";
            $tz_to = "UTC";
            if(isset($user->time_zone)){
                $tz_from = $user->time_zone["label"];
            }
            $dt = new DateTime($data[$element["external_id"]], new DateTimeZone($tz_from));
            $dt->setTimezone(new DateTimeZone($tz_to));
            $dateObj = date_parse($dt->format("H:i:s"));
            return ($dateObj['hour'] * 3600) + ($dateObj['minute'] * 60) + $dateObj['second'];
        }
        //weDate with config required time
        elseif($element["config"]["custom"]["time"] == "required"){
            return $this->setJulianDateTime($data[$element["external_id"]],$user);
        }
    }
    /**
     * Function to render output of weDate
     * @date 19-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  object $user
     * @return date
     */
    public function renderOutput($user){
        $data = $this->data;
        $element = $this->element;
        //weDate with config hide time
        if($element["config"]["custom"]["time"] == "hide"){
            return $this->getJulianDate($data->$element["field_id"]);
        }
        //weDate with config show time
        elseif($element["config"]["custom"]["time"] == "show-time"){
            $dt = $data->$element["field_id"];
            if(isset($dt["time"]) && $dt["time"] > 0){
                return $this->getJulianDateTime($dt,$user);
            }
            else{
                return $this->getJulianDate($dt);
            }
        }
        //weDate with config show time only
        elseif($element["config"]["custom"]["time"] == "show-time-only"){
            //To get timezone of current user
            $tz_from = "UTC";
            $tz_to = "UTC";
            if(isset($user->time_zone)){
                $tz_to = $user->time_zone["label"];
            }
            $time = gmdate("H:i:s", $data->$element["field_id"]);
            $date = new DateTime($time, new DateTimeZone($tz_from));
            $date->setTimezone(new DateTimeZone($tz_to));
            return $date->format('H:i:s');
        }
        //weDate with config required time
        elseif($element["config"]["custom"]["time"] == "required"){
            $dt = $data->$element["field_id"];
            return $this->getJulianDateTime($dt,$user);
        }        
    }
    /**
     * Function to validate date
     * @date 19-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to validate
     * @return boolean
     */
    private function validateDate($data){
        return (DateTime::createFromFormat('Y-m-d', $data) !== false);
    }
    /**
     * Function to validate time
     * @date 20-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to validate
     * @return boolean
     */
    private function validateTime($data){
        return (DateTime::createFromFormat('H:i:s', $data) !== false);
    }
    /**
     * Function to validate date time
     * @date 19-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to validate
     * @return boolean
     */
    private function validateDateTime($data){
        return (DateTime::createFromFormat('Y-m-d H:i:s', $data) !== false);
    }
    /**
     * Function to create julian date object
     * @date 20-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to create julian date object
     * @return julian date object
     */
    private function setJulianDate($data){
        $dateObj = date_parse($data);
        $julDate = juliantojd($dateObj['month'], $dateObj['day'], $dateObj['year']);
        return array(
            "date" => $julDate,
            "time" => 0,
            "datetime" => floatval($julDate . '' . sprintf("%05d",0))
        );
    }
    /**
     * Function to create julian datetime object
     * @date 20-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to create julian datetime object
     * @return julian datetime object
     */
    public function setJulianDateTime($data,$user){
        //To get timezone of current user
        $tz_from = "UTC";
        $tz_to = "UTC";
        if(isset($user->time_zone)){
            $tz_from = $user->time_zone["label"];
        }
        $dt = new DateTime($data, new DateTimeZone($tz_from));
        $dt->setTimezone(new DateTimeZone($tz_to));
        $dateObj = date_parse($dt->format("Y-m-d H:i:s"));
        $julDate = juliantojd($dateObj['month'], $dateObj['day'], $dateObj['year']);
        $time = ($dateObj['hour'] * 3600) + ($dateObj['minute'] * 60) + $dateObj['second'];
        return array(
            "date" => $julDate,
            "time" => $time,
            "datetime" => floatval($julDate . '' . sprintf("%05d",$time))
        );
    }
    /**
     * Function to get julian date object
     * @date 20-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to get julian date object
     * @return julian date object
     */
    private function getJulianDate($data){
        $date = new DateTime(jdtojulian($data["date"]));
        return $date->format('Y-m-d');
    }
    /**
     * Function to get julian datetime object
     * @date 20-Jul-2017
     * @author Setha Thay <setha.thay@workevolve.com>
     * @param  var $data is input data to get julian datetime object
     * @return julian datetime object
     */
    public function getJulianDateTime($data,$user){
        //To get timezone of current user
        $tz_from = "UTC";
        $tz_to = "UTC";
        if(isset($user->time_zone)){
            $tz_to = $user->time_zone["label"];
        }
        $time = gmdate("H:i:s", $data["time"]);
        $date = new DateTime(jdtojulian($data["date"]). ' ' . $time, new DateTimeZone($tz_from));
        $date->setTimezone(new DateTimeZone($tz_to));
        return $date->format('Y-m-d H:i:s');
    }
}
