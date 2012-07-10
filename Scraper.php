<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Scraper
 *
 * @author vincent
 */
abstract class Scraper
{
  private $headers = array();
  private $result;
  private $error;
  private $url;
  private $data;

  /**
   *
   * @param <type> $url 
   */
  public function __construct($url)
  {
    $this->url = $url;
    $this->init();
  }

  /**
   * 
   */
  private function init()
  {
    $this->fetch($this->getUrl());
    $this->setData($this->removeNewlines($this->getResult()));
  }

  /**
   *
   * @param <type> $header 
   */
  public function setHeader($header)
  {
    $this->headers[] = $header;
  }

  /**
   *
   * @param <type> $url
   * @param <type> $data 
   */
  public function fetch($url, $data='')
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)");
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookie.txt');
    curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookie.txt');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    if (is_array($data) && count($data) > 0)
    {
      curl_setopt($ch, CURLOPT_POST, true);
      $params = http_build_query($data);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    }

    if (is_array($this->headers) && count($this->headers) > 0)
    {
      curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
    }

    $this->result = curl_exec($ch);
    $this->error = curl_error($ch);
    curl_close($ch);
  }

  /**
   *
   * @param <type> $needle
   * @param <type> $include
   * @return <type> 
   */
  public function fetchBefore($needle, $include=false)
  {
    $included = strpos($this->getData(), $needle) + strlen($needle);
    $excluded = strpos($this->getData(), $needle);

    if ($included === false || $excluded === false)
    {
      return null;
    }

    $length = ($include == true) ? $included : $excluded;
    $substring = substr($this->getData(), 0, $length);

    return trim($substring);
  }

  /**
   *
   * @param <type> $needle
   * @param <type> $include
   * @return <type> 
   */
  public function fetchAfter($needle, $include=false)
  {
    $included = strpos($this->getData(), $needle);
    $excluded = strpos($this->getData(), $needle) + strlen($needle);

    if ($included === false || $excluded === false)
    {
      return null;
    }

    $position = ($include == true) ? $included : $excluded;
    $substring = substr($this->getData(), $position, strlen($this->getData()) - $position);

    return trim($substring);
  }

  /**
   *
   * @param <type> $needle1
   * @param <type> $needle2
   * @param <type> $include
   * @return <type> 
   */
  public function fetchBetween($needle1, $needle2, $include=false)
  {
    $position = strpos($this->getData(), $needle1);
    
    if ($position === false)
    {
      return null;
    }

    if ($include == false)
    {
      $position += strlen($needle1);
    }

    $position2 = strpos($this->getData(), $needle2, $position);

    if ($position2 === false)
    {
      return null;
    }

    if ($include == true)
    {
      $position2 += strlen($needle2);
    }

    $length = $position2 - $position;
    $substring = substr($this->getData(), $position, $length);

    return trim($substring);
  }

  /**
   *
   * @param <type> $needle
   * @return <type> 
   */
  public function contains($needle)
  {
    $position = strpos($this->getData(), $needle);

    return (is_numeric($position)) ? true : false;
  }

  /**
   *
   * @param <type> $needle1
   * @param <type> $needle2
   * @param <type> $include
   * @return array 
   */
  public function fetchAllBetween($needle1, $needle2, $include=false)
  {
    $matches = array();
    $exp = "|{$needle1}(.*){$needle2}|U";
    preg_match_all($exp, $this->getData(), $matches);
    $i = ($include == true) ? 0 : 1;

    return $matches[$i];
  }

  /**
   *
   * @param <type> $input
   * @return <type> 
   */
  public function removeNewlines($input)
  {
    return str_replace(array("\t", "\n", "\r", "\x20\x20", "\0", "\x0B"), "", html_entity_decode($input));
  }

  /**
   *
   * @param <type> $input
   * @param <type> $allowed
   * @return <type> 
   */
  public function removeTags($input, $allowed='')
  {
    return strip_tags($input, $allowed);
  }

  /**
   * Getter for the header array
   *
   * @return array $headers
   */
  public function getHeaders()
  {
    return $this->headers;
  }

  /**
   *
   * @return <type>
   */
  public function getResult()
  {
    return $this->result;
  }

  /**
   *
   * @return <type> 
   */
  public function getError()
  {
    return $this->error;
  }

  /**
   *
   * @return String
   */
  public function getUrl()
  {
    return $this->url;
  }

  /**
   *
   * @param String $url
   */
  public function setUrl($url)
  {
    $this->url = $url;
  }

  /**
   *
   * @return <type> 
   */
  public function getData()
  {
    return $this->data;
  }

  /**
   *
   * @param <type> $data 
   */
  protected function setData($data)
  {
    $this->data = $data;
  }

}