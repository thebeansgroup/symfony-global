<?php

/*
 *
 */

/**
 * This class is the Propel implementation of sfPager.  It interacts with the propel record set and
 */
class SbPager extends sfPager
{

  protected
  $criteria = null,
  $peer_method_name = 'doSelect',
  $peer_count_method_name = 'doCount';

  /**
   * Constructor.
   *
   * @see sfPager
   */
  public function __construct($class, $maxPerPage = 10)
  {
    parent::__construct($class, $maxPerPage);

    $this->setCriteria(new Criteria());
    $this->tableName = constant($this->getClassPeer() . '::TABLE_NAME');
  }

  /**
   * @see sfPager
   */
  public function init()
  {
    $this->resetIterator();

    $hasMaxRecordLimit = ($this->getMaxRecordLimit() !== false);
    $maxRecordLimit = $this->getMaxRecordLimit();

    $criteriaForCount = clone $this->getCriteria();
    $criteriaForCount
            ->setOffset(0)
            ->setLimit(0)
            ->clearGroupByColumns()
    ;

    $count = call_user_func(array($this->getClassPeer(), $this->getPeerCountMethod()), $criteriaForCount);

    $this->setNbResults($hasMaxRecordLimit ? min($count, $maxRecordLimit) : $count);

    $criteria = $this->getCriteria()
                    ->setOffset(0)
                    ->setLimit(0)
    ;

    if (0 == $this->getPage() || 0 == $this->getMaxPerPage())
    {
      $this->setLastPage(0);
    }
    else
    {
      $this->setLastPage(ceil($this->getNbResults() / $this->getMaxPerPage()));

      $offset = ($this->getPage() - 1) * $this->getMaxPerPage();
      $criteria->setOffset($offset);

      if ($hasMaxRecordLimit)
      {
        $maxRecordLimit = $maxRecordLimit - $offset;
        if ($maxRecordLimit > $this->getMaxPerPage())
        {
          $criteria->setLimit($this->getMaxPerPage());
        }
        else
        {
          $criteria->setLimit($maxRecordLimit);
        }
      }
      else
      {
        $criteria->setLimit($this->getMaxPerPage());
      }
    }
  }

  /**
   * @see sfPager
   */
  protected function retrieveObject($offset)
  {
    $criteriaForRetrieve = clone $this->getCriteria();
    $criteriaForRetrieve
            ->setOffset($offset - 1)
            ->setLimit(1)
    ;

    $results = call_user_func(array($this->getClassPeer(), $this->getPeerMethod()), $criteriaForRetrieve);

    return is_array($results) && isset($results[0]) ? $results[0] : null;
  }

  /**
   * @see sfPager
   */
  public function getResults()
  {
    return call_user_func(array($this->getClassPeer(), $this->getPeerMethod()), $this->getCriteria());
  }

  /**
   * Returns the peer method name.
   *
   * @return string
   */
  public function getPeerMethod()
  {
    return $this->peer_method_name;
  }

  /**
   * Sets the peer method name.
   *
   * @param string $method A method on the current peer class
   */
  public function setPeerMethod($peer_method_name)
  {
    $this->peer_method_name = $peer_method_name;
  }

  /**
   * Returns the peer count method name.
   *
   * @return string
   */
  public function getPeerCountMethod()
  {
    return $this->peer_count_method_name;
  }

  /**
   * Sets the peer count method name.
   *
   * @param string $peer_count_method_name
   */
  public function setPeerCountMethod($peer_count_method_name)
  {
    $this->peer_count_method_name = $peer_count_method_name;
  }

  /**
   * Returns the name of the current model class' peer class.
   *
   * @return string
   */
  public function getClassPeer()
  {
    return constant($this->class . '::PEER');
  }

  /**
   * Returns the current Criteria.
   *
   * @return Criteria
   */
  public function getCriteria()
  {
    return $this->criteria;
  }

  /**
   * Sets the Criteria for the current pager.
   *
   * @param Criteria $criteria
   */
  public function setCriteria($criteria)
  {
    $this->criteria = $criteria;
  }

  /**
   * Returns an array of page numbers to use in pagination links.
   *
   * @param  integer $nb_links The maximum number of page numbers to return
   *
   * @return array
   */
  public function getLinks($nb_links = 5)
  {
    $links = array();
    $tmp = $this->page - floor($nb_links / 2);
    $check = $this->lastPage - $nb_links + 1;
    $limit = $check > 0 ? $check : 1;
    $begin = $tmp > 0 ? ($tmp > $limit ? $limit : $tmp) : 1;

    $i = (int) $begin;
    while ($i < $begin + $nb_links && $i <= $this->lastPage)
    {
      $links[] = $i++;
    }

    $this->currentMaxLink = count($links) ? $links[count($links) - 1] : 1;

    return $links;
  }

  /**
   * get the first item on the specified page
   * 
   * $page integer number of the page
   * @return unknown_type
   */
  public function getFirstObjectOnPage($page)
  {
    $criteriaFirstOnPage = clone $this->getCriteria();
    $page = intval($page);
    $criteriaFirstOnPage
            ->setOffset($page - 1)
            ->setLimit(1);

    return call_user_func(array($this->getClassPeer(), 'doSelectOne'), $criteriaFirstOnPage);
  }

  /**
   * Get the the sql from the crieria formatted in a way so that it is easy to use in the query to get 
   * the rank of the an object in the set
   * 
   * @return unknown_type
   */
  public function createSqlFromCriteria()
  {

    $criteriaToRank = clone $this->getCriteria();
    $criteriaToRank
            ->setOffset(0)
            ->setLimit(0);

    $params = array();
    $query = BasePeer::createSelectSql($criteriaToRank, $params);

    $paramstr1 = array();
    $paramstr = array();
    foreach ($params as $param)
    {
      $paramstr1[] = $param['table'] . '.' . $param['column'] . ' => ' . var_export($param['value'], true);
      $paramstr[] = (is_string($param['value'])) ? "'" . $param['value'] . "'" : $param['value'];
    }

    $pattern = array();
    for ($i = 1; $i <= count($paramstr); $i++)
    {
      $pattern[] = "/:p$i/";
    }

    return preg_replace($pattern, $paramstr, $query);
  }

  /**
   * 	Get the the rank of the item in the set defined by criteria with that type of query
   *
   *    $query = "
   *    SELECT `rank`, `id`, `slug`
   *       FROM (SELECT @rank:=@rank+1 AS `rank`, `id`, `slug`
   *            FROM `sb_picture`
   *            WHERE `gallery_id`= $galleryId
   *              AND `enabled`=1
   *              AND publish_from <= '".strftime('%F %T')."'
   *              ORDER BY `created_at` ASC
   *           )
   *           AS x
   *       WHERE `slug`='$currentSlug'";
   *  }
   *
   * @param  $picture  SbPicture current picture
   * @return array of objects SbPictures the size of which is 3
   */
  public function getSqlRanks($currentObject=null)
  {
    $coreQuery = $this->createSqlFromCriteria();

    $callingTable = $this->tableName;
    $connection = Propel::getConnection();


    if (is_object($currentObject))
    {
      $first = $currentObject;
    }
    else
    {
      if (!($first = $this->getFirstObjectOnPage(1)))
      {
        return 0;
      }
    }
    $currentId = $first->getId();
 
    if ($callingTable != 'sb_picture')
    {
      $connection->query(' SET @rank :=0;');

      $coreQuery = preg_replace('/SELECT/', 'SELECT @rank:=@rank+1 AS `rank`, ' . $callingTable . '.*', $coreQuery);
      $query = "SELECT `rank`, `id`, `slug` FROM ($coreQuery) as x WHERE `id`= '$currentId'";
    }
    else
    {
      $query = "SELECT `rank`, `picture_id` AS `id`, `slug` FROM sb_picture_ranking WHERE `picture_id` = '$currentId'";
    }

    $statement = $connection->query($query);
    $resultset = $statement->fetch();

    return $resultset['rank'];
  }

  /**
   * Sets the current page.
   *
   * @param integer $page
   */
  public function setPage($currentObject = null)
  {
    $rank = intval($this->getSqlRanks($currentObject));
    $perPage = $this->getMaxPerPage();
    if ($this->getMaxPerPage())
    {
      if ($rank % $this->getMaxPerPage() == 0)
      {
        $this->page = intval($rank / $perPage);
      }
      else
      {
        $this->page = floor($rank / $perPage) + 1;
      }
      $this->page = intval($this->page);
    }
    else
    {
      $this->page = 1;
    }

    if ($this->page <= 0)
    {
      // set first page, which depends on a maximum set
      $this->page = $this->getMaxPerPage() ? 1 : 0;
    }
  }

}
