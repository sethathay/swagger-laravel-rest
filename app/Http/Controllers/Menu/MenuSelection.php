<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;
use App\Http\Controllers\BaseLibrary\CriteriaOption\CriteriaOption;
use App\Http\Controllers\BaseLibrary\SchemaRender;
use App\Http\Controllers\Util\MongoUtil;
use App\Http\Controllers\Menu\Menu;

class MenuSelection extends AbstractResourceProvider{
    
    const FIELD_RECORD_TYPE = "record_type";
    const FIELD_TENANT = "_tenant";
    const FIELD_MENU = "menu_code";
    const VALUE_RECORD_TYPE = "MENU_SELECTION";
    
    private $recordType;
    private $tenantId;
    private $menuId;
    private $schemaMenu = "TMENU";
    
    public function __construct($tenantId = null, $menuId = null) {
        parent::__construct();
        $this->recordType = self::VALUE_RECORD_TYPE;
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
        if($menuId != null){
            $this->menuId = $menuId;
        }
    }
    
    public function getRecordType(){
        return $this->recordType;
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    public function getMenuId(){
        return $this->menuId;
    }
    
    public function model() {
        return 'App\Menu';
    }
    
    public function save($object) {
        $object->record_type = $this->getRecordType();
        return parent::save($object);
    }
    
    public function getMenu($tenantId, $menuId){
        $criteria = new CriteriaOption();
        $mObject = new Menu($tenantId);
        $mSchema = new SchemaRender($this->schemaMenu);
        $criteria->where(Menu::FIELD_RECORD_TYPE, $mObject->getRecordType());
        $criteria->where(Menu::FIELD_TENANT,$mObject->getTenantId());
        $criteria->whereRaw($mObject->getDefaultFilter($menuId),$mSchema->getAllFields(),"");
        $mObject->pushCriteria($criteria);
        $mObject->applyCriteria();
        return $mObject->readOne();
    }
    
    public function getDefaultFilter($str){
        return  [
                    "L" => "or",
                    "F" => [
                        [
                            "K" => "id",
                            "L" => "or",
                            "F" => [
                                [
                                    "K" => "id",
                                    "O" => "eq",
                                    "V" => MongoUtil::getObjectID($str)
                                ]
                            ]
                        ],
                        [
                            "K" => "code",
                            "L" => "or",
                            "F" => [
                                [
                                    "K" => "code",
                                    "O" => "eq",
                                    "V" => $str
                                ]
                            ]
                        ]
                    ]
                ];
    }
}
