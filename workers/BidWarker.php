<?php
namespace kwater\workers;

use Yii;
use Exception;
use yii\helpers\Console;

class BidWorker
{
  public static function work($job){
    $workload=$job->workload();
    echo $workload,PHP_EOL;

    try {
    }
    catch(Exception $e){
      echo Console::renderColoredString("%r$e%n"),PHP_EOL;
      Yii::error($e,'kwater');
    }

    echo sprintf("[%s] Peak memory usage: %sMb\n",
      date('Y-m-d H:i:s'),
      (memory_get_peak_usage(true)/1024/1024)
    );
  }
}

