<?php
namespace kwater\models;

use kwater\Module;

class CodeOrgI extends \i2\models\CodeOrgI
{
  public static function getDb(){
    return Module::getInstance()->db;
  }
}

