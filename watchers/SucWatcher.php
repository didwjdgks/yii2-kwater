<?php
namespace kwater\watchers;

use kwater\WatchEvent;

class SucWatcher extends \yii\base\Component
{
  const URL='/SrcWeb/BD/FZBD1040.asp';

  public function watch(){
    $http=\kwater\Module::getInstance()->http;
    $params=[
      'BIDNAM'=>'',
      'NtcDtFrom'=>date('Y/m/d',strtotime('-1 month')),
      'NtcDtTo'=>date('Y/m/d'),
      'txtThtDtFrom'=>'',
      'txtThtDtTo'=>'',
      'Pgsize'=>100,
      'NtcInd'=>0,
      'MYPAGE'=>'',
      'txtdeptName'=>'',
      'txtTimeGubun'=>'Y',
      'AbsolutePg'=>1,
    ];

    try {
      $html=$http->get(self::URL,['query'=>$params]);
      if(preg_match('#\[현재/전체페이지: \d+/(?<total_page>\d+)\]#',$html,$m)){
        $total_page=intval($m['total_page']);
      }
      if(!$total_page) throw new \Exception('total_page not found');

      for($page=1; $page<=$total_page; $page++){
        if($page>1){
          $params['AbsolutePg']=$page;
          $html=$http->get(self::URL,['query'=>$params]);
        }
        $p='#<tr>'.
            ' <td>(?<noticedt>[^<]*)</td>'.
            ' <td>(?<constdt>[^<]*)</td>'.
            ' <td>(?<notinum>\d{4}-\d{4})</td>'.
            ' <td>(?<constnm>[^<]*)</td>'.
            ' <td>(?<contract>[^<]*)</td>'.
            ' <td>(?<succls>[^<]*)</td>'.
            ' <td>(?<status>[^<]*)</td>'.
            ' <td>(?<success>[^<]*)</td>'.
            ' <td>(?<sucname>[^<]*)</td>'.
            ' <td>(?<bidtype>[^<]*)</td>'.
            ' <td>(?<org>[^<]*)</td>'.
            ' <td>(?<charger>[^<]*)</td>'.
           ' </tr>#';
        $p=str_replace(' ','\s*',$p);
        if(preg_match_all($p,$html,$matches,PREG_SET_ORDER)){
          foreach($matches as $m){
            $data=[
              'noticedt'=>trim($m['noticedt']),
              'constdt'=>trim($m['constdt']),
              'notinum'=>trim($m['notinum']),
              'constnm'=>trim($m['constnm']),
              'contract'=>trim($m['contract']),
              'succls'=>trim($m['succls']),
              'status'=>trim($m['status']),
              'success'=>trim($m['success']),
              'sucname'=>trim($m['sucname']),
              'bidtype'=>trim($m['bidtype']),
              'org'=>trim($m['org']),
              'charger'=>trim($m['charger']),
            ];
            $event=new WatchEvent;
            $event->row=$data;
            $this->trigger(WatchEvent::EVENT_ROW,$event);
          }
        }
        sleep(mt_rand(3,5));
      }
    }
    catch(\Exception $e){
      throw $e;
    }
  }
}

