<?php
/**
 * This class contains utility methods for dealing with date and timestamp, e.g.
 * fotmat date, etc.
 */

// we need to make sure the class isn't already defined, or PHP will complain

class DateUtils
{
  const END = 'end';
  const START = 'start';
  const SECONDS_IN_DAY = 86400;

  /**
   * create the time stamp of a date in a standard format yyyy-mm-dd HH:MM:SS
   *
   * @return int the time stamp we are looking for!
   */
  public static function getTimeStamp($time)
  {
    $dateArr = date_parse($time);
    $timestamp = mktime($dateArr['hour'], $dateArr['minute'], $dateArr['second'], $dateArr['month'], $dateArr['day'], $dateArr['year']);

    return $timestamp;
  }

 /**
   * Return the formatted json type time variable so that the javascript counter could use it 
   *
   * @param $t string of the time we want to format so that the contdown works
   * @param string that we want to have dispalyed before the count down time on the javascript output
   * @return interger being the (time() -  publishing timestamp) formatted in general
   */
  public static function getCounterFromTimestamp($t,$title=' ', $options=null)
  {
    if ($t > 0) {
      $hours = floor($t / 3600);
      $minutes = floor( ($t / 3600 - $hours) * 60);
      $seconds = round( ( ( ($t / 3600 - $hours) * 60) - $minutes) * 60);
    } else {
      $hours = 0;
      $minutes = 0;
      $seconds = 0;
    }
    $strOptions ='';
    if ($options && is_array($options))
    {
      foreach($options as $key => $value)
      {
        $strOptions .= $key. ":'" . $value . "',";  
        
      }
       $strOptions =  substr($strOptions,0 ,strlen($strOptions)-1 );
      return "{hour:'$hours',min:'$minutes',sec:'$seconds',title:'$title', $strOptions}";
    }
    else
    {
      return "{hour:'$hours',min:'$minutes',sec:'$seconds',title:'$title'}";
    }
    
  }
  
  /**
   * Returns an array of years before or after the current year.
   *
   * @param int $number The number of years to return either before or after the current
   * year. To indicate years before the current year, use a negative number.
   * @param int $startFrom The first year to begin calculations from. Defaults to the current year.
   * @param bool $ascending If true, the array will be returned in ascending order, if false
   * the order will be descending
   *
   * @return array An array of years
   */
  public static function getYearArray($number, $startFrom=0, $ascending=true)
  {
    $years = array();

    $startFrom = ($startFrom) ? $startFrom : date('Y');

    // get future years
    if ($number > 0)
    {
      for ($i=0; $i<$number; $i++)
      {
        $year = $startFrom + $i;
        $years[$year] = $year;
      }
    }
    else   // get previous years
    {
      for ($i=$startFrom; $i>($startFrom + $number); $i--)
      {
        $years[$i] = $i;
      }
    }

    // reverse keys if necessary
    if (!$ascending)
    {
      $years = array_reverse($years, true);
    }

    return $years;
  }

  /**
   * return an array array('dayago' => 'day ago') for history
   *
   * @param int $startTimestamp
   * @return unknown
   */
  public static function getDayDifference($startTimestamp)
  {
    $dayDiff= array();
    $startDate = date('d-m-Y', $startTimestamp);
    $todayDate = date('d-m-Y', time());
    $yesterdayDate = date('d-m-Y', time()-86400);
    $dayDiff['day'] = "";
    $dayDiff['dayLink'] = "";
    if ($startDate == $todayDate)
    {
      $dayDiff['day'] = "today";
      $dayDiff['dayLink'] = "today";
    }
    else if ($startDate == $yesterdayDate)
    {
      $dayDiff['day'] = "yesterday";
      $dayDiff['dayLink'] = "yesterday";
    }
    else // the item was uploaded more than 1 day ago
    {
      $timeStampDifference = time() - $startTimestamp;
      $numberOfDays = floor($timeStampDifference/86400);
      $dayDiff['day'] = "$numberOfDays days ago";
      $dayDiff['dayLink'] = "days". $numberOfDays. "ago";
    }
    return $dayDiff;
  }

  /**
   * Returns the interval between the start date and end date as an integer
   * representing the number of days different
   *
   * @param string $startDate
   * @param string $endDate
   *
   * @return integer
   */
  public static function getInterval($startDate, $endDate)
  {
    $startDate = new DateTime($startDate);
    $endDate = new DateTime($endDate);

    $interval = $startDate->diff($endDate);

    return $interval->format('%r%a');
  }

  /**
   * Returns a timestamp that corresponds to the start of the latest period of the day
   * that has already passed, where a period is calculated as 24/$periods.
   *
   * e.g. If the current time is 9am 1/1/10, and periods=4, this will return a timestamp that
   * corresponds to 6am 1/1/10
   *
   * @param int $periods The number of periods to split the day into
   * @param int $timestamp Timestamp to calculate the period for
   *
   * @return int timestamp
   */
  public static function getDatePeriod($startOrEnd=DateUtils::END, $periods=24, $timestamp=null)
  {
      if ($timestamp == null)
      {
          $timestamp = time();
      }

      if (!is_integer($timestamp))
      {
          throw new InvalidArgumentException("timestamp parameter must be an integer or null");
      }

      // get the date component for the timestamp
      $date = strftime('%F', $timestamp);

      // convert the date component back to a timestamp
      $dateTime = strtotime($date);

      // find the time in milliseconds
      $time = $timestamp - $dateTime;

      // now compute the previous time period

      if ($startOrEnd == DateUtils::START)
      {
        $periodTime = floor($time / floor(DateUtils::SECONDS_IN_DAY / $periods)) * floor(DateUtils::SECONDS_IN_DAY / $periods);
      }
      else if ($startOrEnd == DateUtils::END)
      {
        $periodTime = ceil($time / ceil(DateUtils::SECONDS_IN_DAY / $periods)) * ceil(DateUtils::SECONDS_IN_DAY / $periods);
      }
      else
      {
        throw new InvalidArgumentException("Invalid value of startOrEnd parameter");
      }

      // add the date component back on
      $previousPeriodTimestamp = $periodTime + $dateTime;

      return $previousPeriodTimestamp;
  }
}

//$t = '9:00am';
//print $t . "\n";
//print strftime('%F %T', DateUtils::getDatePeriod(DateUtils::START, 24, strtotime($t))) . "\n";
//print strftime('%F %T', DateUtils::getDatePeriod(DateUtils::END, 24, strtotime($t))) . "\n";