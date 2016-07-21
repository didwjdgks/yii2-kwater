<?php
namespace kwater\controllers;

use GearmanWorker;
use kwater\workers\BidWorker;
use kwater\workers\SucWorker;
use kwater\BidFile;
use kwater\models\BidKey;
use kwater\models\BidValue;
use kwater\models\BidContent;
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
    
    if($bidkey===null){
      //------------------------------------
      // bid data save
      //------------------------------------
      $bidkey=new BidKey;
      $bidkey->bidid=date('ymd').'K'.str_replace('-','',$row['notinum']).'-00-00-01';
      $bidkey->notinum=$row['notinum'];
      $bidkey->whereis=\kwater\Module::WHEREIS;
      $bidkey->bidtype=$row['bidtype'];
      $bidkey->bidview=$row['bidview'];
      $bidkey->constnm=$row['constnm'];
      $bidkey->succls=$row['succls'];
      $bidkey->noticedt=$row['noticedt'];
      $bidkey->basic=$row['basic'];
      $bidkey->contract=$row['contract'];
      $bidkey->registdt=$row['registdt'];
      $bidkey->opendt=$row['opendt'];
      $bidkey->closedt=$row['closedt'];
      $bidkey->constdt=$row['constdt'];
      $bidkey->pqdt=$row['pqdt'];
      $bidkey->convention=$row['convention'];
      $bidkey->state='N';
      $bidkey->bidproc='B';
      $bidkey->writedt=date('Y-m-d H:i:s');
      $bidkey->editdt=date('Y-m-d H:i:s');
      $bidkey->save();

      $bidvalue=new BidValue;
      $bidvalue->bidid=$bidkey->bidid;
      $bidvalue->yegatype='25';
      $bidvalue->yegarng='-2.5|2.5';
      $bidvalue->charger=$row['charger'];
      $bidvalue->multispare=$row['multispare'];
      //$bidvalue->save();

      $bidcontent=new BidContent;
      $bidcontent->bidid=$bidkey->bidid;
      $bidcontent->orign_lnk='http://ebid.kwater.or.kr/fz?bidno='.$row['notinum'];
      $bidcontent->attchd_lnk=$row['attchd_lnk'];
      $bidcontent->bidcomment=$row['bidcomment'];
      //$bidcontent->save();
    }

    //-------------------------------
    // check bid modified
    //-------------------------------
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

  /**
   * 개찰정보 work
   */
  public function actionSuc(){
    $suc=new SucWorker;
    $suc->on(\kwater\WatchEvent::EVENT_ROW,[$this,'onSucData']);

    $w=new GearmanWorker;
    $w->addServers($this->module->gman_server);
    $w->addFunction('kwater_work_suc',[$suc,'work']);
    while($w->work());
  }

  public function onSucData($event){
    print_r($event->row);
  }
}

