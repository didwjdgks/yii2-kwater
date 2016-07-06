<?php
namespace kwater;

use yii\db\Connection;
use yii\di\Instance;

class Module extends \yii\base\Module
{
  public $db='i2db';

  public $gman_server;
  public $redis_server;

  public $http;

  public $delay_min=1;
  public $delay_max=5;

  public function init(){
    parent::init();

    $this->db=Instance::ensure($this->db,Connection::className());

    $this->http=new Http;
  }
}

