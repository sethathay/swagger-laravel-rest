<?php
/**
 * This file is responsible for datetime and datetime related functionality
 * and contains of all static function using across application.
 * 
 * LICENSE: Some license information
 * 
 * @category name
 * @package name
 * @copyright (c) 2016, work Evolve
 * @license http://URL name
 * @version string
 * @since version
 */

namespace App\Http\Controllers\Util;

use Carbon\Carbon;
use MongoDB\BSON\UTCDateTime;

    /**
    * Class DateUtil is used to assist and support developers 
    * in solving prolems as a whole related to datetime only.
    * 
    * @since version 1.0
    * @deprecated since version 0.0
    */
class DateUtil
{
    /**
     * Set datetime to ISO 8610 format
     * @date 24-Aug-2016
     * @author Kosal Tim <kosal.tim@workevolve.com>
     * @param datetime $value is value of datetime with normal format
     * 
     * @return string ISO 8610 string datetime format
     */
    private static function setDateTimeISO($value)
    {
        $date = new Carbon($value);
        return $date->toIso8601String();
    }
    /**
     * Format ISO to Y-m-d H:i:s
     * @date 01-Sept-2016
     * @author Phou Lin <lin.phou@workevolve.com>
     * @param datetime $ISODate is value of ISO datetime format
     * 
     * @return string datetime format
     */
    private static function formatISODate($ISODate)
    {
        return (new \DateTime())->setTimestamp(floatval((string) $ISODate)/1000)->format('Y-m-d H:i:s');
    }
    
    /**
     * Set datetime to ISO 8610 format
     * @date 04-Sept-2016
     * @author seng sathya <sathya.seng@workevolve.com>
     * @param datetime $ISODate is value of ISO datetime format
     * 
     * @return string ISO 8610 string datetime format
     */
    public static function getDateTimeISO($date)
    {
        if($date instanceof Carbon){
            return DateUtil::setDateTimeISO($date);
        }
        elseif($date instanceof UTCDateTime){
            return DateUtil::setDateTimeISO(self::formatISODate($date));
        }
        return $date;
    }   
}
