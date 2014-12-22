<?php
/*
 * pagedisplay.inc.php
 *
 * Base Display class for Admin-pages
 *
 * (c) 2006-2014 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2014-12-22 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/pagedisplay.inc.php';

class PageDisplay extends PageDisplayBase
{
  var $page;
  var $charset = 'utf-8';
  var $invalid;
  var $is_internal = FALSE;
  var $issue;
  var $location;
  var $stylesheet = 'admin.css';
  var $span_range = NULL; // '[\x{3400}-\x{9faf}]';
  var $span_class = ''; // 'cn';
  var $xls_data = array();

  function __construct (&$page) {
    global $RIGHTS_EDITOR;

    $this->page = $page;
    $this->is_internal = 0 != ($this->page->user['privs'] & $RIGHTS_EDITOR);
  }

  function formatTimestamp ($when, $format = 'd.m.Y') {
    return $when > 0 ? date($format, $when) : '';
  }

  // for form-handling
  function getFormField($name, $args = '') {
    $ret = '';
    $field = $this->form->field($name);
    if (isset($field)) {
      if (isset($this->invalid[$name])) {
        $error_lang = preg_replace('/\_.*/', '', $this->page->lang());

        $ret = '<div class="error">'
             . $this->form->error_fulltext($this->invalid[$name], $error_lang)
             . '</div>';
      }

      $ret .= $field->show();
    }
    return $ret;
  }

  function buildRequired ($label) {
    return $label . '*';
  }

  function buildFormRow ($left, $right = '') {
    return $this->buildContentLine($left, $right,
                                   array('class_left' => 'form', 'class_right' => 'form'));
  }

  function buildContentLine($left, $right, $params = array()) {
    $class_left = isset($params['class_left']) ? $params['class_left'] : 'leftFixedWidth';
    $class_right = isset($params['class_right']) ? $params['class_right'] : 'rightFixedWidth';
    if (empty($right)) {
      $right = '&nbsp;';
    }

    // span fixes IE 7 bug: http://jaspan.com/ie-inherited-margin-bug-form-elements-and-haslayout
    $ret = <<<EOT
    <div class="container">
      <div class="$class_left">$left</div>
      <div class="$class_right"><span>$right</span></div>
    </div>
EOT;

    return $ret;
  }

  function buildContentLineMultiple ($lines, $params = array()) {
    $ret = '';

    for ($i = 0; $i < count($lines); $i++) {
      $line = &$lines[$i];

      $left = isset($line['left']) ? $line['left'] : $line[0];
      $right = isset($line['right']) ? $line['right'] : $line[1];
      $ret .= $this->buildContentLine($left, $right, $params);
    }

    return $ret;
  }

  function buildChangedBy () {
    $changed = '';
    $changed_datetime = $this->form->get_value('changed');
    if (!empty($changed_datetime) && 'NOW()' != $changed_datetime) {
      $changed = sprintf(tr('Last change: %s'), $changed_datetime);
      $changed_by = $this->form->get_value('changed_by');
      if (isset($changed_by)) {
        $querystr = sprintf("SELECT email FROM User WHERE id=%d", $changed_by);
        $dbconn = & $this->page->dbconn;
        $dbconn->query($querystr);
        if ($dbconn->next_record())
          $changed .= sprintf(' %s %s',
                              tr('by'),
                              $this->htmlSpecialChars($dbconn->Record['email']));
      }
      $changed = '<p>' . $changed . '</p>';
    }

    return $changed;
  }

  function buildImgFname ($item_id, $type, $name, $mime) {
    global $MEDIA_EXTENSIONS, $UPLOAD_TRANSLATE;

    $folder = UPLOAD_FILEROOT.$UPLOAD_TRANSLATE[$type]
              .sprintf(".%03d/id%05d/",
                       intval($item_id / 32768), intval($item_id % 32768));

    return $folder . $name . $MEDIA_EXTENSIONS[$mime];
  }

  function buildImgUrl ($item_id, $type, $name, $mime, $append_uid = FALSE) {
    global $MEDIA_EXTENSIONS, $UPLOAD_TRANSLATE;
    static $uid;
    if (empty($uid)) {
      $uid = uniqid();
    }

    $folder = UPLOAD_URLROOT.$UPLOAD_TRANSLATE[$type]
            . sprintf(".%03d/id%05d/",
                      intval($item_id / 32768), intval($item_id % 32768));

    return $folder . $name . $MEDIA_EXTENSIONS[$mime]
         . ($append_uid ? '?uid='.$uid : '');
  }


  function buildImgTag ($relurl, $attrs = '') {
    // global $IMG_ENLARGE_ADDWIDTH, $IMG_ENLARGE_ADDHEIGHT;
    $IMG_ENLARGE_ADDWIDTH = $IMG_ENLARGE_ADDHEIGHT = 4;

    $url_enlarge = '';

    if ($attrs == '') {
      $attrs = array();
    }
    if (!isset($attrs['alt'])) {
      $attrs['alt'] = '';
    }
    if ((array_key_exists('enlarge', $attrs) && $attrs['enlarge'])
      || (!isset($attrs['width']) && !isset($attrs['height'])))
    {
      $fname = preg_match('/^' . preg_quote(UPLOAD_URLROOT, '/') . '/', $relurl)
        ? preg_replace('/^' . preg_quote(UPLOAD_URLROOT, '/') . '/', UPLOAD_FILEROOT, $relurl)
        : preg_replace('/^' . preg_quote(BASE_PATH, '/') . '/', './', $relurl);
      $fname = preg_replace('/\?.*/', '', $fname);
      $size = @getimagesize($fname);
      if (isset($size)) {
        if (!isset($attrs['width']) && !isset($attrs['height'])) {
          $attrs['width'] = $size[0]; $attrs['height'] = $size[1];
        }
        $fname_large = preg_replace('/(\_small)?\.([^\.]+)$/', '_large.\2', $fname);
        if (isset($attrs['enlarge']) && $attrs['enlarge'] !== FALSE) {
          // var_dump($fname_large);

          if (file_exists($fname_large)) {
            $size_large = getimagesize($fname_large);
            $url_enlarge = "window.open('".BASE_PATH."img.php?url=".urlencode($relurl)."&width=".$size_large[0]."&height=".$size_large[1]."&caption=".urlencode($attrs['enlarge_caption'])."', '_blank', 'width=".($size_large[0] + $IMG_ENLARGE_ADDWIDTH).",height=".($size_large[1] + $IMG_ENLARGE_ADDHEIGHT).",resizable=yes');";
            $url_enlarge .= 'return false;';
            $attrs['alt'] = 'Click to enlarge';
          }
          else if (isset($attrs['enlarge_only'])) {
            $url_enlarge = "window.open('".BASE_PATH."img.php?url=".urlencode($relurl)."&large=0&width=".$size[0]."&height=".$size[1]."&caption=".urlencode($attrs['enlarge_caption'])."', '_blank', 'width=".($size[0] + $IMG_ENLARGE_ADDWIDTH).",height=".($size[1] + $IMG_ENLARGE_ADDHEIGHT).",resizable=yes');";
            $url_enlarge .= 'return false;';
          }
        }
      }
    }

    $attrstr = '';
    foreach ($attrs as $attr => $value) {
      if ($attr != 'enlarge' && $attr != 'enlarge_only' && $attr != 'enlarge_caption' && $attr != 'anchor') {
        $attrstr .= ($attr.'="'.$value.'" ');
      }
    }
    if (isset($attrs['enlarge_only']) && (!empty($url_enlarge))) {
      $img_tag = $attrs['enlarge_only'];
    }
    else if (isset($relurl))
      $img_tag = '<img src="'.$relurl.'" '.$attrstr.'/>';

    if (isset($attrs['caption']) && !empty($attrs['caption'])) {
      $img_tag .= $attrs['caption'];
    }
    if (!empty($url_enlarge)) {
      $img_tag = '<a href="#' . (isset($attrs['anchor']) ? $attrs['anchor'] : '').'" onclick="'.$url_enlarge.'"'.(isset($attrs['anchor']) ? ' name="'.$attrs['anchor'].'"' : '') . '>' . $img_tag . '</a>';
    }
    else if (isset($attrs['anchor'])) {
      $img_tag = '<a name="' . $attrs['anchor'] . '">' . $img_tag . '</a>';
    }
    return $img_tag;
  }

  function fetchImage (&$dbconn, $item_id, $type, $img_name) {
    $querystr = sprintf("SELECT item_id, caption, copyright, width, height, mimetype FROM Media WHERE item_id=%d AND type=%d AND name='%s'",
                        $item_id, $type, $dbconn->escape_string($img_name));

    $dbconn->query($querystr);
    if ($dbconn->next_record()) {
      return $dbconn->Record;
    }
  }

  function buildImage($item_id, $type, $img_name,
                      $enlarge = FALSE, $append_uid = FALSE, $return_caption = FALSE, $alt = NULL)
  {
    $dbconn = new DB;

    $img = $this->fetchImage($dbconn, $item_id, $type, $img_name);
    if (isset($img)) {
      $caption = $img['caption'];
      $copyright = $img['copyright'];
      $img_url = $this->buildImgUrl($item_id, $type, $img_name, $img['mimetype'], $append_uid);

      if (in_array($img['mimetype'], array('text/rtf',
                                           'application/vnd.oasis.opendocument.text',
                                           'application/msword',
                                           'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))) {
        $img_tag = sprintf('<a href="%s" target="_blank">%s</a>',
                           htmlspecialchars($img_url),
                           $this->formatText(empty($caption) ? 'Office-Datei' : $caption));
      }
      else if (in_array($img['mimetype'], array(
                                                'audio/mpeg',
                                                )))
      {
        $img_tag = sprintf('<audio src="%s" preload="none" controls></audio>',
                           htmlspecialchars($img_url));
        $img_tag .= sprintf('<br /><a href="%s" target="_blank">%s</a>',
                           htmlspecialchars($img_url),
                           $this->formatText(empty($caption) ? 'Audio' : $caption));
      }
      else if ('application/pdf' == $img['mimetype']) {
        if (!$append_uid) {
          $img_tag = $this->buildPdfViewer($img_url, empty($caption) ? 'PDF' : $caption,
                                           array('thumbnail' => $this->buildThumbnailUrl($item_id, $type, $img_name, $img['mimetype'])));
        }
        else {
          $img_tag = sprintf('<a href="%s" target="_blank">%s</a>',
                             htmlspecialchars($img_url),
                             $this->formatText(empty($caption) ? 'PDF' : $caption));
        }
      }
      else {
        $params = array('width' => $img['width'], 'height' => $img['height'], 'enlarge' => $enlarge, 'enlarge_caption' => $this->formatText($caption), 'border' => 0);
        if (NULL !== $alt) {
          $params['alt'] = $params['title'] = $alt;
        }

        $img_tag = $this->buildImgTag($img_url, $params);
      }

      if ($return_caption) {
        return array($img_tag, $caption, $copyright);
      }

      return $img_tag;
    }
  }

  function buildHtmlLinkTags () {
    $tags = array(); // css, rss
    if (!empty($this->stylesheet)) {
      //link to stylesheet
      $tags[] =
        sprintf('<link rel="stylesheet" href="%s" type="text/css"></link>',
                htmlspecialchars($this->page->BASE_PATH . $this->stylesheet));
    }
    return implode("\n", $tags);
  }

  function buildHtmlEnd () {
return <<<EOT
</body>
</html>
EOT;
  }

  function getImageDescriptions () {
    // override for actual images
  }

  function instantiateUploadHandler () {
    list($media_type, $this->images) = $this->getImageDescriptions();

    if (isset($this->images)) {
      // check if we have to setup an image for body-rendering
      foreach ($this->images as $name => $descr) {
        if (isset($descr['placement']) && 'body' == $descr['placement']) {
          $this->image = array('item_id' => $this->workflow->primaryKey(),
                               'type' => $media_type, 'name' => $name);
        }
      }
      return new ImageUploadHandler($this->workflow->primaryKey(), $media_type);
    }
  }

  function processUpload (&$imageUploadHandler) {
    $action = $this->page->buildLink(array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $this->id));
    $upload_results = array();
    foreach ($this->images as $img_basename => $img_descr) {
      $img_params = $img_descr['imgparams'];
      if (isset($img_descr['multiple'])) {
        if ('boolean' == gettype($img_descr['multiple'])) {
          $max_images = $img_descr['multiple'] ? -1 : 1;
        }
        else {
          $max_images = intval($img_descr['multiple']);
        }
      }
      else {
        $max_images = 1;
      }

      // check if we need to delete something
      if (array_key_exists('delete_img', $this->page->parameters)
          && substr($this->page->parameters['delete_img'], 0, strlen($img_basename)) == $img_basename)
      {
        $imageUploadHandler->delete($this->page->parameters['delete_img']);
      }

      $options = array();
      if (isset($img_params['title'])) {
        $options['title'] = $img_params['title'];
      }

      $images = $imageUploadHandler->buildImages($img_basename, $img_params, $max_images, $options);
      $imageUpload = $imageUploadHandler->buildUpload($images, $action);

      if ($imageUpload->submitted()) {
        $upload_results[$img_basename] = $imageUploadHandler->process($imageUpload, $images);
      }
    }
    $this->upload_results = $upload_results;
  }

  function getUploadFormField(&$upload_form, $name, $args = '') {
    $ret = '';
    $field = $upload_form->field($name);
    if (isset($field)) {
      if (isset($this->invalid[$name]))
        $ret = '<div class="error">'
             . $this->form->error_fulltext($this->invalid[$name], $this->page->lang)
             . '</div>';

      $ret .= $field->show($args);
    }
    return $ret;
  }

  function renderUpload (&$imageUploadHandler, $title = 'Image Upload') {
    $ret = '<h2>' . $this->formatText(tr($title)) . '</h2>';

    $params_self = array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $this->id);
    $action = $this->page->buildLink($params_self);

    $first = TRUE;

    foreach ($this->images as $img_basename => $img_descr) {
      $rows = array();
      if (isset($img_descr['title'])) {
        $ret .= '<h3>' . $img_descr['title'] . '</h3>';
      }

      $upload_results = isset($this->upload_results[$img_basename])
        ? $this->upload_results[$img_basename]: array();
      // var_dump($upload_results);

      $max_images = 1;
      if (isset($img_descr['multiple'])) {
        if ('boolean' == gettype($img_descr['multiple'])) {
          $max_images = $img_descr['multiple'] ? -1 : 1;
        }
        else {
          $max_images = intval($img_descr['multiple']);
        }
      }

      $img_params = $img_descr['imgparams'];

      $options = array();
      if (isset($img_params['title'])) {
        $options['title'] = $img_params['title'];
      }
      $images = $imageUploadHandler->buildImages($img_basename, $img_params, $max_images, $options);
      $imageUpload = $imageUploadHandler->buildUpload($images, $action);

      if ($first) {
        $ret .= $imageUpload->show_start();
        $first = FALSE;
      }

      $imageUploadHandler->fetchAll();

      $count = 0;
      foreach ($imageUploadHandler->img_titles as $img_name => $title) {
        if (substr($img_name, 0, strlen($img_basename)) != $img_basename) {
          continue;
        }

        $img = $imageUpload->image($img_name);
        if (isset($img)) {
          $img_field = '';
          if ($max_images != 1) {
            if ($count > 0)
              $rows[] = '<hr />';
            $img_field .= '<h4>' . $title . '</h4>';
          }
          ++$count;

          $img_form = & $imageUploadHandler->img_forms[$img_name];
          if (isset($upload_results[$img_name]) && isset($upload_results[$img_name]['status']) && $upload_results[$img_name]['status'] < 0) {
            $img_field .= '<div class="message">'.$upload_results[$img_name]['msg'].'</div>';
          }

          $url_delete = $this->page->buildLink(array_merge($params_self,
                                               array('delete_img' => $img_name)));

          list($img_tag, $caption, $copyright) = $this->buildImage($imageUploadHandler->item_id, $imageUploadHandler->type, $img_name, TRUE, TRUE, TRUE);
          // var_dump($img_tag);
          if (!empty($img_tag)) {
            $img_field .= '<p><div style="margin-right: 2em; margin-bottom: 1em; float: left;">' . $img_tag . '</div>'
                        . (!empty($caption) ? $this->formatText($caption).'<br />&nbsp;<br />' : '')
                        . '[<a href="' . htmlspecialchars($url_delete) . '">' . tr('delete') . '</a>]<br clear="left" /></p>';
          }

          $rows[] = $img_field;

          $rows[] = array('File', $img->show_upload_field());
          $rows[] = array('Image Caption', $this->getUploadFormField($img_form, 'caption', array('prepend' => $img_name . '_')));
          $rows[] = array('Copyright-Notice', $this->getUploadFormField($img_form, 'copyright', array('prepend' => $img_name . '_')));

          $rows[] = array('', '<input type="submit" value="' . ucfirst(tr('upload')) . '" />');
        } // if
      }
      foreach ($rows as $row) {
        if ('array' == gettype($row)) {
          $ret .= $this->buildContentLine(tr($row[0]), $row[1]);
        }
        else {
          $ret .= $row;
        }
      } // foreach
    } // foreach

    if (!$first) {
      $ret .= $imageUpload->show_end();
    }

    return $ret;
  }

  function buildMenu () {
    global $SITE_DESCRIPTION;

    $url_main = $this->page->buildLink(array('pn' => '')); // '../';

    $ret = '<div id="header">';

    if (!empty($this->page->user)) {
      $ret .= '<div id="menuAccount" style="font-size: 9pt; float: right">'
            . $this->formatText($this->page->user['login'])
            . ' | <a class="inverse" href="' . $this->page->buildLink(array('pn' => 'account', 'edit' => $this->page->user['id'])).'">'.tr('My Account').'</a> | <a class="inverse" href="'.$this->page->buildLink(array('pn' => '', 'do_logout' => 1)).'">'.tr('Sign out').'</a></div>';
      if (!$this->is_internal) {
        $this->page->site_description['structure']['root']['title'] = 'My Subscription';
      }
    }
    $ret .= sprintf('<a href="%s"><img src="%s" style="margin-left: 4px; vertical-align: text-bottom; border: 0;" width="515" height="106" alt="%s" /></a> ',
                    $url_main,
                    $this->page->BASE_PATH . 'media/logo.png',
                    $this->htmlSpecialchars(tr($SITE_DESCRIPTION['title'])));
    $entries = array();

    if (isset($this->page->path)) {
      foreach ($this->page->path as $entry) {
        $url = $this->page->buildLink(array('pn' => $entry));
        $entries[] = '<a class="inverse" href="' . $url . '">'
                   . $this->htmlSpecialchars($this->page->buildPageTitle($entry))
                   . '</a>';

      }
    }
    if (isset($this->step) && $this->step > 0) {
      $entries[] = tr($this->workflow->name($this->step));
    }

    if (count(Page::$languages) > 0) {
      $languages = array();
      foreach (Page::$languages as $lang => $label) {
        if ($lang != $this->page->lang()) {
          $label = '<a class="inverse" href="?lang=' . $lang . '">' . $this->formatText($label) . '</a>';
        }
        else {
          $label = $this->formatText($label);
        }

        $languages[] = $label;
      }
      $ret .= '<div id="languages">' . implode(' ', $languages) . '</div>';
    }

    if (count($entries) > 0) {
      $ret .= '<div id="breadcrumbs">';
      $ret .= implode(' / ', $entries);
      $ret .= '</div>';
    }

    $ret .= '</div>'; // id="header"

    return $ret;
  }

  function show () {
    $content = $this->buildContent(); // this one may redirect

    if ('xls' == $this->page->display) {
      // include the php-excel class
      require LIB_PATH . 'class-excel-xml.inc.php';

      // generate excel file
      $xls = new Excel_XML;
      $xls->addArray($this->xls_data);
      $xls->generateXML('liste');
      exit;
    }

    echo $this->buildHtmlStart()
       . "\n"
       . '<div id="holder">';

    echo $this->buildMenu();
    echo '<div id="content">' . $content . "</div>\n";

    // echo '<div id="footer"><div align="right" style="padding:0.5em; font-size: 9pt;">(c) 2008 - daniel burckhardt</div></div>';
    echo '</div><!-- .#holder -->';

    echo $this->buildHtmlEnd();
  }

}
