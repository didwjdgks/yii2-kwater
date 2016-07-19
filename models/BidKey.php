<?php
namespace kwater\models;

class BidKey extends \i2\models\BidKey
{
  public static function getDb(){
    return \kwater\Module::getInstance()->db;
  }

  public function getBidModifyCheck(){
    return $this->hasOne(BidModifyCheck::className(),['bidid'=>'bidid']);
  }

  public function getBidValue(){
    return $this->hasOne(BidValue::className(),['bidid'=>'bidid']);
  }
}

