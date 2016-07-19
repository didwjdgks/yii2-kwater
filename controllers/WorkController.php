<?php
namespace kwater\controllers;

use GearmanWorker;
use kwater\workers\BidWorker;
use kwater\BidFile;
use kwater\models\BidKey;
use kwater\models\BidModifyCheck;

class WorkController extends \yii\console\Controller
{
  public function actionBid(){
    $bid=new BidWorker;
    $bid->on(\kwater\WatchEvent::EVENT_ROW,[$this,'onBidData']);

    $worker=new GearmanWorker();
    $worker->addServers($this->module->gman_server);
    $worker->addFunction('kwater_work_bid',[$bid,'work']);
    while($worker->work());
  }

  public function onBidData($event){
    print_r($event->row);
    $row=$event->row;

    $bidkey=BidKey::find()->where([
      'whereis'=>\kwater\Module::WHEREIS,
      'notinum'=>$row['notinum'],
    ])->orderBy('bidid desc')->limit(1)->one();

    if($bidkey===null) return;

    $bidcheck=BidModifyCheck::findOne($bidkey->bidid);
    if($bidcheck===null){
      $bidcheck=new BidModifyCheck([
        'bidid'=>$bidkey->bidid,
      ]);
    }
    $bid_hash=md5(join('',$row));
    $noticeDoc=BidFile::findNoticeDoc($row['attchd_lnk']);
    if($noticeDoc!==null && $noticeDoc->download()){
      $file_hash=md5_file($noticeDoc->saveDir.'/'.$noticeDoc->savedName);
      $noticeDoc->remove();
    }
    if(!empty($bidcheck->bid_hash) and $bidcheck->bid_hash!=$bid_hash){
      $this->stdout(" %r> check : bid_hash diff%n\n");
    }
    else if(!empty($bidcheck->file_hash) and $bidcheck->file_hash!=$file_hash){
      $this->stdout(" %r> check : file_hash diff%n\n");
    }
    $bidcheck->bid_hash=$bid_hash;
    $bidcheck->file_hash=$file_hash;
    $bidcheck->check_at=time();
    $bidcheck->save();
  }
}

