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
    $body = Doctrine::getTable('SnsConfig')->get('oplunchrandomizerplugin_lr_body','none');
    $member_id = (int)Doctrine::getTable('SnsConfig')->get('oplunchrandomizerplugin_lr_from',null);
    $community_id = (int)Doctrine::getTable('SnsConfig')->get('oplunchrandomizerplugin_lr_community',null);

    //$member_id = 1;
    //$community_id = 1;

    //$line = exec($cmd);
    $event = new CommunityEvent();
    $event->setCommunityId($community_id);
    $event->setMemberId($member_id);
    $event->setName($title);
    $event->setBody($body);
    $event->setOpenDate(date("Y-m-d",$event_date));
    $event->save();
  }
}
