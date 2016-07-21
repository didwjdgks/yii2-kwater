<?php
namespace kwater\controllers;

use yii\helpers\Console;
use yii\helpers\VarDumper;

use GearmanWorker;
use kwater\workers\BidWorker;
use kwater\workers\SucWorker;
use kwater\BidFile;
use kwater\models\BidKey;
use kwater\models\BidValue;
use kwater\models\BidContent;
use kwater\models\BidModifyCheck;
use kwater\models\BidRes;
use kwater\models\BidSuccom;
use kwater\models\CodeOrgI;

class WorkController extends \yii\console\Controller
{
  private $gman_client;

  public function init(){
    parent::init();
    $gman_client=new \GearmanClient;
    $gman_client->addServers('115.168.48.242');
  }

  public function actionBid(){
    $bid=new BidWorker;
    $bid->on(\kwater\WatchEvent::EVENT_ROW,[$this,'onBidData']);

    $worker=new GearmanWorker();
    $worker->addServers($this->module->gman_server);
    $worker->addFunction('kwater_work_bid',[$bid,'work']);
    while($worker->work());
  }

  public function onBidData($event){
    $row=$event->row;
    \Yii::info('['.__METHOD__.'] $row'.PHP_EOL.VarDumper::dumpAsString($row),'kwater');
    $this->stdout(Console::renderColoredString(
      "[KWATER] %g{$row['notinum']}%n [{$row['bidtype']}] {$row['constnm']} \n"
    ));

    try{
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
        $bidkey->org_i=$row['org_i'];
        $codeorg=CodeOrgI::findByOrgname($bidkey->org_i);
        if($codeorg!==null){
          $bidkey->orgcode_i=$codeorg->org_Scode;
        }
        $bidkey->save();

        $bidvalue=new BidValue;
        $bidvalue->bidid=$bidkey->bidid;
        $bidvalue->yegatype='25';
        $bidvalue->yegarng='-2.5|2.5';
        $bidvalue->charger=$row['charger'];
        $bidvalue->multispare=$row['multispare'];
        $bidvalue->save();

        $bidcontent=new BidContent;
        $bidcontent->bidid=$bidkey->bidid;
        $bidcontent->orign_lnk='http://ebid.kwater.or.kr/fz?bidno='.$row['notinum'];
        $bidcontent->attchd_lnk=$row['attchd_lnk'];
        $bidcontent->bidcomment=$row['bidcomment'];
        $bidcontent->save();
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
        $this->stdout(" > check : bid_hash diff\n",Console::FG_RED);
        $this->sendMessage("수자원공사 공고정보 확인필요! [{$row['notinum']}]");
      }
      else if(!empty($bidcheck->file_hash) and $bidcheck->file_hash!=$file_hash){
        $this->stdout(" > check : file_hash diff\n",Console::FG_RED);
        $this->sendMessage("수자원공사 공고원문 확인필요! [{$row['notinum']}]");
      }
      $bidcheck->bid_hash=$bid_hash;
      $bidcheck->file_hash=$file_hash;
      $bidcheck->check_at=time();
      $bidcheck->save();
    }catch(\Exception $e){
      $this->stdout("$e\n",Console::FG_RED);
      \Yii::error($e,'kwater');
    }
  }

  public function sendMessage($msg){
    $gman_client->doBackground('send_chat_message_from_admin',Json::encode([
      'recv_id'=>149,
      'message'=>$msg,
    ]));
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
    $row=$event->row;
    \Yii::info('['.__METHOD__.'] $row '.VarDumper::dumpAsString($row),'kwater');
    $this->stdout(Console::renderColoredString(
      "[KWATER] %g{$row['notinum']}%n {$row['constnm']} ({$row['status']})\n"
    ));

    try{
      $bidkey=BidKey::find()->where([
        'whereis'=>'07',
        'notinum'=>$row['notinum'],
      ])->orderBy('bidid desc')->limit(1)->one();
      if($bidkey===null){
        $this->stdout(" > 입찰공고가 없습니다.\n",Console::FG_RED);
        return;
      }
      $bidvalue=BidValue::findOne($bidkey->bidid);
      if($bidvalue===null){
        throw new \Exception('bid_value is empty');
      }

      $bidres=BidRes::findOne($bidkey->bidid);
      if($bidres===null){
        $bidres=new BidRes([
          'bidid'=>$bidkey->bidid,
        ]);
      }

      BidSuccom::deleteAll(['bidid'=>$bidkey->bidid]);

      if($row['bidproc']=='F'){
        $bidres->yega=0;
        $bidres->selms='';
        $bidres->multispare='';
        $bidres->officenm1='유찰';
        $bidres->reswdt=date('Y-m-d H:i:s');
        $bidres->save();

        $bidkey->bidproc='F';
        $bidkey->resdt=date('Y-m-d H:i:s');
        $bidkey->editdt=date('Y-m-d H:i:s');
        $bidkey->save();
        return;
      }

      $bidres->yega=$row['yega'];
      $bidres->innum=$row['innum'];
      $bidres->selms=$row['selms'];
      $bidres->multispare=$bidvalue->multispare;
      $bidres->save();

      Console::startProgress(0,$row['innum']);
      foreach($row['succoms'] as $succom){
        $bidsuccom=new BidSuccom([
          'bidid'=>$bidkey->bidid,
          'seq'=>$succom['seq'],
          'officeno'=>'',
          'officenm'=>$succom['officenm'],
          'prenm'=>'',
          'success'=>$succom['success'],
          'pct'=>$succom['pct'],
          'rank'=>$succom['rank'],
          'selms'=>'',
          'etc'=>$succom['etc'],
        ]);
        $bidsuccom->save();
        Console::updateProgress($succom['seq'],$row['innum']);
      }
      Console::endProgress();

      $bidkey->bidproc='S';
      $bidkey->resdt=date('Y-m-d H:i:s');
      $bidkey->editdt=date('Y-m-d H:i:s');
      $bidkey->save();

    }catch(\Exception $e){
      $this->stdout("$e\n",Console::FG_RED);
      \Yii::error($e,'kwater');
    }
  }
}

