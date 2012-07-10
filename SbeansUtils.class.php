<?php
/**
 * This class contains utility methods specific to Studentbeans
 */
class SbeansUtils
{

  /*
   * Returns whether the autoactivation for user accounts is enabled
   * 
   * @returns boolean
   */
  public static function isAutoActivationEnabled()
  {
    $c = new Criteria();
    $c->add(SbSettingPeer::NAME, 'accountAutoActivation');
    $accountAutoActivation = SbSettingPeer::doSelectOne($c);

    if (is_object($accountAutoActivation) && ($accountAutoActivation->getValue() === '1'))
    {
      return true;
    }

    return false;
  }

  /*
   * Sets or unsets the auto-activation of user accounts
   *
   * @param integer (either 0 or 1)
   *
   */
  public static function setAutoActivation($enable)
  {
    $enable = (int)$enable;

    if (($enable != 0) && ($enable != 1))
    {
      throw new Exception('Wrong paramenter for auto-activation.');
    }

    if (($enable == 0) || ($enable == 1))
    {
      $c = new Criteria();
      $c->add(SbSettingPeer::NAME, 'accountAutoActivation');
      $accountAutoActivation = SbSettingPeer::doSelectOne($c);
      $accountAutoActivation->setValue($enable)->save();
    }

  }

}