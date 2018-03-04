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

class MongoUtil {
    
    public static function getObjectID($id = ''){
        try{
            if($id != ''){
                return new \MongoDB\BSON\ObjectID($id);
            }else{
                return new \MongoDB\BSON\ObjectID();
            }
        }catch(\Exception $ex){
            return $id;
        }
    }
    
}
