<?php
/**
 * This class contains utility methods that can be used for any general code
 * 
 * ALL OF THESE METHODS ARE DEPRECATED (20/7/09). PLEASE COPY THEM INTO OTHER, MORE SPECIFIC
 * UTILITY CLASSES. THEN WE'LL GO THROUGH AND UPDATE EXISTING PROJECTS THAT USE THESE
 * METHODS.
 */
class Utils
{
  /**
   * Compares the types given in the first array with the values passed
   * as the second array. This can be used by methods to confirm that
   * they have been given the parameters that they expect.
   *
   * @param array $types The expected types of parameters. PHP functions called "is_$type" and "{$type}val"
   * must exist for any values given. Valid key values are: 'array', 'binary', 'bool', 'callable', 'double',
   * 'float', 'int', 'long', 'object', 'real', 'resource', 'string'. Multiple types can be separated with
   * a '/' character, so for instance, a parameter can be tested to be 'int/null'.
   * @param array &$values The parameters to test the types of
   * @return bool
   *
   * @throws InvalidArgumentException on behalf of the calling method so it doesn't have to.
   * @throws UnexpectedValueException When supplied with an invalid value in the $types array
   */
  public static function checkParameterTypes(array $types, array &$values)
  {
    $numericTypes = array('int', 'float', 'double', 'integer');
    $invalidArgument = false;

    // test the type of each value - this allows us to test methods that take default
    // parameters because func_get_args doesn't include default values
    // (so count($values) could < count($types))
    for ($i=0; $i<count($values); $i++)
    {
      // see if we need to check one of multiple types
      $typeFunctions = explode('/', $types[$i]);
       
      // this records whether a test against multiple types matched
      // on one of them
      $validParameter = false;
       
      foreach ($typeFunctions as $function)
      {
        $isFunction = 'is_' . $function;
        $valFunction = $function . 'val';

        if (!function_exists($isFunction))
        {
          throw new UnexpectedValueException('Invalid type given - no such PHP function ' . $isFunction);
        }

        // we need to treat numeric values differently since they may be 'string' types - so
        // we need to convert them to their numeric types they call is_int, is_double, etc.
        if (in_array($function, $numericTypes))
        {
          // convert the value to the numeric equivalent
          $number = $valFunction($values[$i]);
           
          // strip all leading zeros from the number/string, unless the parameter
          // is zero to begin with
          $paramNumber = ($valFunction($values[$i]) == 0) ? $values[$i] : ltrim($values[$i], '0');
           
          // make sure both values are the same
          if (strcmp($number, $paramNumber) === 0)
          {
            $validParameter = true;
            break;
          }
        }
         
        // if it's not a numeric type, make sure the value is of the correct type
        if (!in_array($function, $numericTypes) && $isFunction($values[$i]))
        {
          $validParameter = true;
        }
      }

      // if there was an error, throw an exception
      if (!$validParameter)
      {
        throw new InvalidArgumentException("Parameter " . ($i+1) . " should be of type {$function}. " .
        gettype($values[$i]) . ' given.');
      }
    }

    return true;
  }

  /**
   * Returns the text with all non-alphanumeric characters replaced with a dash
   * 
   * @deprecated DO NOT USE THIS METHOD. USE StringUtils::slugify INSTEAD.
   * @return string
   */
  public static function slugify($text)
  {
    // replace all non-alphanumeric characters with a dash
    $text = preg_replace('/\W+/', '-', trim($text));

    // trim and convert to lowercase
    $text = (strlen(str_replace('-', '', $text)) != 0) ? strtolower($text) : 'n-a';
    
    return $text;
  }

  /**
   * Guarantees a text is not longer than $maxlength chars
   * (if it is, it's shortened and appended three dots to it by default)
   *
   * @param string $text - the text to adjust
   * @param int - the maximum number of characters
   * @param string $filler - three dots by default
   * @return string - the adjusted text
   */
  public static function getTeaser($text, $maxlength, $filler = '...')
  {
    $params = func_get_args();
    Utils::checkParameterTypes(array('string', 'int', 'string'), $params);
    return (strlen($text) > $maxlength) ? substr($text, 0, $maxlength-strlen($filler)).$filler : $text;
  }

  /**
   * @return string - everything that comes after http://www.studentbeans.com/ in the URL
   */
  public static function requestedUrl()
  {
    $url = "";

    if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'])
    {
      $url = substr($_SERVER['REQUEST_URI'], 1);
    }
    else if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'])
    {
      $url = substr($_SERVER['QUERY_STRING'], 2);

      // unfortunately $_SERVER['QUERY_STRING'] replace the ? in the URL with &amp;
      // and all the &'s  with &amp;
      $url = preg_replace('/&amp;/', '?', $url, 1);
      $url = preg_replace('/&amp;/', '&', $url);
    }


    return $url;
  }

  /**
   * Emails the developers with details of an exception that has occurred.
   * @param Exception $e An Exception object
   */
  public static function notifyDevelopersAboutException(Exception $e)
  {
    $subject = $_SERVER['HTTP_HOST'] . ' EXCEPTION: ' . $e->getMessage();
    $body = 'The following exception occurred at ' . strftime('%T on %d/%m/%Y') . " on {$_SERVER['HTTP_HOST']}:\n\n";
    $body .= $e;
    $body .= "\n\nError occurred on " . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
     
    mail('developers@studentbeans.com', $subject, $body);
  }

  /**
   * Returns a rappresentation of the current date&time compatible with the 
   * MySQL timestamp type
   *
   * @return string
   */
  public static function getMySQLTimestamp()
  {
    return date('Y-m-d H:i:s', time());
  }

  /**
   * Returns a rappresentation of the current date compatible with the MySQL date type
   *
   * @return string
   */
  public static function getMySQLDate()
  {
    return date('Y-m-d', time());
  }

  /**
   * @param string $mysqlTimestamp - a MySQL timestamp (e.g.: 2009-05-01 09:39:04)
   * @return string|NULL - the UNIX timestamp corresponding to the input, NULL if the input is not valid
   */
  public static function getUnixTimestampFromMySQLTimestamp($mysqlTimestamp)
  {
    // $unixTimestamp is int the format: 2009-05-01 09:39:04
    preg_match('/^(\d\d\d\d)-(\d\d)-(\d\d) (\d\d):(\d\d):(\d\d)$/', $mysqlTimestamp, $dateTokens);

    if (count($dateTokens))
    {
      return mktime($dateTokens[4], $dateTokens[5], $dateTokens[6], $dateTokens[2], $dateTokens[3], $dateTokens[1]);
    }
    else
    {
      return NULL;
    }
  }

  /**
   * Returns a numerical representation of the date corresponding to the input, 
   * NULL if the input is not valid (e.g.: 20090501 when the input is as shown below)
   *
   * @param string $mysqlTimestamp - a MySQL timestamp (e.g.: 2009-05-01 09:39:04)
   * @return string|NULL
   */
  public static function getNumericDateFromMySQLTimestamp($mysqlTimestamp)
  {
    // $unixTimestamp is int the format: 2009-05-01 09:39:04
    preg_match('/^(\d\d\d\d)-(\d\d)-(\d\d) (\d\d):(\d\d):(\d\d)$/', $mysqlTimestamp, $dateTokens);

    if (count($dateTokens))
    {
      return $dateTokens[4] . $dateTokens[5] . $dateTokens[6];
    }
    else
    {
      return NULL;
    }
  }


  /**
   * Returns the $subject with the words contained in $queryString highlighted
   *
   * @param array $tokens - an array of strings to search the subject for
   * @param string $subject - the string to search for words for hightlight (the string to transform)
   * @param string $preHTML - the HTML to use before the word to highlight
   * @param string $postHTML - the HTML to use after the word to highlight
   * @param bool $caseSensitive - Whether to respect case while highlighting
   * @return string
   */
  public static function highlightText(array $tokens, $subject, $preHTML='<b>', $postHTML='</b>', $caseSensitive=false)
  {
    $subject = str_replace('/', ' ', str_replace('/', ' ', $subject));
    // the following loop is to avoid to highlight sub-parts of a word, i.e.
    // if I search for PR, I don't want to highlight those two letters in the word: program
    foreach ($tokens as $key => $value)
    {
      $tokens[$key] = str_replace('/', ' ', $value) . ' ';
    }

    $search = '(' . implode('|', $tokens) . ')';
    
    $modifier = ($caseSensitive) ? '' : 'i';
    //echo "\"/\b($search)/$modifier\", $preHTML . \"$1\" . $postHTML, $subject";exit;
    $subject = preg_replace("/\b($search)/$modifier", $preHTML . "$1" . $postHTML, $subject);
    
    return $subject;
  }
}