<?php
namespace kwater;

use Yii;

class BidFile extends \yii\base\Model
{
  public $realname;
  public $downloadUrl;
  public $saveDir='/tmp';
  public $savedName;

  public $homeUrl='http://ebid.kwater.or.kr/default_new.asp';
  public $cookieFile='/tmp/kwater.cookie';

  public static function findNoticeDoc($attchd_lnk){
    $attchd_lnks=explode('|',$attchd_lnk);
    if(empty($attchd_lnks)) return null;

    foreach($attchd_lnks as $i=>$lnk){
      list($realname,$downinfo)=explode('#',$lnk);
      if( (strpos($realname,'공고서')!==false) ||
          (strpos($realname,'입찰공고')!==false && strpos($realname,'내역서')===false) ||
          (strpos($realname,'공고문')!==false)
        ){
        return Yii::createObject([
          'class'=>static::className(),
          'downloadUrl'=>$downinfo,
          'realname'=>$realname,
        ]);
      }
    }

    list($realname,$downinfo)=explode('#',$attchd_lnks[0]);
    return Yii::createObject([
      'class'=>static::className(),
      'downloadUrl'=>$downinfo,
      'realname'=>$realname,
    ]);
  }

  public function download(){
    $cmd="wget --save-cookies $this->cookieFile --keep-session-cookies -U 'Mozilla/4.0' -q -T 30 -O $this->saveDir/ebid.kwater.co.kr '$this->homeUrl'";
    $res=exec($cmd,$output,$ret);
    if($ret!=0){
      return false;
    }

    $this->savedName=md5($this->realname);
    $filename=$this->saveDir.'/'.$this->savedName;
    $cmd="wget --load-cookies $this->cookieFile --keep-session-cookies -U 'Mozilla/4.0' -q -T 30 -O $filename '".iconv('utf-8','euckr',$this->downloadUrl)."'";
    $res=exec($cmd,$output,$ret);
    if($ret!=0){
      return false;
    }
    return true;
  }

  public function remove(){
    @unlink($this->saveDir.'/'.$this->savedName);
  }
}

