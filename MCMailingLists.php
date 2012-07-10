<?php
/**
 * Class which synchronises our mailing list with mail chimp and vice versa
 */
class MCMailingLists
{
  /**
   * @var The number of users to update to mail chimp in one batch
   */
  const BULK_UPDATE_THRESHOLD = 200;
  
  /**
   * @var object A reference to the MCAPI class
   */
  protected $mc;
  
  /**
   * @var array An array of list names and mail chimp IDs 
   */
  protected $mcListId = array();
  
  /**
   * @var string The name of the mailing list we're currently working with
   */
  protected $currentListName;
  
  /**
   * @var object The local mailing list object in Symfony for a named mailing list
   */
  protected $localList;
  
  /**
   * @var boolean Whether to run in debug mode, in which case no data will be sent
   * to mail chimp
   */
  protected $debugMode;
  
  /**
   * @var array An array of data from users to subscribe or update at mail chimp
   */
  protected $subscribes = array();
  
  /**
   * @var An array of user data to unsubscribe from mail chimp
   */
  protected $unsubscribes = array();
  
  /**
   * Constructor. Initialises the mail chimp API
   * 
   * @throws RunTimeException if there is an error logging in to mail chimp
   */
  public function __construct($username, $password, $debugMode=false)
  {
    $this->mc = new MCAPI($username, $password);

    $this->debugMode = $debugMode;
    
    if ($this->debugMode)
    {
      echo "Running in debug mode. No data will be committed to mail chimp.\n";
    }
    
    if ($this->mc->errorCode != '')
    {
      throw new RunTimeException("Error logging in to Mail Chimp. \nError was: {$this->mc->errorMessage}\n");
    }
    
    // ping the mail chimp server to make sure everything is ok with them
    if (strcmp($this->mc->ping(), "Everything's Chimpy!") !== 0)
    {
      throw new RunTimeException("Mail Chimp ping returned an error. Aborting.");
    }
  }

  /**
   * Returns the mail chimp api connection
   * 
   * @return mixed
   */
  public function fetchMembersOfList($name, $status='subscribed', $since=null, $start=0, $limit=100)
  {
    if ($listId = $this->getListId($name))
    {
      return $this->mc->listMembers($listId, $status, $since, $start, $limit);
    }
    
    return false;
  }
  
  /**
   * Returns data for the member with the given email address
   * 
   * @param string $email
   * @return array
   */
  public function fetchMemberData($email)
  {
    return $this->mc->listMemberInfo($this->getCurrentListId(), $email);
  }
  
  /**
   * Adds subscriber data to an array that will be sent in bulk to mail chimp
   * to be updated
   * 
   * @param array $userData An array of user data prepared for mail chimp
   */
  public function batchSubscribeUser($userData)
  {
    $this->subscribes[] = $userData;
    $this->bulkUpdateMailChimp();
  }
  
  /**
   * Adds unsubscriber data to an array that will be sent in bulk to mail chimp
   * to be updated
   * 
   * @param string $email The email address of a user to unsubscribe
   */
  public function batchUnsubscribeUser($email)
  {
    $this->unsubscribes[] = $email;
    $this->bulkUpdateMailChimp();
  }

  /**
   * Destructor. Flushes updates to mail chimp
   */
  public function __destruct()
  {
    $this->bulkUpdateMailChimp(false);
  }
  
  /**
   * Updates data on mail chimp in a batch
   * 
   * @param boolean $respectThreshold Whether to only update subscribers if there
   * are more than self::BULK_UPDATE_THRESHOLD entries in the arrays 
   */
  protected function bulkUpdateMailChimp($respectThreshold=true)
  {
    try
    {
      if ($respectThreshold && (count($this->subscribes) < self::BULK_UPDATE_THRESHOLD) && (count($this->unsubscribes) < self::BULK_UPDATE_THRESHOLD))
      {
        return;
      }
      
      echo "Updating " . count($this->unsubscribes) . " unsubscribers and " . count($this->subscribes) . 
        " subscribers to the MC list called " . $this->currentListName;
      
      if ($this->debugMode)
      {
        echo " (not really - debug mode is enabled)";
      }
      
      echo "\n";
      
      if (!$this->debugMode && count($this->unsubscribes) > 0)
      {
        if (!$this->mc->listBatchUnsubscribe($this->getCurrentListId(), $this->unsubscribes, false, false, false))
        {
          throw new RunTimeException("Batch unsubscribing to mail chimp failed. Message was: \n" . $this->mc->errorMessage);
        }
      }
      
      $this->unsubscribes = array();
      
      if (!$this->debugMode && count($this->subscribes) > 0)
      {
        if (!$retVal = $this->mc->listBatchSubscribe($this->getCurrentListId(), $this->subscribes, false, true, true))
        {
          throw new RunTimeException("Batch subscribing to mail chimp failed. Message was: \n" . $this->mc->errorMessage);
        }
      }

      $this->subscribes = array();

      // errors were returned. see if we can recover from them
      if (isset($retVal['error_count']) && $retVal['error_count'] > 0)
      {
        $errorHandled = false;

        if (is_array($retVal['errors']))
        {
          $mcRecoverableErrors = array(
            'has unsubscribed, and cannot be resubscribed by you',
            'has bounced, and cannot be resubscribed',
            'Invalid Email Address:'
          );
           
          foreach ($retVal['errors'] as $error)
          {
            // if mail chimp is refusing to add a subscriber back to the after they unsubscribed
            // or bounced, unsubscribe them from send studio
            foreach ($mcRecoverableErrors as $mcErrorMessage)
            {
              if (strpos($error['message'], $mcErrorMessage) !== false)
              {
                if (isset($error['row']['EMAIL']))
                {
                  echo "recovered from an error ({$error['message']}) for email {$error['row']['EMAIL']}\n";
                  //self::unsubscribeFromSendStudio(array($error['row']['EMAIL']));
                  $errorHandled = true;
                }
              }
            }
          }
        }

        // if we couldn't handle the error, throw a new exception.
        if (!$errorHandled)
        {
          throw new RunTimeException("Mail chimp reported an error: " . serialize($retVal['errors']));
        }
      }
    }
    catch (Exception $e)
    {
      echo $e->getMessage();
    }
  }
  
  /**
   * Connects to mail chimp and retrieves the list ID for the given list name
   *
   * @param string $name The name of the list to find in mail chimp. It must
   * correspond to the name of a list in our database
   * @return string
   */
  protected function getListId($name)
  {
    $name = trim($name);
    
    if (isset($this->mcListId[$name]))
    {
      $this->currentListName = $name;
      return $this->mcListId[$name];
    }
    
    $mcLists = $this->mc->lists();

    foreach ($mcLists as $list)
    {
      if (strcasecmp(trim($list['name']), $name) === 0)
      {
        $this->mcListId[$name] = $list['id'];
        $this->currentListName = $name;
      }
    }

    // if we couldn't get one or both of the IDs, throw an exception
    if (!isset($this->mcListId[$name]))
    {
      throw new Exception("Unable to retrieve the mail chimp list IDs for the list called $name.");
    }
    
    return $this->mcListId[$name];
  }

  /**
   * Allows the client to set the list to work with
   * 
   * @param string $name The name of the mailing list to work with
   * @return boolean
   */
  public function setListName($name)
  {
    return (bool)$this->getListId($name);
  }
  
  /**
   * Returns the list id of the current list
   * 
   * @return int
   */
  protected function getCurrentListId()
  {
    if (!isset($this->mcListId[$this->currentListName]))
    {
      throw new RuntimeException("The current list ID is not set");
    }
    
    return $this->mcListId[$this->currentListName];
  }
  
  /**
   * Takes an easy auth student user and creates an array of data to send to 
   * mail chimp 
   * 
   * @param sfEasyAuthUser $student A user with the student credential
   * @return array An array of data prepared for mail chimp
   */
  public function prepareStudentData(sfEasyAuthUser $student)
  {
    if (!$student->hasCredential('student'))
    {
      throw new InvalidArgumentException("User is not a student");
    }

    // if this student doesn't have a profile for some reason, throw an exception
    if (!$student->hasStudentProfile())
    {
      throw new InvalidArgumentException("Student with user id {$student->getId()} doesn't have a profile");
    }
    
    $configuredInterests = array(
      'Travel',
      'Dating',
      'Poker & Gaming',
      'Books & Magazines',
      'Eating Out',
      'Cars',
      'Sports',
      'Theatre/Art',
      'Competitions',
      'Music',
      'Films',
      'Computer Games',
      'Fashion',
      'Clubbing',
      'Careers',
      'Food'
    );
    
    $interests = array();
    
    foreach ($student->getSbUserMarketingQuestionsJoinSbMarketingQuestion() as $interest)
    {
      $interestName = str_replace('&amp;', '&', trim($interest->getSbMarketingQuestion()->getLabel()));
      
      if (in_array($interestName, $configuredInterests))
      {
        $interests[] = $interestName;
      }
    }
    
    $interests = implode(',', $interests);

    $data = array(
      'EMAIL' => $student->getEmail(),
      'FNAME' => $student->getStudentProfile()->getFirstName(),
      'LNAME' => $student->getStudentProfile()->getLastName(),
      'HASH' => $student->getAutoLoginHash(),
      'WUID' => $student->getId(),
      'GENDER' => ($student->getStudentProfile()->getGender() == 'M') ? 'male' : 'female',
      'PRIMLOC' => ($student->getStudentProfile()->getSbRegion()) ? $student->getStudentProfile()->getSbRegion()->getName() : '',
      'GRADDATE' => $student->getStudentProfile()->getCourseEnd('Y'),
      'DOB' => $student->getStudentProfile()->getDob('U'),
      'UNIVERSITY' => ($student->getStudentProfile()->getSbUniversity()) ? $student->getStudentProfile()->getSbUniversity()->getName() : '',
      'COURSE' => ($student->getStudentProfile()->getSbCourse()) ? $student->getStudentProfile()->getSbCourse()->getName() : '',
      'CANRESELL' => intval($student->getStudentProfile()->getCanResellInfo()),
      'CSTART' => $student->getStudentProfile()->getCourseStart('Y'),
      'USERTYPE' => 'student',
      'MILKROUND' => $student->getStudentProfile()->getJoinMilkround(),
      'SNAPFISH' => $student->getStudentProfile()->getJoinSnapfish(),
      'INTERESTS' => $interests,
      'EMAIL_TYPE' => 'html'
    );

    return $data;
  }
  
  /**
   * Deletes a member of the current list by email
   * 
   * @param string $email The email address of the user to delete
   */
  public function deleteMember($email)
  {
    if ($this->debugMode)
    {
      echo "Want to delete email $email from list {$this->getCurrentListId()}\n";
    }
    else
    {    
      return $this->mc->listUnsubscribe($this->getCurrentListId(), $email, true, false, false);
    }
  }
  
  /**
   * Updates the subscriber details for the member with the given email address
   * 
   * @param string $email
   * @param sfEasyAuth $student The student user object to retrieve up-to-date details from
   * @return unknown_type
   */
  public function updateSubscriberDetails($email, $student)
  {
    $data = $this->prepareStudentData($student);
    
    if ($this->debugMode)
    {
      echo "Want to update the details of member with email $email with data: \n";
      print_r($data);
      return;
    }
    else
    {
      return $this->mc->listUpdateMember($this->getCurrentListId(), $email, $data, '', true);
    }
  }

  /**
   * Returns the most recent error message from mail chimp
   *
   * @return string The most recent error message
   */
  public function getErrorMessage()
  {
    return $this->mc->errorMessage;
  }

  /**
   * Returns the most recent error code from mail chimp
   *
   * @return string The most recent error code
   */
  public function getErrorCode()
  {
    return $this->mc->errorCode;
  }
}