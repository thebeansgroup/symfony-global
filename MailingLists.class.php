<?php
/**
 * This class manages synchronisation between the SendStudio and Mail Chimp
 * mailing lists.
 *
 * @package global
 */

// include sendstudio lists and subscribers apis
require_once(realpath(dirname(__FILE__) . '/../../../') . '/sendstudio/admin/functions/api/lists.php');
require_once(realpath(dirname(__FILE__) . '/../../../') . '/sendstudio/admin/functions/api/subscribers.php');

class MailingLists
{
  /**
   * @const The name of the saveyourbeans mailing lists
   */
  const SYB = 'saveyourbeans.com';
  
  /**
   * @static The ID of the studentbeans.com mailing list in send studio.
   */
  private static $ssListID;

  /**
   * @static The ID of the studentbeans.com mailing list on Mail Chimp.
   */
  private static $mcListID;

  /**
   * @static A reference to the Mail Chimp object
   */
  private static $mc;

  /**
   * @static A reference to the Send Studio lists API object
   */
  private static $ssLists;

  /**
   * @static A reference to the Send Studio subscribers API object
   */
  private static $ssSubscribers;

  /**
   * @static The mail chimp user name
   */
  private static $mcUsername;

  /**
   * @static The mail chimp password
   */
  private static $mcPassword;

  /**
   * Initialises the class
   * @param string $mcUsername Our mail chimp user name
   * @param string $mcPassword Our mail chimp password
   */
  public static function init($mcUsername, $mcPassword)
  {
    self::$mcUsername = $mcUsername;
    self::$mcPassword = $mcPassword;
    
    date_default_timezone_set('UTC');
  }

  /**
   * Returns a boolean describing whether the class has been initialised
   * @return boolean
   */
  private static function isInitialised()
  {
    return (strlen(self::$mcUsername) > 0 && strlen(self::$mcPassword) > 0);
  }

  /**
   * Adds the given email address to the named mailing list on both sendstudio
   * and mail chimp
   *
   * @param string $name The name of the list to synchronise
   * @param string $email The email address to add
   * @param bool $autoConfirm If true, the user will not have to confirm their email address
   */
  public static function addToMailingList($name, $email, $autoConfirm)
  {
    $email = trim($email);

    // validate the email address
    $normalEmail = "/^[a-z0-9_\+-]+(\.[a-z0-9_\+-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*\.([a-z]{2,4})$/";
    $validButRareEmail = "/^[a-z0-9,!#\$%&'\*\+=\?\^_`\{\|}~-]+(\.[a-z0-9,!#\$%&'\*\+=\?\^_`\{\|}~-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*\.([a-z]{2,})$/";

    if (!preg_match($normalEmail, $email) && !preg_match($validButRareEmail, $email))
    {
      return false;
    }

    // valid email, so subscribe to the lists

    // initialise mail chimp and send studio
    self::getListIDs($name);

    // make sure the subscriber isn't already on the sendstudio list
    if (self::$ssSubscribers->IsDuplicate($email, self::$ssListID))
    {
      return false;
    }

    date_default_timezone_set('UTC');
    
    self::$ssSubscribers->Set('requestip', $_SERVER['REMOTE_ADDR']);
    self::$ssSubscribers->Set('subscribedate', date('U'));
    self::$ssSubscribers->Set('requestdate', date('U'));

    if ($autoConfirm)
    {
      self::$ssSubscribers->Set('confirmdate', date('U'));
      self::$ssSubscribers->Set('confirmip', $_SERVER['REMOTE_ADDR']);
    }
    else
    {
      self::$ssSubscribers->Set('confirmdate', 0);
      self::$ssSubscribers->Set('confirmip', '');
    }

    // subscribe to send studio
    $newSubscriberID = self::$ssSubscribers->AddToList($email, self::$ssListID);
    self::$ssSubscribers->ChangeSubscriberFormat('html', $newSubscriberID);

    if ($autoConfirm)
    {
      self::$ssSubscribers->ListConfirm(self::$ssListID, $newSubscriberID);

      // now add the user to the mail chimp list
      if (!self::$mc->listSubscribe(self::$mcListID, $email, array('INTERESTS' => ''), 'html', false))
      {
        throw new RuntimeException("Error subscribing email $email to Mail Chimp list " . 
                self::$mcListID . " ($name). Error message was: " . self::$mc->errorMessage);
      }
    }

    return true;
  }

  /**
   * Retrieves the send studio ID and confirm code of the given email address, or
   * an empty array if the email address isn't on the specified list.
   * @param $mailingList The name of the mailing list to look for subscribers on
   * @param $email The email address to search for
   * @return string The Send studio confirmation code for the specified email address
   */
  public static function getConfirmCode($mailingList, $email)
  {
    if (!is_object(self::$ssSubscribers))
    {
    		self::$ssSubscribers = new Subscribers_API();
    }
     
    if (!is_object(self::$ssSubscribers))
    {
    		throw new RuntimeException("self::\$ssSubscribers cannot be initialised.");
    }
     
    $code = 'none';

    if (self::$ssSubscribers->IsSubscriberOnList($email, array(self::$ssListID)))
    {
      $code = self::$ssSubscribers->GenerateConfirmCode();
    }

    return $code;
  }

  /**
   * Checks a send studio member's email address and confirmation code. If they match,
   * the subscriber's account is activated.
   * @param $name The name of the mailing list to confirm a user on
   * @param $email
   * @param $code
   * @return boolean True on success
   */
  public static function confirmSSSubscriber($name, $email, $code)
  {
    self::getListIDs($name);

    if (!is_object(self::$ssSubscribers))
    {
    		throw new RuntimeException('self::$ssSubscribers has not been initialised.');
    }

    self::$ssSubscribers->Set('confirmip', $_SERVER['REMOTE_ADDR']);

    if ($ids = self::$ssSubscribers->GetSubscriberIdsToConfirm($email, $code))
    {
      // confirmation successful
      foreach ($ids as $k => $id)
      {
        if (self::$ssSubscribers->IsSubscriberOnList($email, array(self::$ssListID)))
        {
          self::$ssSubscribers->ListConfirm(self::$ssListID, $id);
          return true;
        }
      }
    }

    // check failed
    return false;
  }

  /**
   * Initialises the Mail Chimp and SendStudio APIs.
   */
  private static function initialiseAPIs()
  {
    if (!self::isInitialised())
    {
      throw new RuntimeException("Can't initialise APIs - MailingLists hasn't been initialised.");
    }

    if (!is_object(self::$ssLists))
    {
      self::$ssLists = new Lists_API();
    }

    if (!is_object(self::$ssSubscribers))
    {
      self::$ssSubscribers = new Subscribers_API();
    }

    if (!is_object(self::$mc))
    {
      self::$mc = new MCAPI(self::$mcUsername, self::$mcPassword);
      if (self::$mc->errorCode != '')
      {
        throw new RunTimeException("Error logging in to Mail Chimp. Aborting.");
      }
    }

    // if initialisation fails, throw an exception
    if (!is_object(self::$ssLists) || !is_object(self::$ssSubscribers) || !is_object(self::$mc))
    {
      throw new RunTimeException("Unable to initialise APIs. Aborting.");
    }

    // ping the mail chimp server to make sure everything is ok with them
    if (strcmp(self::$mc->ping(), "Everything's Chimpy!") !== 0)
    {
      throw new RunTimeException("Mail Chimp ping returned an error. Aborting.");
    }
  }

  /**
   * Sets a private static variable to the list ID of the studentbeans.com
   * Mail Chimp mailing list.
   *
   * @param string $name The name of the list to find. Must be the same for
   * both Mail Chimp and Send Studio
   */
  private static function getListIDs($name)
  {
    // if the list IDs are already set, just return them
    if (self::$ssListID && self::$mcListID)
    {
      return;
    }

    // otherwise try to get the list IDs
    self::initialiseAPIs();

    $name = trim($name);

    // try to get the send studio list id
    self::$ssListID = self::$ssLists->Find($name);

    // now the mail chimp one
    $mcLists = self::$mc->lists();
    foreach ($mcLists as $list)
    {
      if (strcasecmp(trim($list['name']), $name) === 0)
      self::$mcListID = $list['id'];
    }

    // if we couldn't get one or both of the IDs, throw an exception
    if (empty(self::$ssListID) || empty(self::$mcListID))
    {
      throw new Exception("Unable to retrieve one or both list IDs.");
    }
  }
}
