<?php
namespace kwater\workers;

use yii\helpers\Json;
use yii\helpers\Console;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;

class BidWorker extends \yii\base\Component
{
  const URL='/SrcWeb/BD/FZBD1020.asp';
  private $module;

  public function init(){
    parent::init();
    $this->module=\kwater\Module::getInstance();
  }

  public function work($job){
    \Yii::info('['.__METHOD__.'] $workload'.PHP_EOL.VarDumper::dumpAsString($job->workload()),'kwater');
    $workload=Json::decode($job->workload());
    $http=new \kwater\Http;
    $data=[];

    try {
      if(empty($workload['notinum'])) throw new \Exception('notinum is empty');
      if(empty($workload['bidtype'])) throw new \Exception('bidtype is empty');

      $data['org_i']='한국수자원공사';
      if($workload['realorg']) $data['org_i'].=' '.str_replace('관리처','지역본부',$workload['realorg']);

      $data['notinum']=$workload['notinum'];
      switch($workload['bidtype']){
        case '공사':
          $data['bidtype']='con';
          $data['bidview']='con';
          break;
        case '용역':
          $data['bidtype']='ser';
          $data['bidview']='ser';
          break;
        default:
          $data['bidtype']='pur';
          $data['bidview']='pur';
      }
      $html=$http->request('GET',static::URL,[
        'query'=>[
          'BidNo'=>$workload['notinum'],
        ],
      ]);
      $thml=strip_tags($html,'<tr><td><a>');
      $html=preg_replace('/<tr[^>]*>/','<tr>',$html);
      $html=preg_replace('/<td[^>]*>/','<td>',$html);
      $html=str_replace('&nbsp;','',$html);
      $data['attchd_lnk']=$this->attchd_lnk($html);
      $html=strip_tags($html,'<tr><td>');
      //echo $html,PHP_EOL;
      $data['constnm']=$this->constnm($html);
      $contract=$this->contract($html);
      if($contract=='소액전자'){
        $data['contract']='40';
      }
      $succls=$this->succls($html);
      switch($succls){
        case '적격심사': $data['succls']='01'; break;
        case '최저가': $data['succls']='02'; break;
        default: $data['succls']='00';
      }
      $data['registdt']=$this->registdt($html);
      $data['multispare']=$this->multispare($html);
      $data['constdt']=$this->constdt($html);
      //$data['bidcomment']=$this->bidcomment($thml);
      $data['charger']=$this->charger($html);
      $data=ArrayHelper::merge($data,$this->closedt($html));
      $convention=$this->convention($html);
      $data['convention']='0';
      if(ArrayHelper::isIn($convention['convention1'],['가능','공동'])){
        $data['convention']='2';
      }
      if(ArrayHelper::isIn($convention['convention2'],['가능','공동'])){
        $data['convention']='2';
      }
      $data['orign_lnk']='http://ebid.kwater.or.kr/fz?bidno='.$workload['notinum'];
      $data['noticedt']=$this->noticedt($html);
      $data['basic']=$this->basic($html);
      $data['pqdt']=$this->pqdt($html);
      //현장설명회
      if($data['pqdt']){
        $data['bidcomment']='PQ심사신청서 신청기한 : '.$data['pqdt'].'<hr>';
        $data['succls']='05';
      }


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

  public static function match($pattern,$html,$label){
    $pattern=str_replace(' ','\s*',$pattern);
    $ret='';
    if(preg_match($pattern,$html,$m)){
      if(is_array($label)){
        $ret=[];
        foreach($label as $l){
          $ret[$l]=trim($m[$l]);
        }
      }else{
        $ret=trim($m[$label]);
      }
    }
    return $ret;
  }

  public function basic($html){
    $p='#<td>발주금액</td> <td>(?<basic>\d{1,3}(,\d{3})*)원 </td>#';
    $s=static::match($p,$html,'basic');
    return str_replace(',','',$s);
  }

  public function noticedt($html){
    $p='#<td> 입찰공고 </td> <td>(?<noticedt>[^<]*)</td> <td>[^<]*</td>#';
    $s=static::match($p,$html,'noticedt');
    return $s;
  }

  public function pqdt($html){
    $p='#<td> PQ평가심사신청 </td> <td>[^<]*</td> <td>(?<pqdt>[^<]*)</td>#';
    $s=static::match($p,$html,'pqdt');
    return $s;
  }

  public function closedt($html){
    $p='#<td> 가격입찰서제출 </td> <td>(?<opendt>[^<]*)</td> <td>(?<closedt>[^<]*)</td>#';
    $a=static::match($p,$html,['opendt','closedt']);
    if(empty($a)) return ['opendt'=>'','closedt'=>''];
    return $a;
  }

  public function constdt($html){
    $p='#<td> 예정가격추첨 및 (낙찰처리|우선협상자선정) </td> <td>[^<]*</td> <td>(?<constdt>[^<]*)</td>#';
    return static::match($p,$html,'constdt');
  }

  public function bidcomment($html){
    $p='#<td>자격 및 유의사항 </td> <td>(?<bidcomment>[^<]*)</td>#';
    return static::match($p,$html,'bidcomment');
  }

  public function constnm($html){
    $p='#<td>공고명\(한글\)</td> <td>(?<constnm>[^<]*)</td>#';
    return static::match($p,$html,'constnm');
  }

  public function contract($html){
    $p='#<td>계약방법</td> <td>(?<contract>[^<]*)</td>#';
    return static::match($p,$html,'contract');
  }

  public function succls($html){
    $p='#<td>낙찰자결정방법</td> <td>(?<succls>[^<]*)</td>#';
    return static::match($p,$html,'succls');
  }

  public function registdt($html){
    $p='#<td> 입찰참가신청 </td> <td>[^<]*</td> <td>(?<registdt>[^<]*)</td>#';
    return static::match($p,$html,'registdt');
  }

  public function multispare($html){
    $p='#<td>NO\.\d{1,2}</td>[^\d]+(?<price>\d{1,3}(,\d{3})*) 원#';
    if(preg_match_all($p,$html,$matches,PREG_SET_ORDER)){
      $ret=[];
      foreach($matches as $m){
        $ret[]=str_replace(',','',$m['price']);
      }
      return join('|',$ret);
    }
    return '';
  }

  public function charger($html){
    $p='#<td>입찰집행부서 및 담당자 </td> </tr> <tr> <td></td> </tr> <tr>'.
        ' <td>[^<]*</td>'.
        ' <td>(?<name>[^<]*)</td>'.
        ' <td>전화번호</td>'.
        ' <td>(?<tel>[^<]*)</td> </tr>#';
    $a=static::match($p,$html,['name','tel']);
    return join('|',$a);
  }

  public function attchd_lnk($html){
    $p='#<a[^>]*Popup1\(\'(?<filename>.*)\'\)[^>]*> <img[^>]*>.*</a>#';
    $p=str_replace(' ','\s*',$p);
    $ret=[];
    if(preg_match_all($p,$html,$matches,PREG_SET_ORDER)){
      foreach($matches as $m){
        $ret[]=$m['filename'].'#'.'http://ebid.kwater.or.kr/SrcWeb/CM/FZCMDOWN.asp?fileName='.$m['filename'];
      }
    }
    return join('|',$ret);
  }

  public function convention($html){
    $p='#<td>공동(분담|업체)여부</td> <td>(?<convention1>[^<]*)</td> <td>분담가능여부</td> <td>(?<convention2>[^<]*)</td>#';
    $a=static::match($p,$html,['convention1','convention2']);
    if(empty($a)) return ['convention1'=>'','convention2'=>''];
    return $a;
  }
}

