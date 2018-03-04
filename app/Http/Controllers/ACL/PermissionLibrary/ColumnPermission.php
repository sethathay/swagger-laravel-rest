<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\ACL\PermissionLibrary;

class ColumnPermission extends PermissionLibrary{
    
    public function __construct($tenantId = null) {
        $columnPermission = 'VIEW';
        parent::__construct($tenantId, $columnPermission);
    }
    
}
