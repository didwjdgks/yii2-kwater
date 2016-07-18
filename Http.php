<?php
namespace kwater;

class Http extends \yii\base\Component
{
  protected $client;

  public function init(){
    parent::init();
    
    $this->client=new \GuzzleHttp\Client([
      'base_uri'=>'http://ebid.kwater.or.kr',
      'cookies'=>true,
      'allow_redirects'=>false,
      'headers'=>[
        'User-Agent'=>'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 10.0; WOW64; Trident/7.0; .NET4.0C; .NET4.0E; .NET CLR 2.0.50727; .NET CLR 3.0.30729; .NET CLR 3.5.30729)',
        'Accept-Language'=>'ko',
        'Accept-Encoding'=>'gzip, deflate',
        'DNT'=>'1',
        'Pragma'=>'no-cache',
        'Connection'=>'Keep-Alive',
      ],
    ]);
    $this->client->get('/default_new.asp');
  }

  public static function sleep(){
    $module=Module::getInstance();
    sleep(mt_rand($module->delay_min,$module->delay_max));
  }

  public function request($method,$uri='',array $options=[]){
    $res=$this->client->request($method,$uri,$options);
    $body=$res->getBody();
    $html=iconv('euckr','utf-8//IGNORE',$body);
    $html=strip_tags($html,'<tr><td>');
    $html=preg_replace('/<td[^>]*>/','<td>',$html);
    $html=preg_replace('/<tr[^>]*>/','<tr>',$html);
    $html=str_replace('&nbsp;','',$html);
    return $html;
  }

  public function get($uri,array $options=[]){
    return $this->request('GET',$uri,$options);
  }
}

