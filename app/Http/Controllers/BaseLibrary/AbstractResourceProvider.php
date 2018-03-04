<?php

/**
* Abstract class AbstractResourceProvider will handle on three points
 * 1) Implement base function (readAll, readOne, save, remove) from interface DataSourceInterface
 * 2) Implement criteria function (skipCriteria, getCriteria, getByCriteria, pushCriteria, applyCriteria) from interface CriteriaInterface
 * 3) Provide abstract function model() for Concrete class to implement specific model using in class.
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

use App\Http\Controllers\BaseLibrary\CriteriaInterface;
use App\Http\Controllers\BaseLibrary\AbstractCriteria;

use Jenssegers\Mongodb\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Container\Container;

abstract class AbstractResourceProvider implements DataSourceInterface, CriteriaInterface {
    /**
     * @var container
     */
    private $container;
    /**
     * @var model
     */
    protected $model;
    /**
     * @var Collection
     */
    protected $criteria;
        /**
     * @var bool
     */
    protected $skipCriteria = false;
        
    public function __construct() 
    {
        $container = new Container();
        $this->criteria = new Collection();
        $this->setContainer($container);
        $this->setModel();
        $this->resetScope();
    }
    
    /**
     * Specify Model class name
     * 
     * @return mixed
     */
    abstract function model();
   
    /**
     * Function use for set model name that this model name get from Concrete class
     * implement in function model().
     * @date 20-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @return Model
     */ 
    public function setModel() 
    {
        $model = $this->container->make($this->model());
        
        if (!$model instanceof Model)
        {
            return "Can't get model";
        }
        return $this->model = $model;
    }  
    
    /**
     * Function will provide property model for concrete class and update criteria request
     * on every calling.
     * @date 20-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @return model
     */ 
    public function getModel()
    {
        $this->applyCriteria();
        return $this->model;
    } 
      
    /**
     * Set value to property container for concrete class
     * on every calling.
     * @date 20-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     */ 
    public function setContainer($container)
    {
        $this->container = $container;
    }
    
    /**
     * Get value from property container for using in concrete class
     * on every calling.
     * @date 20-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @return property container
     */
    public function getContainer()
    {
        return $this->container;
    }   

    /**
     * Retrieve the list of data from collection.
     * @date 20-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @return data collection
     */
    public function readAll($attributes = array(), $relationModel = '') 
    {   
        if($relationModel != ''){
            $this->getModel()->with($relationModel)->get($attributes);
        }
        return $this->getModel()->get($attributes);
    }
    
    /**
     * Retrieve an object data from collection.
     * @date 31-Aug-2017
     * @author Setha Thay <lin.phou@workevolve.com>
     * @return object data
     */
    public function readOne($attributes = array(), $relationModel = ''){
        if($relationModel != ''){
            return $this->getModel()->with($relationModel)->first($attributes);
        }
        return $this->getModel()->first($attributes);
    }
    
    /**
     * Create new or update an object data from collection.
     * @date 21-Feb-2017
     * @author Phou Lin <lin.phou@workevolve.com>
     * @return object data
     */
    public function save($object){
        $object->save();
        return $object;
    }
    
    public function saveMany($object){
        return $this->model->insert($object);
    }

    /**
     * Delete an object data from collection.
     * @date 31-Aug-2017
     * @author Setha Thay <lin.phou@workevolve.com>
     * @return -
     */
    public function remove(){
        return $this->getModel()->delete();
    }
    
    /**
     * @return $this
     */
    public function resetScope() {
        $this->skipCriteria(false);
        return $this;
    }
 
    /**
     * @param bool $status
     * @return $this
     */
    public function skipCriteria($status = true){
        $this->skipCriteria = $status;
        return $this;
    }
 
    /**
     * @return mixed
     */
    public function getCriteria() {
        return $this->criteria;
    }
 
    /**
     * @param AbstractCriteria $criteria
     * @return $this
     */
    public function getByCriteria(AbstractCriteria $criteria) {
        $this->model = $criteria->apply($this->model);
        return $this;
    }
 
    /**
     * @param AbstractCriteria $criteria
     * @return $this
     */
    public function pushCriteria(AbstractCriteria $criteria) {
        $this->criteria->push($criteria);
        return $this;
    }
 
    /**
     * @return $this
     */
    public function  applyCriteria() {
        if ($this->skipCriteria === true) {
            return $this;
        }

        foreach($this->getCriteria() as $criteria) {
            if ($criteria instanceof AbstractCriteria) {
                $this->model = $criteria->apply($this->model);
            }
        }
 
        return $this;
    }
    
    /**
     * Apply the statistic function without fetching fields and rows
     * This make the count operation very efficient without performance pain
     * @return total number of record
     */
    public function count(){
        return $this->getModel()->count();
    }
    
}
