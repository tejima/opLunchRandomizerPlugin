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

    $this->addArgument('mode', null , sfCommandOption::PARAMETER_REQUIRED, 'mode');
  }
  protected function execute($arguments = array(),$options = array())
  {
    $databaseManager = new sfDatabaseManager($this->configuration);
    if($arguments['mode'] == 'create'){
      $this->test_createevent();
    } else if($arguments['mode'] == 'shuffle') {
      $this->shuffle();
    }
  }
  private function test_getmember(){
    $member = Doctrine::getTable('Member')->find(1);
    print_r($member);
  }
  private function shuffle(){
    $possible_group_names = '[A-E]';
    $infty = 9999;

    $target_event_id = Doctrine::getTable('SnsConfig')->get('target_lunch_event');

    if(!$target_event_id){
      echo "no target event";
      return false;
    }

    $eMembersList = Doctrine::getTable('CommunityEventMember')->findByCommunityEventId($target_event_id);
    $members = array();
    foreach($eMembersList as $member){
      $m = Doctrine::getTable('Member')->find($member->member_id);
      $members[$member->member_id]['nickname'] = $m->name;
    }
    $member_count = count($members);

    //対象イベントのコメント。参加希望を取得する
    $commentList = Doctrine::getTable('CommunityEventComment')->findByCommunityEventId($target_event_id);
    foreach($commentList as $comment){
      $body = $comment->getBody();
      $body = strtoupper(mb_convert_kana($body,'a','UTF8'));
      $body = preg_replace('/で|希望/','陣',$body);
      $body = preg_replace('/～|~|から|以降/','-',$body);
      $body = preg_replace('/('.$possible_group_names.')(まで|より前|以前)/','-$1',$body);

      $member_id = $comment->getMemberId();
      if (!isset($members[$member_id]))
      {
        continue;
      }

      $min = 0;
      $max = $infty;

      if (preg_match('/('.$possible_group_names.')--?('.$possible_group_names.'?)/',$body,$match))
      {
        $min = ord($match[1]) - 0x41;
        if ($match[2])
       {
          $max = ord($match[2]) - 0x41;
        }
      }
      elseif (preg_match('/-('.$possible_group_names.')/',$body,$match))
      {
        $max = ord($match[1]) - 0x41;
      }
      elseif (preg_match('/('.$possible_group_names.')/',$body,$match))  
      {
        $min = $max = ord($match[1]) - 0x41;
      }
      elseif (preg_match('/(X|別)/',$body,$match))
      {
        $min = $max = $infty;
        $member_count--;
      }
      else
      {
        // any
      }
      $members[$member_id]['min'] = $min;
      $members[$member_id]['max'] = $max;
    }

    //シャッフル
    shuffle($members);

    //グループ数目安計算
    $group_count = floor(($member_count + 3) / 4);
    $groups = array();
    for ($g=0; $g<$group_count; $g++)
    {
      $groups[$g] = array();
    }

    //希望枠が１つだけの人を最優先に決定
    foreach ($members as $i => $member)
    {
      $min = $member['min'];
      $max = $member['max'];

      if ($min == $infty)
      {
        $groups[$infty][] = $member;
        unset($members[$i]);
      }
      elseif ($min == $max)
      {
        $groups[$min][] = $member;
        unset($members[$i]);
      }
    }
    //希望あり優先決定
    foreach ($members as $i => $member)
    {
      $min = $member['min'];
      $max = $member['max'];
      if ($min == 0 && $max == $infty)
      {
        // どこでもいい人は後回し
        continue;
      }
      $g = rand($min, min($group_count-1,$max));
      $groups[$g][] = $member;
      unset($members[$i]);
    }

    // 3人で埋めてから
    foreach ($members as $i => $member)
    {
      for ($g=0; $g<$group_count; $g++)
      {
        if (count($groups[$g]) < 3)
        {
          $groups[$g][] = $member;
          unset($members[$i]);
          break;
        }
      }
    }
    // 4人目を入れる
    foreach ($members as $i => $member)
    {
      for ($g=0; $g<$group_count; $g++)
      {
        if (count($groups[$g]) < 4)
        {
          $groups[$g][] = $member;
          unset($members[$i]);
          break;
        }
      }
    }
    ksort($groups);
    //コメントとしてシャッフル結果を発表
    $result = "";
    foreach ($groups as $i => $group)
    {
      $groupName = ($i == $infty) ? 'X' : chr(0x41+$i);
      $result .= $groupName . "\n";
      foreach ($group as $j => $member)
      {
        $result .= $member['nickname'] . "\n";
      }
    }
    $result .= "\n\n";
    $result .= Doctrine::getTable('SnsConfig')->get('oplunchrandomizerplugin_lr_footer','');

    //シャッフル結果をコメントに書き込む
    $comment = new CommunityEventComment();
    $comment->setCommunityEventId($target_event_id);
    $comment->setMemberId(Doctrine::getTable('SnsConfig')->get('oplunchrandomizerplugin_lr_from',1));
    $comment->setBody($result);
    $comment->save();
    Doctrine::getTable('SnsConfig')->set('target_lunch_event',null);

    $this->log2activity(Doctrine::getTable('SnsConfig')->get('oplunchrandomizerplugin_lr_from',1),'ランチメンバーシャッフル完了！');
  }
  private function kibouList4text($text){
    
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

    $member = Doctrine::getTable('Member')->find($member_id);

    Doctrine::getTable('SnsConfig')->set('target_lunch_event',$event->id);

    $this->log2activity($member_id,'ランチイベントを作成！');
  }
  private function log2activity($id,$body){
    $act = new ActivityData();
    $act->setMemberId($id);
    $act->setBody($body);
    $act->setIsMobile(0);
    $act->save();
  }
}
