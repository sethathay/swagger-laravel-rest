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

use App\Http\Controllers\BaseLibrary\AbstractCriteria;

interface CriteriaInterface  {
        
    /**
     * @param bool $status
     * @return $this
     */
    public function skipCriteria($status = true);
 
    /**
     * @return mixed
     */
    public function getCriteria();
 
    /**
     * @param AbstractCriteria $criteria
     * @return $this
     */
    public function getByCriteria(AbstractCriteria $criteria);
 
    /**
     * @param AbstractCriteria $criteria
     * @return $this
     */
    public function pushCriteria(AbstractCriteria $criteria);
 
    /**
     * @return $this
     */
    public function  applyCriteria();
    
}
