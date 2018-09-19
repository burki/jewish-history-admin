<?php
/*
 * admin_zotero.inc.php
 *
 * Sync Zotero Items
 *
 * (c) 2016-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-07-23 dbu
 *
 * Changes:
 *
 */
require_once INC_PATH . 'admin/displaybackend.inc.php';

class ZoteroFlow
extends TableManagerFlow
{
  const DETAILS = 1000;
  const SYNC = 1010;


  function init ($page) {
    if (isset($page->parameters['view'])
        && 'sync' == $page->parameters['view'])
    {
      return self::SYNC;
    }

    if (!empty($page->parameters['details'])) {
      return self::DETAILS;
    }

    return TABLEMANAGER_LIST;
  }
}

class DisplayZotero
extends DisplayBackend
{
  var $page_size = 30;
  var $table = 'Zotero';
  var $distinct_listing = true;
  var $fields_listing = [ "concat(corresp,'|',title)", 'corresp', 'title' ];
  var $cols_listing = [
    'corresp' => 'Key',
    'title' => 'Title',
    '' => '',
  ];
  var $order = [
    'corresp' => ['corresp', 'corresp DESC'],
    'title' => ['title', 'title DESC'],
  ];
  var $condition = [
    [ 'name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'corresp,title' ],
    'Zotero.status <> -1'
    // alternative: buildFulltextCondition
  ];

  const API_PAGE_SIZE = 20;
  const GROUP_ID = '301118';

  function __construct (&$page, $workflow = null) {
    $workflow = new ZoteroFlow($page); // only list and sync

    parent::__construct($page, $workflow);
  }

  function buildSync () {
    $ret = '';
    $forceResync = false;

    $dbconn = new DB;

    // TODO: proper syncing including deleted
    // see https://www.zotero.org/support/dev/web_api/v3/syncing
    $maxModified = null;

    if (!$forceResync) {
      $querystr = sprintf("SELECT MAX(zoteroModified) AS maxModified FROM Zotero WHERE status <> %d",
                          STATUS_DELETED);
      $dbconn->query($querystr);
      if ($dbconn->next_record()) {
        $maxModified = join('T', explode(' ', $dbconn->Record['maxModified'])) . 'Z';
      }
    }

    $api = ZoteroApiFactory::getInstance();
    $start = 0;
    $continue = true;
    while ($continue) {
      $request = $api->group(self::GROUP_ID)
          ->items()
          ->sortBy('dateModified')
          ->direction(is_null($maxModified) ? 'asc' : 'desc')
          ->start($start)
          ->limit(self::API_PAGE_SIZE);

      var_dump('Start: ' . $start);
      flush();
      set_time_limit(60);
      $response = $request->send();

      $statusCode = $response->getStatusCode();
      if ($statusCode < 200 || $statusCode >= 300) {
        // something went wrong
        break;
      }

      $headers = $response->getHeaders();

      $start += self::API_PAGE_SIZE;
      $continue = $start < $headers['Total-Results'][0];

      // now process the items
      $items = $response->getBody();
      foreach ($items as $item) {
        $dateModified = $item['data']['dateModified'];
        if (!is_null($maxModified) && strcmp($dateModified, $maxModified) < 0) {
          $continue = false;
          break;
        }

        if (!empty($item['key']) && !in_array($item['data']['itemType'], [ 'note', 'attachment' ])) {
          $this->processItem($dbconn, $item);
        }
      }
    }

    return $ret;
  } // buildContent

  function processItem($dbconn, $item)
  {
    // check if we already have this item
    $querystr = sprintf("SELECT id, zoteroVersion FROM Zotero WHERE zoteroKey='%s' AND status <> %d",
                        $dbconn->escape_string($item['key']), STATUS_DELETED);
    $dbconn->query($querystr);
    $update = null;
    if ($dbconn->next_record()) {
      if ($dbconn->Record['zoteroVersion'] >= $item['version']) {
        return 0; // no update needed
      }

      $update = $dbconn->Record['id']; // update needed
    }

    // we need to insert/update
    $record = [
      'zoteroKey' => $item['key'],
      'zoteroModified' => join(' ',
                               explode('T',
                                       rtrim($item['data']['dateModified'], 'Z'))
                               ),
      'zoteroVersion' => $item['version'],
      'zoteroData' => json_encode($item['data'], JSON_UNESCAPED_SLASHES |  JSON_UNESCAPED_UNICODE),
      'itemType' => $item['data']['itemType'],
      'title' => $item['data']['title'],
    ];

    $record['corresp'] = $this->buildCorresp($item);
    $this->insertUpdate($dbconn, $record, $update);
  }

  function buildCorresp($item)
  {
    $slugify = new \Cocur\Slugify\Slugify();

    if (!empty($item['data']['extra'])
        && preg_match('/[a-z\-0-9]+_[0-9][0-9a-z]*/', $item['data']['extra']))
    {
      // manually set
      return $item['data']['extra'];
    }

    $creator = !empty($item['meta']['creatorSummary'])
      ? $item['meta']['creatorSummary'] : 'NN';
    if (!empty($item['meta']['parsedDate'])
        && preg_match('/^(\d+)/', $item['meta']['parsedDate'], $matches))
    {
      $date = $matches[1];
    }
    else {
      $date = 'oj';
    }

    return $slugify->slugify($creator, '-') . '_' . $date;
  }

  function insertUpdate($dbconn, $record, $primaryKey)
  {
    $sql_fields = [ 'changed' ];
    $sql_values = [ 'NOW()' ];
    foreach ($record as $key => $value) {
      $sql_fields[] = $key;
      $sql_values[] = "'" . $dbconn->escape_string($value) . "'";
    }
    if (!is_null($primaryKey)) {
      // update
      $querystr = 'UPDATE Zotero SET ';
      for ($i = 0; $i < count($sql_fields); $i++) {
        if ($i > 0) {
          $querystr .= ', ';
        }
        $querystr .= $sql_fields[$i] . '=' . $sql_values[$i];
      }
      $querystr .= ' WHERE id = ' . $primaryKey;
    }
    else {
      $sql_fields[] = 'created';
      $sql_values[] = 'NOW()';

      $querystr = 'INSERT INTO Zotero ('
        . join(', ', $sql_fields)
        . ') VALUES ('
        . join(', ', $sql_values)
        . ')';
    }
    var_dump($querystr);
    $dbconn->query($querystr);
  }

  function lookupCollectionIds ($collectionIds) {
    $ret = [];
    $api = ZoteroApiFactory::getInstance();
    foreach ($collectionIds as $collectionId) {
      $request = $api->group(self::GROUP_ID)
          ->collections($collectionId);

      $response = $request->send();

      $statusCode = $response->getStatusCode();
      if ($statusCode < 200 || $statusCode >= 300) {
        continue;
      }

      $body = $response->getBody();
      $ret[$body['data']['key']] = $body['data']['name'];
    }

    return $ret;
  }

  function checkItem ($itemId) {
    $api = ZoteroApiFactory::getInstance();
    $request = $api->group(self::GROUP_ID)
            ->items($itemId);
    try {
      $response = $request->send();
    }
    catch (\GuzzleHttp\Exception\ClientException $e) {
      if (404 == $e->getResponse()->getStatusCode()) {
        // deleted
        return false;
      }
    }

    // TODO: maybe additional checks
    return true;
  }

  function buildDetails () {
    $ret = '';
    list($corresp, $title) = explode('|', rawurldecode($this->page->parameters['details']), 2);
    $dbconn = new DB();
    $querystr = sprintf("SELECT id, title, zoteroKey, zoteroData FROM Zotero WHERE corresp='%s' AND title='%s' AND status <> %d",
                        $dbconn->escape_string($corresp), $dbconn->escape_string($title), STATUS_DELETED);
    $dbconn->query($querystr);
    $entries = [];
    $collections = [];
    while ($dbconn->next_record()) {
      $record = $dbconn->Record;
      $key = $record['zoteroKey'];
      if (!$this->checkItem($key)) {
        // removal doesn't get synced, so do it here
        $querystr = sprintf("UPDATE Zotero SET status = %d WHERE zoteroKey = '%s'",
                           STATUS_DELETED,  $dbconn->escape_string($key));
        $dbsub = new DB();
        $dbsub->query($querystr);
      }
      else {
        $entries[$key] = $record;
        $entries[$key]['data'] = $data = json_decode($record['zoteroData'], true);
        if (!empty($data['collections'])) {
          foreach ($data['collections'] as $collectionId) {
            $collections[$collectionId] = [];
          }
        }
      }
    }
    $collectionIds = array_keys($collections);
    if (!empty($collectionIds)) {
      $collections = $this->lookupCollectionIds($collectionIds);
    }

    if (!empty($entries)) {
      $ret .= '<h2>' . $this->htmlSpecialchars($title) . '</h2>';
      foreach ($entries as $key => $entry) {
        $containedIn = [];
        if (!empty($entry['data']['collections'])) {
          foreach ($entry['data']['collections'] as $collectionId) {
            $containedIn[] = array_key_exists($collectionId, $collections)
              ? $collections[$collectionId] : $collectionId;
          }
        }
        sort($containedIn);
        if (!empty($containedIn)) {
          $ret .= '<p>' . $this->htmlSpecialchars(join(', ', $containedIn)). '</p>';
        }
      }
    }

    return $ret;
  }

  function buildListingTop ($may_add = false) {
    return parent::buildListingTop($may_add);
  }

  function buildListingCell (&$row, $col_index, $val = null) {
    // expect primary-key in first field, "title" in the second and merge those two:
    if (!$this->idcol_listing && 0 == $col_index) {
      return '';
    }

    $cell = isset($val) ? $val : $this->htmlSpecialchars($row[$col_index]);
    if (1 == $col_index) {
      $cell = sprintf('<a href="%s">%s</a>',
                      htmlspecialchars($this->page->buildLink(['pn' => $this->page->name, 'details' => rawurlencode($row[0])])), $cell);
    }

    return '<td class="listing">' . $cell . '</td>';
  }

  function buildContent () {
    if (ZoteroFlow::SYNC == $this->step) {
      $res = $this->buildSync();
    }

    if (ZoteroFlow::DETAILS == $this->step) {
      return $this->buildDetails();
    }

    return parent::buildContent();
  }
}

$display = new DisplayZotero($page);
if (false === $display->init()) {
  $page->redirect(['pn' => '']);
}
$page->setDisplay($display);
