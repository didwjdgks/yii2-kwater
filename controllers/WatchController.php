<?php
namespace kwater\controllers;

use yii\helpers\Console;

use kwater\watchers\BidWatcher;

class WatchController extends \yii\console\Controller
{
  public function actionBid(){
    $bidWatcher=new BidWatcher;

    while(true){
      try {
        $bidWatcher->watch();
      }
      catch(Exception $e){
      }
      $this->stdout(sprintf("[%s] Peak memory usage: %s MB\n",
        date('Y-m-d H:i:s'),
        (memory_get_peak_usage(true)/1024/1024)),
        Console::FG_GREY
      );
      \kwater\Http::sleep();
    }
  }

  public function onFoundBid($event){
  }
}

