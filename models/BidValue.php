<?php
namespace kwater\models;

class BidValue extends \i2\models\BidValue
{
  public static function getDb(){
    return \kwater\Module::getInstance()->db;
  }
}

