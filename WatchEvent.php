<?php
namespace kwater;

class WatchEvent extends \yii\base\Event
{
  const EVENT_ROW='event_row';

  public $row;
}

