<?php
namespace kwater\controllers;

use GearmanWorker;
use kwater\workers\BidWorker;

class WorkController extends \yii\console\Controller
{
  public function actionBid(){
    $worker=new GearmanWorker();
    $worker->addServers($this->module->gman_server);
    $worker->addFunction('kwater_work_bid',[BidWorker::className(),'work']);
    while($worker->work());
  }
}

