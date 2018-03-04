<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\Project;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;
use App\Http\Controllers\Util\MongoUtil;

class Project extends AbstractResourceProvider{
    
    const FIELD_TENANT = "_tenant";
    
    private $tenantId;
    
    //Initiate constructor
    public function __construct($tenantId = null){
        parent::__construct();
        if($tenantId != null){
            $this->tenantId = $tenantId;
        }
    }
    
    public function getTenantId(){
        return $this->tenantId;
    }
    
    //Override abstract method model of abstract resource provider
    public function model(){
        return 'App\Project';
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
                            "K" => "no",
                            "L" => "or",
                            "F" => [
                                [
                                    "K" => "no",
                                    "O" => "eq",
                                    "V" => $str
                                ]
                            ]
                        ]
                    ]
                ];
    }
}