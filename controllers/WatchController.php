<?php
namespace kwater\controllers;

use yii\helpers\Console;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

use kwater\WatchEvent;
use kwater\watchers\BidWatcher;
use kwater\watchers\SucWatcher;
use kwater\models\BidKey;
use kwater\models\BidModifyCheck;

class WatchController extends \yii\console\Controller
{
  private $gman_client;

  public function init(){
    $this->gman_client=new \GearmanClient;
    $this->gman_client->addServers($this->module->gman_server);
  }

  /**
   * 개찰정보 watch
   */
  public function actionSuc(){
    $watcher=new SucWatcher;
    $watcher->on(WatchEvent::EVENT_ROW,[$this,'onSucRow']);
    while(true){
      try{
        $watcher->watch();
        $this->module->db->close();
      }
      catch(\Exception $e){
        $this->stdout($e.PHP_EOL,Console::FG_RED);
        \Yii::error($e,'kwater');
      }
      $this->stdout(sprintf("[%s] Peak memory usage: %s MB\n",
        date('Y-m-d H:i:s'),
        (memory_get_peak_usage(true)/1024/1024)),
        Console::FG_GREY
      );
      \kwater\Http::sleep();
    }
  }

  public function onSucRow($event){
    $row=$event->row;
    $out[]="[KWATER] [{$row['bidtype']}] %g{$row['notinum']}%n {$row['constnm']} ({$row['contract']},{$row['status']})";

    $bidkey=BidKey::find()->where([
      'whereis'=>\kwater\Module::WHEREIS,
      'notinum'=>$row['notinum'],
      'state'=>'Y',
    ])->orderBy('bidid desc')->limit(1)->one();
    if($bidkey===null){
      if($row['contract']!=='지명경쟁'){
        $out[]="%bNONE%n";
        $sleep=1;
      }
    }else{
      $out[]="({$bidkey->bidproc})";
      if(!ArrayHelper::isIn($bidkey->bidproc,['F','S'])){
        $out[]="%gNEW%n";
        $this->gman_client->doBackground('kwater_work_suc',Json::encode($row));
        $sleep=5;
      }
    }

    $this->stdout(Console::renderColoredString(join(' ',$out)).PHP_EOL);
    if($sleep) sleep($sleep);
  }

  /**
   * 입찰정보 watch
   */
  public function actionBid(){
    $bidWatcher=new BidWatcher;
    $bidWatcher->on(WatchEvent::EVENT_ROW,[$this,'onRow']);

    while(true){
      try {
        $bidWatcher->watch();

        $this->module->db->close();
      }
      catch(\Exception $e){
        $this->stdout($e.PHP_EOL,Console::FG_RED);
        \Yii::error($e,'kwater');
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
    $out[]="[KWATER] [{$row['bidtype']}] %g{$row['notinum']}%n {$row['constnm']} ({$row['contract']},{$row['status']})";

    $bidkey=BidKey::find()->where([
      'whereis'=>\kwater\Module::WHEREIS,
      'notinum'=>$row['notinum'],
    ])->orderBy('bidid desc')->limit(1)->one();
    if($bidkey===null){
      if(!ArrayHelper::isIn($row['status'],['입찰완료','적격신청','결과발표']) and
         !ArrayHelper::isIn($row['contract'],['지명경쟁','수의계약(시담)'])){
        $out[]="%rNEW%n";
        $this->gman_client->doBackground('kwater_work_bid',Json::encode($row));
        $sleep=1;
      }
    }else{
      $out[]="({$bidkey->bidproc})";
      if($bidkey->bidproc==='B' and $bidkey->state==='Y'){
        $bidcheck=BidModifyCheck::findOne($bidkey->bidid);
        if($bidcheck===null){
          $out[]="%yCHECK%n";
          $this->gman_client->doBackground('kwater_work_bid',Json::encode($row));
        }else{
          $diff=time()-$bidcheck->check_at;
          if($diff>=60*60*1){
            $out[]="%yCHECK%n";
            $this->gman_client->doBackground('kwater_work_bid',Json::encode($row));
            $bidcheck->check_at=time();
            $bidcheck->save();
            $sleep=1;
          }
        }
      }
    }
    $this->stdout(Console::renderColoredString(join(' ',$out)).PHP_EOL);
    if(isset($sleep)) sleep(3);
  }
}

