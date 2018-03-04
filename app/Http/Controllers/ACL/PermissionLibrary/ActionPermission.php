<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\ACL\PermissionLibrary;

class ActionPermission extends PermissionLibrary{
    
    public function __construct($tenantId = null) {
        $actionPermission = 'PROG';
        parent::__construct($tenantId, $actionPermission);
    }
    
}
