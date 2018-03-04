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
namespace App\Http\Controllers\BaseLibrary;

abstract class AbstractCriteria
{      
    public abstract function apply($model);    
}
