<?php 
class LunchRandomizerPluginConfigForm extends sfForm
{
  protected $configs = array(
    'lr_body' => 'oplunchrandomizerplugin_lr_body',
    'lr_footer' => 'oplunchrandomizerplugin_lr_footer',
    'lr_from' => 'oplunchrandomizerplugin_lr_from',
    'lr_community' => 'oplunchrandomizerplugin_lr_community',
  );
  public function configure()
  {
    $this->setWidgets(array(
      'lr_body' => new sfWidgetFormTextArea(),
      'lr_footer' => new sfWidgetFormTextArea(),
      'lr_from' => new sfWidgetFormInput(),
      'lr_community' => new sfWidgetFormInput(),
    ));
    $this->setValidators(array(
      'lr_body' => new sfValidatorString(array(),array()),
      'lr_footer' => new sfValidatorString(array(),array()),
      'lr_from' => new sfValidatorString(array(),array()),
      'lr_community' => new sfValidatorString(array(),array()),
    ));

    $this->widgetSchema->setHelp('lr_body', 'イベント本文');
    $this->widgetSchema->setHelp('lr_footer', 'イベントフッター文章');
    $this->widgetSchema->setHelp('lr_from', 'イベント作成者(エージェント)のメンバーID');
    $this->widgetSchema->setHelp('lr_community', '対象のコミュニティID');

    foreach($this->configs as $k => $v)
    {
      $config = Doctrine::getTable('SnsConfig')->retrieveByName($v);
      if($config)
      {
        $this->getWidgetSchema()->setDefault($k,$config->getValue());
      }
    }
    $this->getWidgetSchema()->setNameFormat('lr[%s]');
  }
  public function save()
  {
    foreach($this->getValues() as $k => $v)
    {
      if(!isset($this->configs[$k]))
      {
        continue;
      }
      $config = Doctrine::getTable('SnsConfig')->retrieveByName($this->configs[$k]);
      if(!$config)
      {
        $config = new SnsConfig();
        $config->setName($this->configs[$k]);
      }
      $config->setValue($v);
      $config->save();
    }
  }
  public function validate($validator,$value,$arguments = array())
  {
    return $value;
  }
}

