<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\User;

use App\Http\Controllers\BaseLibrary\AbstractResourceProvider;

class Token extends AbstractResourceProvider{
    
    public function __construct() {
        parent::__construct();
    }
    
    //Override abstract method model of abstract resource provider
    public function model(){
        return 'App\Token';
    }
}
