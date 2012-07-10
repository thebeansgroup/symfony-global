<?php
/**
 * This class allows for easy sending of authenticated emails
 *
 * @package global
 */

class Mailer
{
  /**
   * Sends an authenticated email
   * @param string $host SMTP host name
   * @param int $port SMTP port
   * @param string $username SMTP user name
   * @param string $password SMTP password
   * @param string $fromAddress From address
   * @param string $fromName From name
   * @param string $toAddress Recipients email address
   * @param string $toName Recipients name
   * @param string $subject
   * @param string $body The text body
   * @param string $bodyHTML If set, an HTML email will be sent
   */
  public static function send($host, $port, $username, $password, $fromAddress, 
                              $fromName, $toAddress, $toName, $subject, $bodyText, $bodyHTML='')
  {
    $params = func_get_args();
    Utils::checkParameterTypes(array('string', 'int', 'string', 'string', 'string', 
        'string', 'string', 'string', 'string', 'string', 'string'), $params);

    // Create a Swift SMTP Transport
    $transport = Swift_SmtpTransport::newInstance($host, $port)
                  ->setUsername($username)
                  ->setPassword($password);
                  
    $mailer = Swift_Mailer::newInstance($transport);
    
    // Create a message
    $message = Swift_Message::newInstance($subject)->setFrom(array($fromAddress => $fromName));
    
    if (strlen(trim($toName)) > 0)
    {
      $message->setTo(array($toAddress => $toName));
    }
    else
    {
      $message->setTo(array($toAddress));
    }                
    
    // if we're sending an HTML email, set it up
    if (!empty($bodyHTML))
    {
      $message->setBody($bodyHTML, 'text/html');
      $message->addPart($bodyText);
    }
    else
    {
      $message->setBody($bodyText);
    }

    // Send the message
    if(!$mailer->send($message))
    {
      throw new RunTimeException("Could not send email to $toAddress.");
    }
  }
}
