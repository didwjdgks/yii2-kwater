<?php
namespace kwater\models;

use kwater\Module;

class BidSuccom extends \i2\models\BidSuccom
{ 
  public static function getDb(){
    return Module::getInstance()->db;
  }
}

