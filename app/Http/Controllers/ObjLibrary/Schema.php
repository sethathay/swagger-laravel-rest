<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\ObjLibrary;

Class Schema extends ObjLibrary{
    public function __construct() {
        $schemaType = 'SCHM';
        parent::__construct($schemaType);
    }
}