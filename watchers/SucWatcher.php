<?php
namespace kwater\watchers;

use kwater\WatchEvent;

class SucWatcher extends \yii\base\Component
{
  const URL='';

  public function watch(){
    $http=\kwater\Module::getInstance()->http;

    try {
    }
    catch(\Exception $e){
      throw $e;
    }
  }
}

