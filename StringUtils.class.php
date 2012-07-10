<?php
/**
 * This class contains utility methods that can be used for any general code
 */
class StringUtils
{
  /**
   * Returns the text with all non-alphanumeric characters replaced with a dash
   *
   * @param string $separator A separator to use while slugifying text
   * @return string
   */
  public static function slugify($text, $separator='-')
  {
    // replace the string &amp;? with the word 'and'
    $text = preg_replace('!&amp;?!', 'and', $text);

    // replace all non-alphanumeric characters with a separator
    $text = preg_replace('/\W+/', $separator, trim($text));

    // trim and convert to lowercase
    $text = (strlen(str_replace('-', '', $text)) != 0) ? strtolower($text) : 'n' . $separator . 'a';

    return $text;
  }

  /**
   * Compares two strings to see whether they would be the same if appended
   * numbers were stripped off. This allows us to determine, for instance, whether a
   * file name has been SEOed already.
   *
   * @param $str1 The first string
   * @param $str2 The second string
   * @param $separator A string that separates numbers from the end of the file names.
   * This will also be stripped off.
   * @return bool True if the files have the same name once trailing numbers have been
   * removed
   */
  public static function stringsSameWithoutTrailingNumbers($str1, $str2, $separator='-')
  {
    $str1 = preg_replace("/{$separator}[0-9]+$/", '', $str1);
    $str2 = preg_replace("/{$separator}[0-9]+$/", '', $str2);

    return (strcmp($str1, $str2) === 0);
  }

  /**
   * Creates a random string of the specified length
   *
   * @param int $length The length of the string to generate
   * @return string
   */
  public static function randomString($length=10)
  {
    if ($length != intval($length) || $length < 0)
    {
      throw new InvalidParameterException("$length is not a valid number or parameter");
    }

    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $string = '';

    for ($i=0; $i<$length; $i++)
    {
      $string .= $characters[mt_rand(0, strlen($characters)-1)];
    }

    return $string;
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
    if ($filler == '&hellip;')
    {
      return (strlen($text) > $maxlength) ? substr($text, 0, $maxlength - 3).$filler : $text;
    }
    else
    {
      return (strlen($text) > $maxlength) ? substr($text, 0, $maxlength - strlen($filler)).$filler : $text;
    }
  }

  /*
   * Returns the input truncated to the latest fullstop in the string within $maxlenght.
   * If there isn't a fullstop within $maxlenght, the getTeaser method is used.
   *
   * @param string $text - the text to adjust
   * @param int - the maximum number of characters
   * @param string $filler - three dots by default
   * @return string - the adjusted text
  */
  public static function getTeaserToFullstop($text, $maxlength, $filler = '...')
  {
    $teaser = self::getTeaser($text, $maxlength, '');
    $lastFullStopPosition = strripos($teaser, '.');
    if ($lastFullStopPosition !== FALSE)
    {
      return substr($text, 0, $lastFullStopPosition+1);
    }
    else
    {
      return self::getTeaser($text, $maxlength, '...');
    }
  }

}