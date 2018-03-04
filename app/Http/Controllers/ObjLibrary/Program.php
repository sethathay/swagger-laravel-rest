<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\ObjLibrary;

Class Program extends ObjLibrary{
    public function __construct() {
        $programType = 'PROG';
        parent::__construct($programType);
    }
}