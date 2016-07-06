<?php
namespace kwater\watchers;

use Exception;

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
    $params=['NtcInd'=>'0'];

    try {
      $http->get('/',['debug'=>true]);
      $html=$http->get(self::URL,[
        'debug'=>true,
        'query'=>$params,
      ]);
    }
    catch(Exception $e){
      throw $e;
    }
  }
}
