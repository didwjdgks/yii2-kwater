<?php
namespace kwater\watchers;

use Exception;
use kwater\WatchEvent;

class BidWatcher extends \yii\base\Component
{
  const URL='/SrcWeb/BD/FZBD1000.asp';

  public function watch(){
    $http=\kwater\Module::getInstance()->http;
    $params=[
      'BIDNAM'=>'',
      'NtcDtFrom'=>date('Y/m/d',strtotime('-1 month')),
      'NtcDtTo'=>date('Y/m/d'),
      'Pgsize'=>'100',
      'NtcInd'=>'0',
      'AbsolutePg'=>'1',
      'txtSortSeq'=>'DESC',
      'txtSortNm'=>'ancdt',
      'MYPAGE'=>'',
      'txtThtDtFrom'=>'',
      'txtThtDtTo'=>'',
      'txtSeqm'=>'',
      'txtdeptName'=>'',
    ];

    try {
      $html=$http->get(self::URL,['query'=>$params]);
      if(preg_match('#\[현재/전체페이지: \d+/(?<total_page>\d+)\]#',$html,$m)){
        $total_page=intval($m['total_page']);
      }
      if(!$total_page){ throw new Exception('total_page not found!'); }

      for($page=1; $page<=$total_page; $page++){
        if($page>1){
          $params['AbsolutePg']=$page;
          $html=$http->get(self::URL,['query'=>$params]);
        }
        $p='#<tr>'.
            ' <td> (?<noticedt>\d{2}/\d{2}/\d{2}) </td>'.
            ' <td>(?<registdt>[^<]*)</td>'.
            ' <td>(?<notinum>\d{4}-\d{4})</td>'.
            ' <td>(?<constnm>[^<]*)</td>'.
            ' <td>(?<contract>[^<]*)</td>'.
            ' <td>(?<status>[^<]*)</td>'.
            ' <td>(?<joins>[^<]*)</td>'. //참가
            ' <td>(?<shots>[^<]*)</td>'. //투찰
            ' <td>(?<bidtype>[^<]*)</td>'. //종류
            ' <td>(?<bidcls>[^<]*)</td>'. //구분
            ' <td> (?<basic>\d{1,3}(,\d{3})*) </td>'. //발주금액
            ' <td>(?<realorg>[^<]*)</td>'. //부서
            ' <td>(?<charger>[^<]*)</td>'. //담당자
           ' </tr>#';
        if(preg_match_all(str_replace(' ','\s*',$p),$html,$matches,PREG_SET_ORDER)){
          foreach($matches as $m){
            $data=[
              'noticedt'=>trim($m['noticedt']),
              'registdt'=>trim($m['registdt']),
              'notinum'=>trim($m['notinum']),
              'constnm'=>trim($m['constnm']),
              'contract'=>trim($m['contract']),
              'status'=>trim($m['status']),
              'joins'=>trim($m['joins']),
              'shots'=>trim($m['shots']),
              'bidtype'=>trim($m['bidtype']),
              'bidcls'=>trim($m['bidcls']),
              'basic'=>trim($m['basic']),
              'realorg'=>trim($m['realorg']),
              'charger'=>trim($m['charger']),
            ];
            $event=new WatchEvent;
            $event->row=$data;
            $this->trigger(WatchEvent::EVENT_ROW,$event);
          }
        }
        \kwater\Http::sleep();
      }
    }
    catch(Exception $e){
      throw $e;
    }
  }
}
