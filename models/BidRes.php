<?php
namespace kwater\models;

use kwater\Module;

class BidRes extends \i2\models\BidRes
{
  public static function getDb(){
    return Module::getInstance()->db;
  }
}

