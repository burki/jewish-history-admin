<?php
/*
 * admin_presentation.inc.php
 *
 * Update article and source
 *
 * (c) 2019-2020 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2020-08-05 dbu
 *
 * Changes:
 *
 */
require_once INC_PATH . 'admin/displaybackend.inc.php';

class PresentationFlow
extends TableManagerFlow
{
  const SYNC = 1010;

  function init ($page) {
    $res = parent::init($page);
    if ($res != TABLEMANAGER_VIEW) {
      return false;
    }

    /*
    if (isset($page->parameters['view'])
        && 'sync' == $page->parameters['view'])
    {
      return self::SYNC;
    }
    */
    return $res;
  }
}

class DisplayPresentation
extends DisplayBackend
{
  var $page_size = 30;
  var $table = 'Media';
  var $fields_listing = [ 'name' ];
  var $cols_listing = [
    'title' => 'Title',
    '' => '',
  ];
  var $order = [
    'name' => ['name', 'name DESC'],
  ];
  var $condition = [
    [ 'name' => 'search', 'method' => 'buildLikeCondition', 'args' => 'name' ],
    'Media.status <> -1'
    // alternative: buildFulltextCondition
  ];

  function __construct (&$page, $workflow = null) {
    $workflow = new PresentationFlow($page); // only detail and actions

    parent::__construct($page, $workflow);
  }

  function buildRecord ($name = '') {
    $record = parent::buildRecord($name);

    if (!isset($record)) {
      return;
    }

    $record->add_fields([
      new Field([ 'name' => 'id', 'type' => 'hidden', 'datatype' => 'int', 'primarykey' => true ]),
      new Field([ 'name' => 'item_id', 'type' => 'hidden', 'datatype' => 'int', 'null' => true ]),
      new Field([ 'name' => 'type', 'type' => 'hidden', 'datatype' => 'int', 'noupdate' => true ]),
      new Field([ 'name' => 'ord', 'type' => 'hidden', 'datatype' => 'int', 'value' => 0 ]),
      new Field([ 'name' => 'width', 'type' => 'hidden', 'datatype' => 'int', 'null' => true ]),
      new Field([ 'name' => 'height', 'type' => 'text', 'datatype' => 'int', 'null' => true ]),
      new Field([ 'name' => 'name', 'type' => 'hidden', 'datatype' => 'char', 'null' => true ]),
      new Field([ 'name' => 'mimetype', 'type' => 'hidden', 'datatype' => 'char', 'null' => true ]),
      new Field([ 'name' => 'original_name', 'type' => 'hidden', 'datatype' => 'char', 'null' => true ]),
      new Field([ 'name' => 'caption', 'type' => 'textarea', 'cols' => 60, 'rows' => 3, 'datatype' => 'char', 'null' => true ]),
      new Field([ 'name' => 'copyright', 'type' => 'text', 'size' => 60, 'maxlength' => 255, 'datatype' => 'char', 'null' => true ]),
      new Field([ 'name' => 'created', 'datatype' => 'function', 'value' => 'NOW()', 'noupdate' => true ])
    ]);

    return $record;
  }

  function buildEditButton ()
  {
    return '';
  }

  function getEditRows ($mode = 'edit') {
    $rows = [
      'id' => false, 'item_id' => false,
      'original_name' => [ 'label' => tr('File Name') ],

      (isset($this->form) ? $this->form->show_submit(tr('Store')) : ''),
    ];

    return $rows;
  }

  function buildViewFooter ($found = true) {
    require_once INC_PATH . 'common/PresentationService.php';

    $options = [];
    if (defined('URL_PRESENTATION_DE') || defined('URL_PRESENTATION_EN')) {
      $lang_settings = [];
      if (defined('URL_PRESENTATION_DE')) {
        $lang_settings['deu'] = [ 'url' => URL_PRESENTATION_DE ];
      }
      if (defined('URL_PRESENTATION_EN')) {
        $lang_settings['eng'] = [ 'url' => URL_PRESENTATION_EN ];
      }
      $options['lang_settings'] = $lang_settings;
    }
    $presentationService = new PresentationService(new DB_Presentation(), $options);

    $item_id = $this->record->get_value('item_id');
    $name = $this->record->get_value('name');
    $mimetype = $this->record->get_value('mimetype');
    $original_name = $this->record->get_value('original_name');
    $type = $GLOBALS['TYPE_PUBLICATION'] == $this->record->get_value('type')
      ? 'source' : 'article';

    $info = $presentationService->lookupArticle($item_id, $original_name);

    if (false === $info) {
      return sprintf('Error: no corresponding %s found',
                     $type);
    }

    if ($info['type'] != $type) {
      return sprintf('Error: %s is not of type %s',
                     $info['uid'], $type);
    }

    $ret = '';

    $fnameUpload = $this->buildImgFname($item_id, $this->record->get_value('type'), $name, $mimetype);

    if (defined('RSYNC_REMOTE_HOST')) {
      // enable updating upload from hidrive

      require_once INC_PATH . 'common/RsyncService.php';
      $rsyncService = new RsyncService([
        'ssh_config' => [
          'executable' => defined('RSYNC_SSH') ? RSYNC_SSH : '/usr/bin/ssh',
          'host' => RSYNC_REMOTE_HOST,
          'username' => RSYNC_REMOTE_USER,
          'private_key' => realpath(INC_PATH . 'ssh/' . RSYNC_REMOTE_USER),
        ],
        'rsync_config' => [
          'executable' => defined('RSYNC_EXECUTABLE') ? RSYNC_EXECUTABLE : '/usr/bin/rsync',
          'archive' => false,
          'recursive' => false,
        ],
      ]);

      $fileInfo = $rsyncService->listRemote($remotePath = ($info['status'] > 0 ? RSYNC_REMOTE_PUBLISHED : RSYNC_REMOTE_UNPUBLISHED));
      if (array_key_exists($original_name, $fileInfo)) {
        if (array_key_exists('action', $_GET) && 'sync' == $_GET['action']) {
          $res =  $rsyncService->fetchFromRemote($remotePath . $original_name, $fnameUpload);
          if (0 != $res) {
            $ret .= 'ERROR: ' . $remotePath . $original_name . ' could not be fetched';
          }
        }

        $mtimeUpload = filemtime($fnameUpload);
        if ($fileInfo[$original_name]['mtime'] >= $mtimeUpload) {
          $ret .=
            '<p>'
            . sprintf('INFO: ' . tr('Hidrive version is newer (%s) than local version (%s).'),
                      date('d.m.Y H:i:s', $fileInfo[$original_name]['mtime']),
                      date('d.m.Y H:i:s', $mtimeUpload))
            . sprintf('<br>[<a href="%s">%s</a>]',
                      htmlspecialchars($this->page->buildLink([
                        'pn' => $this->page->name, 'view' => $this->workflow->id,
                        'display' => 'embed',
                        'action' => 'sync',
                      ])),
                      tr('update from Hidrive'))
            . '</p>'
            ;
        }
        /*
        $ret .= 'Remote date: ' . $fileInfo[$original_name]['mtime'] . '<br />';
        $ret .= 'Remote size: ' . $fileInfo[$original_name]['size'] . '<br />';


        $ret .= 'Local date: ' . filemtime($fnameUpload) . '<br />';
        $ret .= 'Local size: ' . filesize($fnameUpload) . '<br />';
        */
      }
    }

    $refresh_button = '';

    $cmd = $presentationService->buildRefreshCommand($info['type'], $info['uid'], $info['lang']);

    if (false !== $cmd) {
      $fnamePresentation = $presentationService->buildTeiFname($info['type'], $info['uid'], $info['lang']);

      $allowRefresh = false;
      if ($fnamePresentation !== false && ($allowRefresh = $presentationService->allowRefresh($fnameUpload, $fnamePresentation))) {
        if (array_key_exists('action', $_GET) && 'refresh' == $_GET['action']) {
          if (1 === $allowRefresh) {
            // files are identical, no need to copy
            $copyied = true;
          }
          else {
            $copyied = $presentationService->refreshFile($fnameUpload, $fnamePresentation);
          }

          if (!$copyied) {
            $ret .= 'Error copying: ' . $fnameUpload . ' to ' . $fnamePresentation;
          }
          else {
            $output = $presentationService->runRefreshCommand($info['type'], $info['uid'], $info['lang']);
            if (false === $output) {
              $ret .= 'Error running: <tt>' . join(' ', $cmd) . '</tt>';
            }
            else {
              $ret .= 'Output: <tt><pre>' . "\n" . $output . "\n</pre></tt>";
            }
          }
        }
        else {
          $refresh_button = sprintf('[<a href="%s">%s</a>]',
                htmlspecialchars($this->page->buildLink([
                  'pn' => $this->page->name, 'view' => $this->workflow->id,
                  'display' => 'embed',
                  'action' => 'refresh',
                ])),
                $this->htmlSpecialchars(tr('refresh')));
        }
      }

      if (0 === $allowRefresh) {
        $ret .= 'Info: Presentation copy is already newer than uploaded version';
      }
    }

    $url = $presentationService->buildPresentationUrl($info['type'], $info['uid'], $info['lang']);
    $ret .= sprintf('<p><a href="%s" target="_blank">%s</a> %s</p>',
                    htmlspecialchars($url), htmlspecialchars($url),
                    $refresh_button);

    return $ret;
  }

  function buildContent () {
    /*
    if (ZoteroFlow::SYNC == $this->step) {
      $res = $this->buildSync();
    }
    */

    return parent::buildContent();
  }
}

$display = new DisplayPresentation($page);
if (false === $display->init()) {
  $page->redirect([ 'pn' => '' ]);
}
$page->setDisplay($display);
