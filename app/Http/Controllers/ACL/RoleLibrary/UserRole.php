<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Http\Controllers\ACL\RoleLibrary;

class UserRole extends RoleLibrary{
    public function __construct($tenantId = null){
        $userRoleType = 'PROG';
        parent::__construct($tenantId, $userRoleType);
    }
}
