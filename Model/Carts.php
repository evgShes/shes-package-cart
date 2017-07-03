<?php

namespace ShesShoppingCart\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;

class Carts extends Model
{
     protected $fillable = [
         'cart_id',
         'items'
     ];
}
