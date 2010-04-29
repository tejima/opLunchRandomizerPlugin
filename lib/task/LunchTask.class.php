<?php 
class LunchEventTask extends sfBaseTask
{
  protected function configure()
  {
    set_time_limit(120);
    mb_language("Japanese");
    mb_internal_encoding("utf-8");

    $this->namespace = 'SA';
    $this->name      = 'LunchEvent';
    $this->aliases   = array('sa-lunch');
    $this->breafDescription = '';
  }
  protected function execute($arguments = array(),$options = array())
  {
    $databaseManager = new sfDatabaseManager($this->configuration);
    $this->test_createevent();
  }
  private function test_createevent(){
    $event_date = strtotime('tomorrow');
    $title = date('m-d',$event_date) .  'のランチイベント';
    $body = <<< EOF
・11時35分にランチメンバー決定します。それまでに参加してください
・ミーティングや業務が忙しい人は、11時35分までにA～Eの時間指定のコメントし、イベントに参加する
・時間外で食べる場合は「Xで」と書き、イベントに参加する

例）
------
Aがいい時

A陣
Aで
------
B以降がいい時

B～
B以降
Bより後
------
B以前がいいとき

～B
B以前
Bより前
------
BからCを指定したいとき

B～C
BからC
B以降C以前
------

・アルファベットは小文字・半角・全角
　問いません。

・現在、A～Eのアルファベットに
　対応しています。

・人数が極端に少ないと、
　正常に希望通りのグループ分けが
　できません。
　ご了承ください。

A：11:50～12:50
B：12:20～13:20
C：12:40～13:40
D：13:00～14:00
E：13:00～14:00
X：時間外
EOF;

    //$line = exec($cmd);
    $event = new CommunityEvent();
    $event->setCommunityId(1);
    $event->setMemberId(1);
    $event->setName($title);
    $event->setBody($body);
    $event->setOpenDate(date("Y-m-d",$event_date));
    $event->save();
  }
}
