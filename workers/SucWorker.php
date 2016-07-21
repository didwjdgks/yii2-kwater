<?php
namespace kwater\workers;

use yii\helpers\Json;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;

use kwater\WatchEvent;

class SucWorker extends \yii\base\Component
{
  const URL='/SrcWeb/BD/FZBD3030.asp';
  const URL_M='/SrcWeb/BD/FZBD3011.asp'; //복수예가 선택번호 url
  private $module;

  public function init(){
    parent::init();
    $this->module=\kwater\Module::getInstance();
  }

  public function work($job){
    \Yii::info('suc worker workload '.$job->workload(),'kwater');
    $workload=Json::decode($job->workload());

    $http=$this->module->http;
    $data=[];

    if($workload['status']=='유찰'){
      $event=new WatchEvent;
      $event->row=[
        'notinum'=>$workload['notinum'],
        'bidproc'=>'F',
      ];
      $this->trigger(WatchEvent::EVENT_ROW,$event);
      return;
    }

    $data['notinum']=$workload['notinum'];
    $data['bidproc']='S';

    try{
      $html=$http->request('GET',static::URL_M,['query'=>['BidNo'=>$workload['notinum']]]);
      $html=strip_tags($html,'<tr><td>');
      $html=preg_replace('/<tr[^>]*>/','<tr>',$html);
      $html=preg_replace('/<td[^>]*>/','<td>',$html);
      $html=str_replace('&nbsp;',' ',$html);
      \Yii::info($workload['notinum']."\n$html",'kwater');
      //추첨번호
      $p='/추첨된번호: (?<no>\d{1,2}) 번/';
      $p=str_replace(' ','\s*',$p);
      if(preg_match_all($p,$html,$matches,PREG_SET_ORDER)){
        $selms=[];
        foreach($matches as $m){
          $selms[]=$m['no'];
        }
        sort($selms);
        $data['selms']=join('|',$selms);
        if(count($selms)==1) $data['selms']='';
      }

      //개찰결과
      $html=$http->request('GET',static::URL,['query'=>['BidNo'=>$workload['notinum']]]);
      $html=strip_tags($html,'<tr><td>');
      $html=preg_replace('/<tr[^>]*>/','<tr>',$html);
      $html=preg_replace('/<td[^>]*>/','<td>',$html);
      $html=str_replace('&nbsp;',' ',$html);
      $p='#<tr> <td>예정가격</td>( <td>[^<]*</td>){2} </tr>'.
         ' <tr> <tr> <td> (?<yega>\d{1,3}(,\d{3})*) 원 </td>( <td>[^<]*</td>){2} </tr>#';
      $p=str_replace(' ','\s*',$p);
      if(preg_match($p,$html,$m)){
        $data['yega']=str_replace(',','',$m['yega']);
      }

      //참여업체
      $succoms=[];
      $s_plus=[];
      $s_minus=[];
      $p='#<tr>'.
         ' <td>(?<seq>\d+)</td>'.
         ' <td>(?<officenm>[^<]*)</td>'.
         ' <td>(?<pct>[^<]*)</td>'.
         ' <td>(?<success>[^<]*)</td>'.
         ' <td>(?<etc>[^<]*)</td>'.
         ' </tr>#';
      $p=str_replace(' ','\s*',$p);
      if(preg_match_all($p,$html,$matches,PREG_SET_ORDER)){
        foreach($matches as $m){
          $s=[
            'seq'=>$m['seq'],
            'officenm'=>trim($m['officenm']),
            'pct'=>substr(str_replace('%','',trim($m['pct'])),0,8),
            'success'=>str_replace(',','',trim(str_replace('원','',$m['success']))),
            'etc'=>trim($m['etc']),
          ];
          $succoms[$s['seq']]=$s;
          switch($s['etc']){
            case '적격심사 1 순위':
            case '낙찰예정자':
            case '우선협상대상자':
              $data['success1']=$s['success'];
              $data['officenm1']=$s['oficenm'];
              break;
          }
          if(isset($data['success1'])){
            $s_plus[]=$s['seq'];
          }else{
            $s_minus[]=$s['seq'];
          }
        }
      }

      //최저가
      if(empty($s_plus)){
        $i=1;
        foreach($s_minus as $seq){
          $succoms[$seq]['rank']=$i;
          if($i==1){
            $data['success1']=$succoms[$seq]['success'];
            $data['officenm1']=$succoms[$seq]['officenm1'];
          }
          $i++;
        }
      }else{
        $i=1;
        foreach($s_plus as $seq){
          $succoms[$seq]['rank']=$i;
          $i++;
        }
        $i=count($s_minus)*-1;
        foreach($s_minus as $seq){
          $succoms[$seq]['rank']=$i;
          $i++;
        }
      }
      $data['succoms']=$succoms;
      $data['innum']=count($succoms);

      $event=new \kwater\WatchEvent;
      $event->row=$data;
      $this->trigger(\kwater\WatchEvent::EVENT_ROW,$event);
    }
    catch(\Exception $e){
      echo Console::renderColoredString("%r$e%n"),PHP_EOL;
      \Yii::error($e,'kwater');
    }
    $this->module->db->close();
    echo sprintf("[%s] Peak memory usage: %sMb\n",
      date('Y-m-d H:i:s'),
      (memory_get_peak_usage(true)/1024/1024)
    );
    sleep(1);
  }
}

