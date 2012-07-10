fn <?php

/**
 * Utilities for working with HTML.
 * 
 * @author al
 *
 */
class HtmlUtils
{

  /**
   * Cleans dirty HTML, e.g. as entered by a user. It uses HtmlPurifier.
   * 
   * @param string $dirtyHtml
   * @return string Cleaned Html
   */
  public static function clean($dirtyHtml)
  {
    $purifier = new HTMLPurifier();
    return $purifier->purify($dirtyHtml);
  }
}