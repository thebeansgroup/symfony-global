<?php

/**
 * This class contains utility methods specific to Symfony that can be
 * used across all symfony projects.
 */
class SymfonyUtils
{

  /**
   * Sets a unique slug if necessary for the given object
   *
   * @param object $object An object to set a slug for. It must have a 'setSlug' method (it'd
   * be too much work to create an inteface since generated classes would probably need to implement
   * it)
   * @param string $getter The name of the getter to use to create the slug (optional)
   * @param string $string A specific string to slugify instead of using the getter
   */
  public static function setUniqueSlug($object, $getter='__toString', $string='')
  {
    $slugifiedString = ($string) ? StringUtils::slugify($string) : StringUtils::slugify(call_user_func(array($object, $getter)));

    // set the slug if the current slug doesn't begin with $slugifiedString
    if (strpos($object->getSlug(), $slugifiedString) !== 0)
    {
      $object->setSlug(self::generateUniqueSlug($object, $slugifiedString));
    }
  }

  /**
   * Generates a unique slug for an object. This method will
   * return the given slug with a number appended to it so. Numbering starts at 2, e.g.
   * my-slug, my-slug-2, my-slug-3, etc.
   *
   * @param object $object The object to set a unique slug for
   * @param string $slug The base slug to append a number to if necessary
   * @return string A unique slug to use
   */
  public static function generateUniqueSlug($object, $slug)
  {
    $peerClass = get_class($object) . 'Peer';

    // if the class doesn't exist, look for a parent peer class in case we are dealing with a
    // subclass
    if (!class_exists($peerClass))
    {
      $peerClass = get_parent_class($object) . 'Peer';

      if (!class_exists($peerClass))
      {
        throw new RuntimeException("No peer class exists for " . get_class($object) . ' or it\'s parent class');
      }
    }

    // retrieve the slug with the highest index that the given object's slug collided with
    $c = new Criteria();
    $c->addSelectColumn('*');
    $c->add(constant($peerClass . '::' . 'SLUG'), constant($peerClass . '::' . 'SLUG') . " REGEXP '^$slug(-[0-9]{2})?$'", Criteria::CUSTOM);
    $c->addDescendingOrderByColumn(constant($peerClass . '::' . 'SLUG'));

    if (!$existingObject = call_user_func(array($peerClass, 'doSelectOne'), $c))
    {
      // Criteria didn't to return an object, so just return the slug
      return $slug;
    }

    // get the end index of the slug
    $index = preg_replace("!$slug-?!", '', $existingObject->getSlug());

    $index = (empty($index)) ? 2 : $index + 1;

    return $slug . '-' . sprintf('%02d', $index);
  }

  /**
   * Sends an authenticated email
   *
   * @param $toAddress Recipient's email address
   * @param $toName Recipient's name
   * @param $subject
   * @param $bodyText The text body
   * @param $bodyHTML If set, an HTML email will be sent
   */
  public static function sendMail($toAddress, $toName, $subject, $bodyText, $bodyHTML='')
  {
    $sendToCatchAll = sfConfig::get('app_emails_to_catchall');

    if ($sendToCatchAll)
    {
      $toAddress = sfConfig::get('app_emails_catchall_address');
    }

    // get smtp details
    $host = sfConfig::get('app_smtp_host');
    $port = sfConfig::get('app_smtp_port');
    $username = sfConfig::get('app_smtp_username');
    $password = sfConfig::get('app_smtp_password');
    $fromAddress = sfConfig::get('app_smtp_from');
    $fromName = sfConfig::get('app_smtp_from_name');

    if (class_exists('SbSentEmail'))
    {
        // log the mail to the db
        $sentMail = new SbSentEmail();
        $sentMail->setTimeSent(new DateTime());
        $sentMail->setSentFrom($_SERVER['REQUEST_URI']);
        $sentMail->setToName($toName);
        $sentMail->setToEmail($toAddress);
        $sentMail->setSubject($subject);
        $sentMail->setBody($bodyText);
        $sentMail->save();
    }

    return Mailer::send($host, $port, $username, $password, $fromAddress, $fromName,
            $toAddress, $toName, $subject, $bodyText, $bodyHTML);
  }

  /**
   * Creates a ticket in FogBugz
   *
   * @param string $ticketSubject
   * @param string $ticketContent
   */
  public static function createTicket($ticketSubject, $ticketContent = '')
  {
    $fogBugzMailBox = sfConfig::get('app_fogBugzMailBox_email_address');
    self::sendMail($fogBugzMailBox, "FogBugz", $ticketSubject, $ticketContent);
  }

  /**
   * Sends an email to developers
   *
   * @param $subject
   * @param $bodyText
   */
  public static function emailDevelopers($subject, $bodyText)
  {
    return self::sendMail(sfConfig::get('app_developers_email'), sfConfig::get('app_developers_name'), $subject, $bodyText);
  }

  /**
   * Allows us to add users to a mailing list on both sendstudio and mail chimp
   *
   * @param $name
   * @param $email
   * @param $autoConfirm
   * @return unknown_type
   */
  public static function addToMailingList($name, $email, $autoConfirm)
  {
    // initialise the mailing lists class with the mail chimp details
    MailingLists::init(sfConfig::get('app_mailchimp_username'),
                    sfConfig::get('app_mailchimp_password'));

    return MailingLists::addToMailingList($name, $email, $autoConfirm);
  }

  /**
   * Lets us mark as confirmed a user who confirms their email address for a
   * mailing list
   *
   * @param $name
   * @param $email
   * @param $code A confirmation code/token
   * @return unknown_type
   */
  public static function confirmMailingListSubscriber($name, $email, $code)
  {
    // initialise the mailing lists class with the mail chimp details
    MailingLists::init(sfConfig::get('app_mailchimp_username'),
                    sfConfig::get('app_mailchimp_password'));

    return MailingLists::confirmSSSubscriber($name, $email, $code);
  }

  /**
   * This function returns the link to a route
   *
   * @param $context context from which to create the config from
   * @param $routingText this contains the text to be routed
   * @param $object if we are generating an object route we need to pass the object
   * @param boolean $absolute - whether to generate an absolute path
   * @param String $host - This is usefull when you want to influence the domain name ie. when creating a route
   * in a task
   *
   * @return string
   */
  public static function getUrlFromContext($routingText, $params = null, $application = null, $debug = false,
                                           $absolute = false, $host = null)
  {
    $currentApplication = sfConfig::get('sf_app');
    $currentEnvironment = sfConfig::get('sf_environment');
    $context = sfContext::getInstance();
    $routing = $context->getRouting();

    // we need to save the current state of the routing object as we need to restore it later
    $origRoutingOptions = $routing->getOptions();
    $origEventDispatcher = sfContext::getInstance()->getEventDispatcher();

    $switchedContext = false;
    if (!is_null($application) && $context->getConfiguration()->getApplication() != $application)
    {
      $configuration = ProjectConfiguration::getApplicationConfiguration($application, $currentEnvironment,
                      $debug);
      $routing = sfContext::createInstance($configuration)->getRouting();
      // lets import the routes for this application
      $config = new sfRoutingConfigHandler();
      $routes = $config->evaluate(array(sfConfig::get('sf_apps_dir') . "/$application/config/routing.yml"));
      $routing->setRoutes($routes);
      // lets set all the options for this application
      $routingOptions = $routing->getOptions();
      // lets make sure we have the correct controller
      $routingOptions['context']['prefix'] = self::generatePrefix($routingOptions['context']['host'],
                      (is_null($application) ? $currentApplication : $application), $currentEnvironment);

      $routing->initialize(sfContext::getInstance()->getEventDispatcher(), $routing->getCache(), $routingOptions);
      $switchedContext = true;
    }
    // this part is making sure that if we have provided a hostname it gets substituted with the current one
    if (!is_null($host))
    {
      $routingOptions = $routing->getOptions();
      $routingOptions['context']['host'] = $host;
      $routingOptions['context']['prefix'] = self::generatePrefix($host, (is_null($application) ?
                              $currentApplication : $application), $currentEnvironment);
      // re-initialising the routing object minus cache which causes same class object routes to be replicated
      $routing->initialize(sfContext::getInstance()->getEventDispatcher(), null, $routingOptions);
    }

    if (is_object($params))
    {
      $route = $routing->generate($routingText, $params, $absolute);
    }
    else
    {
      $route = $routing->generate($routingText, $params, $absolute);
    }

    // restoring the old routing object
    $routing->initialize($origEventDispatcher, null, $origRoutingOptions);

    if ($switchedContext)
    {
      sfContext::switchTo($currentApplication);
    }

    return $route;
  }

  /**
   * This function gets the required prefix/controller for the route
   *
   * @param String $host
   * @param String $application
   * @param String $environment
   * @return String
   */
  private static function generatePrefix($host, $application, $environment)
  {
    $application = strcasecmp($application, 'backend') == 0 ? 'beanteam' : $application;
    $context = null;
    $leadingSlash = (strpos(strrev($host), "/", 0) === false ? '/' : '');

    // the fontend controller is index.php on prod thus its implicit
    if (strcasecmp($environment, "prod") == 0 && strcasecmp($application, 'frontend') == 0)
    {
      $context = "";
    }
    else if (strcasecmp($environment, "prod") == 0)
    {
      $context = "{$leadingSlash}{$application}.php";
    }
    else
    {
      $context = "{$leadingSlash}{$application}_{$environment}.php";
    }

    return $context;
  }

}
