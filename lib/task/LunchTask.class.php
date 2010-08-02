<?php
function lowpass($ar, $upper)
{
  $result = array();
  foreach($ar as $a) {
    if ($a <= $upper) $result[] = $a;
  }
  return $result;
}
function choose($ar)
{
  return $ar[rand(0,count($ar)-1)];
}
function abc($i)
{
  return chr(0x41+$i); // 0 -> 'A', 1 -> 'B', 2 -> 'C', ...
}
function unabc($abc)
{
  return ord($abc) - 0x41; // 'A' -> 0, 'B' -> 1, 'C' -> 2, ...
}
function mxm($M,$initial)
{
  // vector<vector<int> > mxm(M, vector<int>(M, initial));
  $mxm = array();
  for ($i=0; $i<$M; $i++) {
    $mxm[$i] = array();
    for ($j=0; $j<$M; $j++) {
      $mxm[$i][$j] = 0;
    }
  }
  return $mxm;
}
function makeMatchTable($M, $groups)
{
  $table = mxm($M,0);
  foreach ($groups as $group)
  {
    $n = count($group);
    for ($i=0; $i<$n-1; $i++)
    {
      for ($j=$i+1; $j<$n; $j++)
      {
        $id1 = $group[$i];
        $id2 = $group[$j];
        $table[$id1][$id2] = $table[$id2][$id1] = 1;
      }
    }
  }
  return $table;
}
function pp($arr, $bool=false)
{
  foreach ($arr as $r => $row) {
    foreach ($row as $c => $col) {
      if ($bool)
        printf(" %s", $col ? 'x' : '.');
      else
        printf(" %d", $col);
    }
    printf("\n");
  }
}

function p($ar, $bool=false)
{
  foreach ($ar as $i => $elem) {
    if ($bool)
      printf(" %s", $elem ? 'x' : '.');
    else
      printf(" %d", $elem);
  }
  printf("\n");
}

class LunchEventTask extends sfBaseTask
{
  private $members = null;

  private $possible_group_names = '[A-E]';
  private $infty = 9;
  private $footer_identifier = '■ランチ時間帯';

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
 
    switch ($arguments['mode'])
    {
      case 'create':
        $this->test_createevent();
        break;
      case 'shuffle':
        $this->shuffle();
        break;
      case 'shuffleMCMC':
        $this->shuffleMCMC();
        break;
      case 'shuffleMCMC2':
        $this->shuffleMCMC(0.333333);
        break;
      default:
        printf("LunchTask doesn't support the mode '%s'.\n", $arguments['mode']);
        break;
    }
  }

  // ランチコミュニティのcommunity_idを返す
  private function getLunchRandomizerCommunityId($default=null)
  {
    return (int)Doctrine::getTable('SnsConfig')->get('oplunchrandomizerplugin_lr_community',$default);
  }
  private function getLunchRandomizerBody($default='none')
  {
    return Doctrine::getTable('SnsConfig')->get('oplunchrandomizerplugin_lr_body',$default);
  }
  private function getLunchRandomizerFooter($default='')
  {
    return Doctrine::getTable('SnsConfig')->get('oplunchrandomizerplugin_lr_footer',$default);
  }
  private function getLunchRandomizerAgentId($default=1)
  {
    return (int)Doctrine::getTable('SnsConfig')->get('oplunchrandomizerplugin_lr_from',$default); // member_id
  }

  // (sns_configテーブル上の)ターゲットイベントIDを得る/セットする
  private function getTargetLunchEventId()
  {
    return (int)Doctrine::getTable('SnsConfig')->get('target_lunch_event');
  }
  private function setTargetLunchEventId($event_id)
  {
    Doctrine::getTable('SnsConfig')->set('target_lunch_event',$event_id);
  }

  // 今日または直近のランチイベントのIDを返す。sns_configテーブルは見ない。
  private function latestLunchEventId()
  {
    $q = Doctrine_Query::create()
      ->select("max(id) as last_id")
      ->from("CommunityEvent ce")
      ->where("ce.community_id=? AND open_date<=now()", $this->getLunchRandomizerCommunityId());
    $rs = $q->fetchOne();
    $event_id = $rs['last_id'];

    return $event_id;
  }

  // n回前のランチイベントのidを調べる。
  private function getPreviousLunchEventId($n=1)
  {
    $curr_event_id = $this->getTargetLunchEventId();
//  $curr_event_id = $this->latestLunchEventId();

    for ($d=$n; $d>0; $d--)
    {
      $q = Doctrine_Query::create()
        ->select("max(id) as last_id")
        ->from("CommunityEvent ce")
        ->where("ce.community_id=? AND ce.id<?", array($this->getLunchRandomizerCommunityId(),$curr_event_id) );
      $rs = $q->fetchOne();
      $curr_event_id = $rs['last_id'];
      if (is_null($curr_event_id)) break;
    }
    return $curr_event_id;
  }

  // あるランチイベントにおけるグループ分割の結果が含まれるコメントIDを得る
  private function getGroupingCommentIdFromEventId($event_id)
  {
    $q = Doctrine_Query::create()
      ->select("max(id) as result_id")
      ->from("CommunityEventComment cec")
      ->where("cec.community_event_id=? AND cec.body LIKE '%". $this->footer_identifier ."%'", $event_id);
    $rs = $q->fetchOne();
    return $rs['result_id'];
  }

  // コメントから分割結果を抽出
  private function getGrouping($comment_id)
  {
    $groups = array();

    $body = Doctrine::getTable('CommunityEventComment')->find($comment_id)->getBody();
    $lines = split("[\r\n]+", $body);
    $g = null;
    foreach ($lines as $line)
    {
      if (!$line)
      {
        $g = null;
      }
      elseif (preg_match('/^[A-E][ \t\r\n]?$/', $line))
      {
        $g = $line;
        $groups[$g] = array();
      }
      elseif (preg_match('/'.$this->footer_identifier.'/',$line))
      {
        continue;
      }
      else
      {
        if ($g) $groups[$g][] = $line;
      }
    }
    return $groups;
  }

  // n回前のグループ分割を得る
  private function getPreviousGrouping($n=1)
  {
    $event_id   = $this->getPreviousLunchEventId($n);
    $comment_id = $this->getGroupingCommentIdFromEventId($event_id);
    $groups     = $this->getGrouping($comment_id);
    return $groups;
  }

  // グループリストを、名前ベースからmember_idベースに変換する
  private function groupsInMemberId($groups)
  {
    // (map (cut map nameTomemberId <>) groups)
    $groups2 = array();
    foreach ($groups as $group)
    {
      $group2 = array();
      foreach ($group as $name)
      {
        $group2[] = $this->nameToMemberId($name);
      }
      $groups2[] = $group2;
    }
    return $groups2;
  }

  // メンバー一覧（名前とmember_idの対照）の取得
  private function loadMemberData()
  {
    if (is_null($this->members))
    {
      $this->members = array();
      foreach (Doctrine::getTable('Member')->findAll() as $member)
      {
        $nickname  = $member->name;
        $member_id = (int)$member->id;
        $this->members[$nickname] = $member_id;
        $this->members["#REV/".$member_id] = $nickname;
      }
    }
  }

  // member_idから名前を得る
  private function nameFromMemberId($member_id)
  {
    $this->loadMemberData();
    return $this->members["#REV/".$member_id];
  }

  // 名前からmember_idを得る
  private function nameToMemberId($name)
  {
    $this->loadMemberData();
    return $this->members[$name];
  }

  // 参加者のリクエストを読み取る
  private function getRequests()
  {
    $target_event_id = $this->getTargetLunchEventId();

    if(!$target_event_id){
      echo "no target event";
      return null;
    }

    $eMembersList = Doctrine::getTable('CommunityEventMember')->findByCommunityEventId($target_event_id);

    $requests = array();

    foreach($eMembersList as $member){
      $m = Doctrine::getTable('Member')->find($member->member_id);
      $requests[$member->member_id]['nickname'] = $m->name;
    }

    $member_count = count($requests);

    //対象イベントのコメント。参加希望を取得する
    $commentList = Doctrine::getTable('CommunityEventComment')->findByCommunityEventId($target_event_id);
    foreach($commentList as $comment){
      $body = $comment->getBody();
      $body = strtoupper(mb_convert_kana($body,'a','UTF8'));
      $body = preg_replace('/で|希望/','陣',$body);
      $body = preg_replace('/～|~|から|以降/','-',$body);
      $body = preg_replace('/('.$this->possible_group_names.')(まで|より前|以前)/','-$1',$body);

      $member_id = $comment->getMemberId();
      if (!isset($requests[$member_id])) continue;

      $min = 0;
      $max = $this->infty;

      if (preg_match('/('.$this->possible_group_names.')--?('.$this->possible_group_names.'?)/',$body,$match))
      {
        $min = unabc($match[1]);
        if ($match[2])
        {
          $max = unabc($match[2]);
        }
        $candidates = range($min,$max);
      }
      elseif (preg_match('/-('.$this->possible_group_names.')/',$body,$match))
      {
        $max = unabc($match[1]);
        $candidates = range($min,$max);
      }
      elseif (preg_match('/('.$this->possible_group_names.')/',$body,$match))  
      {
        $min = $max = unabc($match[1]);
        $candidates = array($min);
      }
      elseif (preg_match('/(X|別)/',$body,$match))
      {
        $candidates = array();
        $min = $max = $this->infty;
        $member_count--;
      }
      else
      {
        // any
        $candidates = range(0, $this->infty);
      }

      $requests[$member_id]['candidates'] = $candidates;
      $requests[$member_id]['member_id']  = $member_id;
    }
          
    //グループ数目安計算
    $group_count = floor(($member_count + 3) / 4);
    foreach ($requests as $i => $request) {
      $requests[$i]['candidates'] = lowpass($request['candidates'], $group_count-1);
    }

    return array('requests'=>$requests, 'member_count'=>$member_count, 'group_count'=>$group_count);
  }

  // 単純なシャッフル。過去の結果は考慮に入れない。各グループが3人か4人になるよう修正した戸塚版ベース
  private function shuffle(){
    $req = $this->getRequests();

    $requests = $req['requests'];
    $group_count = $req['group_count']; //グループ数

    $groups = array();
    for ($g=0; $g<$group_count; $g++) $groups[abc($g)] = array();

    //シャッフル
    shuffle($requests);

    //希望枠が１つだけの人を最優先に決定
    foreach ($requests as $i => $member)
    {
      $member_id = $member['member_id'];
      $candidates = $member['candidates'];

      switch (count($candidates)) {
        case 0:
          $groups['X'][] = $member_id;
          unset($requests[$i]);
          break;
        case 1:
          $groups[abc($candidates[0])][] = $member_id;
          unset($requests[$i]);
          break;
      }
    }

    //希望あり優先決定
    foreach ($requests as $i => $member)
    {
      $candidates = $member['candidates'];

      if (count($candidates) == $group_count)
      {
        // どこでもいい人は後回し
        continue;
      }

      $member_id = $member['member_id'];
      $g = choose($candidates);
      $groups[abc($g)][] = $member_id;
      unset($requests[$i]);
    }

    // 3人で埋めてから
    foreach ($requests as $i => $member)
    {
      for ($g=0; $g<$group_count; $g++)
      {
        if (count($groups[abc($g)]) < 3)
        {
          $member_id = $member['member_id'];
          $groups[abc($g)][] = $member_id;
          unset($requests[$i]);
          break;
        }
      }
    }
    // 4人目を入れる
    foreach ($requests as $i => $member)
    {
      for ($g=0; $g<$group_count; $g++)
      {
        if (count($groups[abc($g)]) < 4)
        {
          $member_id = $member['member_id'];
          $groups[abc($g)][] = $member_id;
          unset($requests[$i]);
          break;
        }
      }
    }

	$this->postResult($groups);
  }

  // 過去（最大で直近2日分）の結果を踏まえつつ、できるだけかぶらないようなランダムなグループを作る
  private function shuffleMCMC($second_weight=0) // 0.0≦$second_weight≦1.0
  {
    $req = $this->getRequests();
    
    $requests = $req['requests'];
    $member_count = $req['member_count']; // 'X'でないメンバー数
    $group_count = $req['group_count']; //グループ数

    // 全メンバー対照をロード
    $this->loadMemberData();
    $M = count($this->members)/2 + 1;

	$prev1 = makeMatchTable($M, $this->groupsInMemberId( $this->getPreviousGrouping(1) ) );
	$prev2 = makeMatchTable($M, $this->groupsInMemberId( $this->getPreviousGrouping(2) ) );

    $result = null;
    $trial_count = 5000;
    $minimum_conflict = $M * $M * 2;

    for ($t=0; $t<$trial_count; $t++) {
      $groups = array();
      for ($g=0; $g<$group_count; $g++) $groups[abc($g)] = array();
      if (count($requests) > $member_count) $groups['X'] = array();

      $belongs = array();

      foreach ($requests as $i => $request)
      {
        $member_id  = $request['member_id'];
        $candidates = $request['candidates'];
        if (count($candidates)==0)
        {
          $g = $this->infty;
        }
        else
        {
          $g = choose($candidates);
//          printf("member#%d (%s) --> choose(...)=%d; ", $member_id, $this->nameFromMemberId($member_id), $g); p($candidates);
        }
        $groups[abc($g)][] = $member_id;
        $belongs[$member_id] = $g;
      }
      ksort($groups);

      $matching = mxm($M,0);
      $score = 0;

      // 3人か4人でない組があればはじく
      $ok = true;
      foreach ($groups as $i => $group)
      {
        if ($i == $this->infty) continue;
        $cnt = count($group);
        if ($cnt < 3 || 4 < $cnt)
        {
          $ok = false; break;
        }

        for ($a=0; $a<$cnt-1; $a++)
        {
          for ($b=$a+1; $b<$cnt; $b++)
          {
            $id_a = $group[$a];//['id'];
            $id_b = $group[$b];//['id'];
            $matching[$id_a][$id_b] = $matching[$id_b][$id_a] = 1;
          }
        }
      }
      if (!$ok) continue;
    
      // conflict count
      $conflict = 0;
      for ($x=0; $x<$M-1; $x++)
      {
        for ($y=$x+1; $y<$M; $y++)
        {
          if ($matching[$x][$y] == 0) continue;
          if ($prev1[$x][$y]) $conflict += 1.0;
          if ($prev2[$x][$y]) $conflict += $second_weight;
        }
      }

      if ($conflict < $minimum_conflict)
      {
//        printf("T=%d: score=%g; ", $t, $conflict); p($belongs,false);
        $minimum_conflict = $conflict;
        $result = $groups;
      }
    }

	$this->postResult($result);
  }

  //コメントとしてシャッフル結果を発表
  private function postResult($groups,$post=true) // groups['A'] = (1,2,3), ...
  {
    $result = "";

    ksort($groups);
    foreach ($groups as $groupName => $group)
    {
      $result .= $groupName . "\n";
      foreach ($group as $member_id)
      {
        $result .= $this->nameFromMemberId($member_id) . "\n";
      }
    }
    $result .= "\n\n";
    $result .= $this->getLunchRandomizerFooter();

    //シャッフル結果をコメントに書き込む
    if ($post)
    {
      $target_event_id = $this->getTargetLunchEventId();
      $agent_id = $this->getLunchRandomizerAgentId();

      $comment = new CommunityEventComment();
      $comment->setCommunityEventId($target_event_id);
      $comment->setMemberId($agent_id);
      $comment->setBody($result);
      $comment->save();

      $this->setTargetLunchEventId(null);
      $this->log2activity($agent_id, 'ランチメンバーシャッフル完了！');
    } else {
      print_r($result);
    }
  }

  private function test_createevent()
  {
    $event_date = strtotime('tomorrow');
    $title = date('m-d',$event_date) .  'のランチイベント';
    $body = $this->getLunchRandomizerBody();
    $member_id = $this->getLunchRandomizerAgentId();
    $community_id = $this->getLunchRandomizerCommunityId();

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

    $this->setTargetLunchEventId($event->id);
    $this->log2activity($member_id,'ランチイベントを作成！');
  }

  private function test_getmember(){
    $member = Doctrine::getTable('Member')->find(1);
    print_r($member);
  }

  private function log2activity($id,$body){
    $act = new ActivityData();
    $act->setMemberId($id);
    $act->setBody($body);
    $act->setIsMobile(0);
    $act->save();
  }
}
