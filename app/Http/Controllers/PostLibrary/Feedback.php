<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\PostLibrary;

class Feedback extends PostLibrary{
    public function __construct($tenantId){
        $feedbackType = 'FB';
        parent::__construct($tenantId, $feedbackType);
    }
}
