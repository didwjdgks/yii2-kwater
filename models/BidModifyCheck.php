<?php
namespace kwater\models;

class BidModifyCheck extends \i2\models\BidModifyCheck
{
  public static function getDb(){
    return \kwater\Module::getInstance()->db;
  }
}

