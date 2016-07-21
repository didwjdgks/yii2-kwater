<?php
namespace kwater\models;

class BidContent extends \i2\models\BidContent
{
  public static function getDb(){
    return \kwater\Module::getInstance()->db;
  }
}

