<?php

/**
* Interface "DataSourceInterface" is provide base method for class that implement this interface.
* Base methods: readAll(), readOne(), save(), and remove()
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
namespace App\Http\Controllers\BaseLibrary;

interface DataSourceInterface {
        
    public function readAll();
    
    public function readOne();
    
    public function save($object);
    
    public function remove();
    
    public function count();
    
}
