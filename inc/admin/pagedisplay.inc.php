<?php
/*
 * pagedisplay.inc.php
 *
 * Base Display class for Admin-pages
 *
 * (c) 2006-2023 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2023-08-06 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/pagedisplay.inc.php';

class PageDisplay
extends PageDisplayBase
{
  var $page;
  var $charset = 'utf-8';
  var $invalid;
  var $is_internal = false;
  var $issue;
  var $location;
  var $stylesheet = [ 'admin.css' ];
  var $span_range = null; // '[\x{3400}-\x{9faf}]';
  var $span_class = ''; // 'cn';
  var $xls_data = [];
  var $images;
  var $upload_results;

  function __construct (&$page) {
    global $RIGHTS_REFEREE, $RIGHTS_EDITOR;

    $this->page = $page;
    $this->is_internal = !empty($this->page->user)
                       && 0 != ($this->page->user['privs'] & ($RIGHTS_REFEREE | $RIGHTS_EDITOR));
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
    return $this->buildContentLine($left, $right, [
      'class_left' => 'form',
      'class_right' => 'form',
    ]);
  }

  function buildContentLine($left, $right, $params = []) {
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

  function buildContentLineMultiple ($lines, $params = []) {
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
        if ($dbconn->next_record()) {
          $changed .= sprintf(' %s %s',
                              tr('by'),
                              $this->htmlSpecialChars($dbconn->Record['email']));
        }
      }
      $changed = '<p>' . $changed . '</p>';
    }

    return $changed;
  }

  function buildImgFname ($item_id, $type, $name, $mime) {
    global $MEDIA_EXTENSIONS, $UPLOAD_TRANSLATE;

    $folder = UPLOAD_FILEROOT
            . $UPLOAD_TRANSLATE[$type]
            . sprintf('.%03d/id%05d/',
                      intval($item_id / 32768), intval($item_id % 32768));

    return $folder . $name . $MEDIA_EXTENSIONS[$mime];
  }

  function buildImgUrl ($item_id, $type, $name, $mime, $append_uid = false) {
    global $MEDIA_EXTENSIONS, $UPLOAD_TRANSLATE;
    static $uid;

    if (empty($uid)) {
      $uid = uniqid();
    }

    $folder = UPLOAD_URLROOT
            . $UPLOAD_TRANSLATE[$type]
            . sprintf('.%03d/id%05d/',
                      intval($item_id / 32768), intval($item_id % 32768));

    return $folder . $name . $MEDIA_EXTENSIONS[$mime]
         . ($append_uid ? '?uid='.$uid : '');
  }


  function buildImgTag ($relurl, $attrs = '') {
    // global $IMG_ENLARGE_ADDWIDTH, $IMG_ENLARGE_ADDHEIGHT;
    $IMG_ENLARGE_ADDWIDTH = $IMG_ENLARGE_ADDHEIGHT = 4;

    $url_enlarge = '';

    if ($attrs == '') {
      $attrs = [];
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
        if (isset($attrs['enlarge']) && $attrs['enlarge'] !== false) {
          // var_dump($fname_large);

          if (file_exists($fname_large)) {
            $size_large = getimagesize($fname_large);
            $url_enlarge = "window.open('"
                         . BASE_PATH . "img.php?url=" . urlencode($relurl)
                         . "&width=" . $size_large[0] . "&height=" . $size_large[1]
                         . "&caption=" . urlencode(array_key_exists('enlarge_caption', $attrs) ? $attrs['enlarge_caption'] : '')
                         . "', '_blank', 'width=" . ($size_large[0] + $IMG_ENLARGE_ADDWIDTH) . ",height=" . ($size_large[1] + $IMG_ENLARGE_ADDHEIGHT) . ",resizable=yes');";
            $url_enlarge .= 'return false;';
            $attrs['alt'] = 'Click to enlarge';
          }
          else if (isset($attrs['enlarge_only'])) {
            $url_enlarge = "window.open('"
                         . BASE_PATH
                         . "img.php?url=" . urlencode($relurl)
                         . "&large=0&width=" . $size[0]
                         . "&height=" . $size[1]
                         . "&caption=" . urlencode($attrs['enlarge_caption'])
                         . "', '_blank', 'width=" . ($size[0] + $IMG_ENLARGE_ADDWIDTH) . ",height=" . ($size[1] + $IMG_ENLARGE_ADDHEIGHT) . ",resizable=yes');";
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
    else if (isset($relurl)) {
      $img_tag = '<img src="' . $relurl . '" ' . $attrstr . '/>';
    }

    if (isset($attrs['caption']) && !empty($attrs['caption'])) {
      $img_tag .= $attrs['caption'];
    }

    if (!empty($url_enlarge)) {
      $img_tag = '<a href="#'
               . (isset($attrs['anchor']) ? $attrs['anchor'] : '')
               . '" onclick="' . $url_enlarge . '"'
               . (isset($attrs['anchor']) ? ' name="' . $attrs['anchor'] . '"' : '') . '>'
               . $img_tag
               . '</a>';
    }
    else if (isset($attrs['anchor'])) {
      $img_tag = '<a name="' . $attrs['anchor'] . '">'
               . $img_tag
               . '</a>';
    }

    return $img_tag;
  }

  function fetchImage (&$dbconn, $item_id, $type, $img_name) {
    $querystr = sprintf("SELECT id AS media_id, item_id, caption, copyright, width, height, mimetype, original_name"
                        . " FROM Media WHERE item_id=%d AND type=%d AND name='%s'",
                        $item_id, $type, $dbconn->escape_string($img_name));

    $dbconn->query($querystr);
    if ($dbconn->next_record()) {
      return $dbconn->Record;
    }
  }

  function buildImage($item_id, $type, $img_name,
                      $enlarge = false, $append_uid = false, $return_caption = false, $alt = null)
  {
    $dbconn = new DB;

    $img = $this->fetchImage($dbconn, $item_id, $type, $img_name);
    if (isset($img)) {
      $caption = $img['caption'];

      $copyright = $img['copyright'];
      $img_url = $this->buildImgUrl($item_id, $type, $img_name, $img['mimetype'], $append_uid);

      if (in_array($img['mimetype'], [
            'text/rtf',
            'application/vnd.oasis.opendocument.text',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document']))
      {
        $img_tag = sprintf('<a href="%s" target="_blank">%s</a>%s',
                           htmlspecialchars($img_url),
                           $this->formatText(true || empty($caption) ? 'Office-Datei' : $caption),
                           !empty($img['original_name']) ? ' [' . $this->formatText($img['original_name']) . ']' : '');
      }
      else if (in_array($img['mimetype'], [ 'application/xml' ])) {
        $img_tag = sprintf('<a class="previewOverlayTrigger" href="%s" target="_blank">%s</a> ',
                           htmlspecialchars(BASE_PATH . "xml.php?media_id=" . $img['media_id']),
                           $this->formatText('HTML-Vorschau'));
        $img_tag .= sprintf(' <a href="%s">%s</a> ',
                           htmlspecialchars(BASE_PATH . "xml.php?format=docx&media_id=" . $img['media_id']),
                           $this->formatText('Word-Export'));
        $img_tag .= sprintf('<a href="%s" target="_blank">%s</a>%s',
                           htmlspecialchars($img_url),
                           $this->formatText(true || empty($caption) ? 'XML-Datei' : $caption),
                           !empty($img['original_name']) ? ' [' . $this->formatText($img['original_name']) . ']' : '');
      }
      else if (in_array($img['mimetype'], [
            'audio/mpeg',
          ]))
      {
        $img_tag = sprintf('<audio src="%s" preload="none" controls></audio>',
                           htmlspecialchars($img_url));
        $img_tag .= sprintf('<br /><a href="%s" target="_blank">%s</a>%s',
                           htmlspecialchars($img_url),
                           $this->formatText(true || empty($caption) ? 'Audio' : $caption),
                           !empty($img['original_name']) ? ' [' . $this->formatText($img['original_name']) . ']' : '');
      }
      else if (in_array($img['mimetype'], [
            'video/mp4',
          ]))
      {
        $img_tag = sprintf('<div style="max-width: 800px"><video style="width: 100%%; height: auto;" src="%s" preload="none" controls></video></div>',
                           htmlspecialchars($img_url));
        $img_tag .= sprintf('<br /><a href="%s" target="_blank">%s</a>%s',
                           htmlspecialchars($img_url),
                           $this->formatText(true || empty($caption) ? 'Video' : $caption),
                           !empty($img['original_name']) ? ' [' . $this->formatText($img['original_name']) . ']' : '');
      }
      else if ('application/pdf' == $img['mimetype']) {
        if (!$append_uid) {
          $img_tag = $this->buildPdfViewer($img_url, empty($caption) ? 'PDF' : $caption,
                                           ['thumbnail' => $this->buildThumbnailUrl($item_id, $type, $img_name, $img['mimetype'])]);
        }
        else {
          $img_tag = sprintf('<a href="%s" target="_blank">%s</a>%s',
                             htmlspecialchars($img_url),
                             $this->formatText(true || empty($caption) ? 'PDF' : $caption),
                             !empty($img['original_name']) ? ' [' . $this->formatText($img['original_name']) . ']' : '');
        }
      }
      else {
        $params = [
          'width' => $img['width'], 'height' => $img['height'],
          'enlarge' => $enlarge,
          'enlarge_caption' => $this->formatText($caption), 'border' => 0,
        ];

        if (null !== $alt) {
          $params['alt'] = $params['title'] = $alt;
        }

        $img_tag = $this->buildImgTag($img_url, $params);
      }

      if ($return_caption) {
        return [$img_tag, $caption, $copyright, $img['original_name']];
      }

      return $img_tag;
    }
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

  function instantiateUploadHandler ($className = 'ImageUploadHandler') {
    list($media_type, $this->images) = $this->getImageDescriptions();

    if (isset($this->images)) {
      // check if we have to setup an image for body-rendering
      foreach ($this->images as $name => $descr) {
        if (isset($descr['placement']) && 'body' == $descr['placement']) {
          $this->image = [
            'item_id' => $this->workflow->primaryKey(),
            'type' => $media_type,
            'name' => $name,
          ];
        }
      }

      return new $className($this->workflow->primaryKey(), $media_type);
    }
  }

  function processUpload (&$imageUploadHandler) {
    $action = $this->page->buildLink([ 'pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $this->id ]);
    $upload_results = [];
    foreach ($this->images as $img_basename => $img_descr) {
      $img_params = $img_descr['imgparams'];
      if (isset($img_descr['multiple'])) {
        if (is_bool($img_descr['multiple'])) {
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

      $options = [];
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
      if (isset($this->invalid[$name])) {
        $ret = '<div class="error">'
             . $this->form->error_fulltext($this->invalid[$name], $this->page->lang)
             . '</div>';
      }

      $ret .= $field->show($args);
    }

    return $ret;
  }

  function renderUpload ($imageUploadHandler, $title = 'Image Upload') {
    $ret = '<h2>' . $this->formatText(tr($title)) . '</h2>';

    $params_self = [ 'pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $this->id ];
    $action = $this->page->buildLink($params_self);

    $first = true;

    foreach ($this->images as $img_basename => $img_descr) {
      $rows = [];
      if (isset($img_descr['title'])) {
        $ret .= '<h3>' . $img_descr['title'] . '</h3>';
      }

      $upload_results = isset($this->upload_results[$img_basename])
        ? $this->upload_results[$img_basename]: [];
      // var_dump($upload_results);

      $max_images = 1;
      if (isset($img_descr['multiple'])) {
        if (is_bool($img_descr['multiple'])) {
          $max_images = $img_descr['multiple'] ? -1 : 1;
        }
        else {
          $max_images = intval($img_descr['multiple']);
        }
      }

      $img_params = $img_descr['imgparams'];

      $options = [];
      if (isset($img_params['title'])) {
        $options['title'] = $img_params['title'];
      }

      $images = $imageUploadHandler->buildImages($img_basename, $img_params, $max_images, $options);
      $imageUpload = $imageUploadHandler->buildUpload($images, $action);

      if ($first) {
        $ret .= $imageUpload->show_start();
        $first = false;
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
            if ($count > 0) {
              $rows[] = '<hr />';
            }
            $img_field .= '<h4>' . $title . '</h4>';
          }
          ++$count;

          $img_form = & $imageUploadHandler->img_forms[$img_name];
          if (isset($upload_results[$img_name]) && isset($upload_results[$img_name]['status']) && $upload_results[$img_name]['status'] < 0) {
            $img_field .= '<div class="message">' . $upload_results[$img_name]['msg'] . '</div>';
          }

          if (method_exists($imageUploadHandler, 'buildEntry')) {
            $img_field .= $imageUploadHandler->buildEntry($this, $img_name, $params_self);
          }
          else {
            list($img_tag, $caption, $copyright, $original_name) =
              $this->buildImage($imageUploadHandler->item_id, $imageUploadHandler->type, $img_name, true, true, true);
            // var_dump($img_tag);
            if (!empty($img_tag)) {
              $url_delete = $this->page->buildLink(array_merge($params_self,
                                                   [ 'delete_img' => $img_name ]));

              $img_field .= '<p><div style="margin-right: 2em; margin-bottom: 1em; float: left;">' . $img_tag . '</div>'
                          . sprintf('[<a href="%s">%s</a>]<br clear="left" />',
                                    htmlspecialchars($url_delete),
                                    $this->htmlSpecialchars(tr('delete')))
                          . '</p>'
                          . (!empty($caption) ? '<p>' . $this->formatText($caption) . '</p>' : '')
                          ;
            }
          }

          $rows[] = $img_field;

          $rows[] = [ 'File', $img->show_upload_field() ];
          $rows[] = [ 'Image Caption', $this->getUploadFormField($img_form, 'caption', [ 'prepend' => $img_name . '_' ]) ];
          $rows[] = [ 'Copyright-Notice', $this->getUploadFormField($img_form, 'copyright', [ 'prepend' => $img_name . '_' ]) ];

          $rows[] = [ '', '<input type="submit" value="' . ucfirst(tr('upload')) . '" />' ];
        } // if
      }

      foreach ($rows as $row) {
        if (is_array($row)) {
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

    $url_main = $this->page->buildLink(['pn' => '']); // '../';

    $ret = '<div id="header">';

    if (!empty($this->page->user)) {
      $ret .= '<div id="menuAccount">'
            . $this->formatText($this->page->user['login'])
            . sprintf(' | <a class="inverse" href="%s">%s</a>',
                      htmlspecialchars($this->page->buildLink(['pn' => 'account', 'edit' => $this->page->user['id']])),
                      $this->htmlSpecialchars(tr('My Account')))
            . sprintf(' | <a class="inverse" href="%s">%s</a>',
                      htmlspecialchars($this->page->buildLink(['pn' => '', 'do_logout' => 1])),
                      $this->htmlSpecialchars(tr('Sign out')))
            . '</div>'
            ;

      if (!$this->is_internal) {
        $this->page->site_description['structure']['root']['title'] = 'Home';
      }
    }

    if (count(Page::$languages) > 0) {
      $languages = [];
      foreach (Page::$languages as $lang => $label) {
        if ($lang != $this->page->lang()) {
          $label = sprintf('<a class="inverse" href="?lang=%s">%s</a>',
                           $lang, $this->formatText($label));
        }
        else {
          $label = $this->formatText($label);
        }

        $languages[] = $label;
      }

      $ret .= '<div id="languages">' . implode(' ', $languages) . '</div>';
    }

    $ret .= sprintf('<a href="%s"><img src="%s" style="margin-left: 4px; vertical-align: text-bottom; border: 0; height: 106px; width: auto" alt="%s" /><h1>%s</h1></a> ',
                    $url_main,
                    htmlspecialchars($this->page->BASE_PATH . SITE_LOGO),
                    $this->htmlSpecialchars(tr($SITE_DESCRIPTION['title'])),
                    $this->htmlSpecialchars(tr($SITE_DESCRIPTION['title'])));
    $entries = [];

    if (isset($this->page->path)) {
      foreach ($this->page->path as $entry) {
        $url = $this->page->buildLink(['pn' => $entry]);
        $entries[] = '<a class="inverse" href="' . $url . '">'
                   . $this->htmlSpecialchars($this->page->buildPageTitle($entry))
                   . '</a>';
      }
    }

    if (isset($this->step) && $this->step > 0) {
      $entries[] = tr($this->workflow->name($this->step));
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
       . $this->buildBodyStart([ 'role' => 'document' ])
       . "\n"
       . '<div id="holder">';

    if (!$this->page->embed) {
      echo $this->buildMenu();
    }

    echo '<div id="content">' . $content . "</div>\n";

    echo '</div><!-- .#holder -->' . "\n";

    echo $this->buildHtmlEnd();
  }
}
