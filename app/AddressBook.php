<?php

namespace App;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class AddressBook extends Eloquent
{
    public $timestamps = false;
    
    protected $collection="address_books";
}
