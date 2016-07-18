<?php
namespace kwater\controllers;

use yii\helpers\Console;

use kwater\WatchEvent;
use kwater\watchers\BidWatcher;

class WatchController extends \yii\console\Controller
{
  public function actionBid(){
    $bidWatcher=new BidWatcher;
    $bidWatcher->on(WatchEvent::EVENT_ROW,[$this,'onRow']);

    while(true){
      try {
        $bidWatcher->watch();
      }
      catch(Exception $e){
        $this->stdout($e.PHP_EOL,Console::FG_RED);
        Yii::error($e,'kwater');
      }
      $this->stdout(sprintf("[%s] Peak memory usage: %s MB\n",
        date('Y-m-d H:i:s'),
        (memory_get_peak_usage(true)/1024/1024)),
        Console::FG_GREY
      );
      \kwater\Http::sleep();
    }
  }

  public function onRow($event){
    $row=$event->row;
    $this->stdout(implode(',',$row)."\n");
  }
}

