<?php
/*
 * pagedisplay.inc.php
 *
 * Abstract Base Display class
 *
 * (c) 2007-2015 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2015-11-04 dbu
 *
 * Changes:
 *
 */
require_once LIB_PATH . 'CmsCode.php';

class PageDisplayBase
{
  var $page;
  var $charset;
  var $script_url = array();
  var $script_code = '';
  var $style = '';
  var $image_wrap_div_class = '';
  var $image_caption_class = 'caption';
  var $image_caption_setwidth = FALSE;
  var $span_range = NULL; // '[\x{3400}-\x{9faf}]';
  var $span_class = ''; // 'cn';

  function __construct (&$page) {
    $this->page = $page;
  }

  function buildCountryOptions ($prepend_featured = FALSE) {
    require_once INC_PATH . 'common/classes.inc.php';

    $countries = Countries::getAll($this->page->lang());

    if (!isset($GLOBALS['COUNTRIES_FEATURED']) || !$prepend_featured) {
      return $countries;
    }

    for ($i = 0; $i < count($GLOBALS['COUNTRIES_FEATURED']); $i++) {
      $countries_ordered[$GLOBALS['COUNTRIES_FEATURED'][$i]] = $countries[$GLOBALS['COUNTRIES_FEATURED'][$i]];
    }

    // separator
    /*
    $line = chr(hexdec('E2')) . chr(hexdec(94)) . chr(hexdec(80));
    $countries_ordered[''] = $line;
    */
    $countries_ordered['&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;&#x2500;'] = FALSE; // separator
    foreach ($countries as $cc => $name) {
      if (!isset($countries_ordered[$cc])) {
        $countries_ordered[$cc] = $name;
      }
    }

    return $countries_ordered;
  }

  function getLanguages ($lang = 'en') {
    require_once INC_PATH . 'common/classes.inc.php';

    return Languages::getAll($lang);
  }

  function htmlSpecialchars ($txt) {
    $match = array('/&(?!\#\d+;)/s', '/</s', '/>/s', '/"/s');
    $replace = array('&amp;', '&lt;', '&gt;', '&quot;');
    return preg_replace($match, $replace, $txt, -1);
  }

  function placeImages ($matches) {
    $attrs = $matches[1];

    if (preg_match('/\bsrc\=\"([^\"]*)\"/', $attrs, $matches)) {
      $url = $matches[1];
      if (preg_match('/^http:\/\/(\d+)$/', $url, $matches)) {
        // we have to build a local image
        if (isset($this->image)) {
          $img_name = $this->image['name'].sprintf('%02d', $matches[1] - 1);
          // var_dump($img_name);
          list($tag, $caption, $copyright) =
            $this->buildImage($this->image['item_id'], $this->image['type'],
                              $img_name, TRUE, FALSE, TRUE);

          if (!isset($tag)) {
            return '';
          }

          if (isset($this->image_caption_setleft) || $this->image_caption_setwidth) {
            if (preg_match('/width="(\d+)"/', $tag, $matches)) {
              $img_width = $matches[1];
            }
            else {
              $img_width = 400;
            }

            if (isset($this->image_caption_setleft)) {
              $left = $img_width + $this->image_caption_setleft;
            }
          }

          $style = '';
          if (preg_match('/\bstyle\=\"([^\"]*)\"/', $attrs, $matches)) {
            $style = ' '.$matches[0];
          }

          $ret = $tag;

          // build the caption
          if ($this->image_caption_class !== FALSE && (!empty($caption) || !empty($copyright))) {
            $caption_style = '';
            if (isset($this->image_caption_setleft)) {
              $caption_style .= 'left:' . $left . 'px;';
            }
            if ($this->image_caption_setwidth) {
              $caption_style .= 'width:' . $img_width . 'px;';
            }

            $ret .= '<div class="' . $this->image_caption_class . '"'
                  . (!empty($caption_style) ? ' style="' . $caption_style . '"' : '')
                  . '>'
                  . (!empty($caption) ? $this->formatParagraphs($caption) : '')
                  . (!empty($copyright) ? $this->formatText($copyright) : '')
                  . '</div>';
          }

          if (!empty($this->image_wrap_div_class) || !empty($style)) {
            $class = !empty($this->image_wrap_div_class)
              ? ' class="'.$this->image_wrap_div_class.'"' : '';
            if (preg_match('/float\:\s*(left|right)/', $style, $matches)) {
              $padding = 'padding-' . ('left' == $matches[1] ? 'right' : 'left')
                       . ': 10px';
              $style = preg_replace("/;?(\"|')$/", '; ' . $padding . ';\1', $style);
            }

            $ret = '<div' . $class . $style . '>' . $ret . '</div>';
          }

          return $ret;
        }
      }
    }
    return '<img '. $attrs . ' />';
  }

  function adjustCharacters ($txt) {
    $match = array('/\-\-/s'); // , '/—/s', '/’/s', '/[“”]/s', '/&amp;(\#\d+;)/s');
    $replace = array('&#8212;'); // , '&#8212;', "'", '"', '&\1');
    $ret = preg_replace($match, $replace, $txt, -1);

    $ret = preg_replace_callback('/<img\s*([^>]*)\/?>/s',
                                array(&$this, 'placeImages'), $ret);

    if (isset($this->span_range)) {
      $ret = preg_replace('/('.$this->span_range.'+)/us',
                          '<span class="'.$this->span_class.'">\1</span>', $ret);
    }

    return $ret;
  }

  function buildWikilink ($options) {
    if (empty($options['page'])) {
      $url = $options['anchor'];
    }
    else {
      $url = sprintf($this->page->BASE_URL . '?pn=info&id=%d%s',
                     $options['page'],
                     '#' != $options['anchor'] ? $options['anchor'] : '');
    }

    return sprintf('<a href="%s">%s</a>',
                   $url, $this->formatText($options['text']));
  }

  function instantiateEncoder ($paragraph_mode = TRUE) {
    $encoder = @Text_Wiki_CmsCode::factory('CmsCode');
    $encoder->setFormatConf('Xhtml', 'translate', HTML_SPECIALCHARS); // default HTML_ENTITIES messes up &Zcaron
    if ('utf-8' == $this->charset) {
      $encoder->setFormatConf('Xhtml', 'charset', 'UTF-8');
    }

    $encoder->addPath('render', $encoder->fixPath(LIB_PATH . 'CmsCode'));

    $encoder->setRenderConf('Xhtml', 'Wikilink',
                            array('render_callback' => array(&$this, 'buildWikilink')));

    if ($paragraph_mode) {
      $encoder->enableRule('Newline');
      $encoder->enableRule('Paragraph');
    }
    else {
      $encoder->disableRule('Newline');
      $encoder->disableRule('Paragraph');
    }
    return $encoder;
  }

  function formatText ($txt) {
    $encoder = $this->instantiateEncoder(FALSE);

    return preg_replace('/\n/', '<br />', trim($this->adjustCharacters(@$encoder->transform($txt))));
  }

  function convertToPlain ($txt) {
    $encoder = @Text_Wiki_CmsCode::factory('CmsCode');
    if ('utf-8' == $this->charset) {
      $encoder->setFormatConf('Plain', 'charset', 'UTF-8');
    }
    $encoder->addPath('render', $encoder->fixPath(LIB_PATH.'CmsCode'));

    return $encoder->transform($txt, 'Plain');
  }

  function formatParagraphs ($txt, $options = '') {
    // $txt = $this->htmlSpecialchars ($txt);
    $encoder = $this->instantiateEncoder(TRUE);

    $class = ''; $enable_list = FALSE;
    switch (gettype($options)) {
      case 'array':
          if (array_key_exists('class', $options))
            $class = $options['class'];
          if (array_key_exists('list', $options)) {
            $enable_list = $options['list'];
          }
          break;
      case 'string':
          $class = $options;
          break;
    }

    if ($enable_list) {
      $encoder->enableRule('List');
    }
    else {
      $encoder->disableRule('List');
    }

    if (!empty($class)) {
      $encoder->setRenderConf('Xhtml', 'Paragraph', 'css', $class);
    }

    // transform the wiki text.
    return $this->adjustCharacters($encoder->transform($txt, 'Xhtml'));
  }

  function formatTimestamp ($when, $format = 'm/d/Y') {
    return date($format, $when);
  }

  function getDateFromDb ($db_datestr) {
    $ret = new Zend_Date($db_datestr /* . '+00:00' */, // Database set to local time
                         Zend_Date::ISO_8601);
    return $ret;
  }

  function formatDate ($date) {
    if (is_object($date) && $date instanceof Zend_Date) {
      return $date->toString(Zend_Locale_Format::getDateFormat(Zend_Registry::get('Zend_Locale')), Zend_Registry::get('Zend_Locale'));
    }
    return date('Y.m.d', $date);
  }

  function formatDateTime ($datetime) {
    if (is_object($datetime) && $datetime instanceof Zend_Date) {
      return $datetime->toString(Zend_Registry::get('Zend_Locale'));
    }
    return date('Y.m.d H:i', $datetime);
  }

  static function validateEmail ($email) {
    if (function_exists('_MailValidate'))
       return 0 == _MailValidate($email, 1);
    else
      return preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-
]+)*(\.[a-z]{2,4})$/', $email);
  }

  function addMailto ($email) {
    return self::validateEmail($email)
      ? '<a href="mailto:'.$email.'">'.$email.'</a>' : $email;
  }

  function linkEmails ($txt) {
    return preg_replace('/([_a-z0-9-][_a-z0-9-\.]*\@[_a-z0-9-\.]*[_a-z0-9-])/se',
                        '$this->addMailto'."('\\1')", $txt, -1);
  }

  function buildImgFname ($item_id, $type, $name, $mime) {
    global $MEDIA_EXTENSIONS, $UPLOAD_TRANSLATE;

    $folder = UPLOAD_FILEROOT.$UPLOAD_TRANSLATE[$type]
              .sprintf(".%03d/id%05d/",
                       intval($item_id / 32768), intval($item_id % 32768));

    return $folder.$name.$MEDIA_EXTENSIONS[$mime];
  }

  function buildImgUrl ($item_id, $type, $name, $mime, $append_uid = FALSE) {
    global $MEDIA_EXTENSIONS, $UPLOAD_TRANSLATE;
    static $uid;
    if (empty($uid))
      $uid = uniqid();

    $folder = UPLOAD_URLROOT.$UPLOAD_TRANSLATE[$type]
            . sprintf(".%03d/id%05d/",
                      intval($item_id / 32768), intval($item_id % 32768));

    return $folder . $name . $MEDIA_EXTENSIONS[$mime]
         . ($append_uid ? '?uid=' . $uid : '');
  }


  function buildImgTag ($relurl, $attrs = '') {
    // global $IMG_ENLARGE_ADDWIDTH, $IMG_ENLARGE_ADDHEIGHT;
    $IMG_ENLARGE_ADDWIDTH = $IMG_ENLARGE_ADDHEIGHT = 200;

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
      $fname = ereg('^' . UPLOAD_URLROOT, $relurl)
        ? ereg_replace('^' . UPLOAD_URLROOT, UPLOAD_FILEROOT, $relurl)
        : ereg_replace('^' . BASE_PATH, './', $relurl);
      $fname = preg_replace('/\?.*/', '', $fname);
      // var_dump($fname);
      $size = @getimagesize($fname);
      if (isset($size)) {
        if (!isset($attrs['width']) && !isset($attrs['height'])) {
          $attrs['width'] = $size[0]; $attrs['height'] = $size[1];
        }
        $fname_large = preg_replace('/(\_small)?\.([^\.]+)$/', '_large.\2', $fname);
        if (isset($attrs['enlarge']) && $attrs['enlarge'] !== FALSE) {
          // var_dump($fname_large);

          if (file_exists($fname_large)) {
            $size_large = @getimagesize($fname_large);
            $url_enlarge = "window.open('" . BASE_PATH . "img.php?url=" . urlencode($relurl)
                         . "&width=".$size_large[0]."&height=".$size_large[1]
                         . "&caption=".urlencode($attrs['enlarge_caption'])
                         . "', '_blank', 'width=".($size_large[0] + $IMG_ENLARGE_ADDWIDTH)
                         . ",height=".($size_large[1] + $IMG_ENLARGE_ADDHEIGHT).",resizable=yes');";
            $url_enlarge .= 'return false;';
            $attrs['alt'] = 'Click to enlarge';
          }
          else if (isset($attrs['enlarge_only'])) {
            $url_enlarge = "window.open('" . BASE_PATH . "img.php?url=" . urlencode($relurl)
                         . "&large=0&width=".$size[0]."&height=".$size[1]
                         . "&caption=".urlencode($attrs['enlarge_caption'])
                         . "', '_blank', 'width=".($size[0] + $IMG_ENLARGE_ADDWIDTH)
                         . ",height=".($size[1] + $IMG_ENLARGE_ADDHEIGHT).",resizable=yes');";
            $url_enlarge .= 'return false;';
          }
        }
      }
    }

    $attrstr = '';
    foreach ($attrs as $attr => $value) {
      if ($attr != 'enlarge' && $attr != 'enlarge_only' && $attr != 'enlarge_caption' && $attr != 'anchor') {
        $attrstr .= ($attr . '="' . $value . '" ');
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
      $img_tag = '<a href="#' . (isset($attrs['anchor']) ? $attrs['anchor'] : '')
               . '" onclick="' . $url_enlarge . '"'
               . (isset($attrs['anchor']) ? ' name="' . $attrs['anchor'] . '"' : '')
               .'>'
               . $img_tag
               . '</a>';
    }
    else if (isset($attrs['anchor'])) {
      $img_tag = '<a name="' . $attrs['anchor'] . '">' . $img_tag . '</a>';
    }
    return $img_tag;
  }

  function fetchImage (&$dbconn, $item_id, $type, $img_name) {
    $querystr =
      sprintf("SELECT item_id, caption, copyright, width, height, mimetype"
              . " FROM Media WHERE item_id=%d AND type=%d AND name='%s'",
              $item_id, $type, $dbconn->escape_string($img_name));

    $dbconn->query($querystr);
    if ($dbconn->next_record()) {
      return $dbconn->Record;
    }
  }

  function buildImage($item_id, $type, $img_name,
                      $enlarge = FALSE, $append_uid = FALSE, $return_caption = FALSE, $alt = NULL) {
    $dbconn = new DB;

    $img = $this->fetchImage($dbconn, $item_id, $type, $img_name);
    if (isset($img)) {
      $caption = $img['caption'];
      $copyright = $img['copyright'];

      $params = array('width' => $img['width'], 'height' => $img['height'],
                      'enlarge' => $enlarge, 'enlarge_caption' => $this->formatText($caption),
                      'border' => 0);
      if (NULL !== $alt) {
        $params['alt'] = $params['title'] = $alt;
      }

      $img_tag = $this->buildImgTag($this->buildImgUrl($item_id, $type,
                                                       $img_name, $img['mimetype'], $append_uid),
                                    $params);

      if ($return_caption) {
        return array($img_tag, $caption, $copyright);
      }

      return $img_tag;
    }
  }

  function scaleUploadImage ($fname_rel, $name_append, $geometry) {
    $fname_full = UPLOAD_FILEROOT . $fname_rel;

    if (file_exists($fname_full)) {
      // check mimetype to determine how to render
      $unknown = TRUE;
      list($width, $height, $type, $attr) = @getimagesize($fname_full);
      if (isset($type)) {
        global $MEDIA_EXTENSIONS;

        $mime_type = image_type_to_mime_type($type);

        switch ($mime_type) {
            case 'image/gif':
            case 'image/png':
            case 'image/jpg':
                // split
                $path_parts = pathinfo($fname_rel);
                $fname_scaled = sprintf('%s/%s_%s%s',
                                        $path_parts['dirname'],
                                        $path_parts['filename'],
                                        $name_append,
                                        $MEDIA_EXTENSIONS[$mime_type]);
                $fname_scaled_full = UPLOAD_FILEROOT . $fname_scaled;
                $scale = TRUE;
                if (file_exists($fname_scaled_full)) {
                  // if scaled file exists, we rescale only if large file was modified after scaled one
                  $scale = filemtime($fname_full) >= filemtime($fname_scaled_full);
                }

                if ($scale) {
                  $cmd = UPLOAD_PATH2MAGICK . 'convert '
                    . '-geometry ' . escapeshellarg($geometry) . ' '
                    . ('image/jpeg' == $mime_type ? '+profile "*" -colorspace RGB ' : '')
                    . escapeshellarg($fname_full)
                    . ' '
                    . escapeshellarg($fname_scaled_full);
                  // echo($cmd);
                  $ret = exec($cmd, $lines, $retval);
                }

                if (file_exists($fname_scaled_full)) {
                  list($width, $height, $type, $attr) = @getimagesize($fname_scaled_full);

                  return array($fname_scaled, $width, $height);
                }
          }
      }
    }
  }

  function buildHtmlLinkTags () {
    $tags = array(); // css, rss
    if (!empty($this->stylesheet)) {
      // link to stylesheet
      if (!is_array($this->stylesheet)) {
        $this->stylesheet = array($this->stylesheet);
      }

      foreach ($this->stylesheet as $src) {
        $tags[] =
          sprintf('<link rel="stylesheet" href="%s" type="text/css" />',
                  htmlspecialchars($this->page->BASE_PATH . $src));
      }
    }
    return implode("\n", $tags);
  }

  function buildHtmlStartTag ($lang_attr = '') {
    return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
      . '<html xmlns="http://www.w3.org/1999/xhtml"' . $lang_attr . '>'
      . "\n";

  }

  function buildHtmlStart () {
    // javascript
    $scriptcode = '';
    foreach($this->script_url as $url) {
      $scriptcode .= sprintf('<script language="JavaScript" type="text/javascript" src="%s"></script>'."\n",
                             htmlspecialchars($this->page->BASE_PATH . $url));
    }

    if (!empty($this->script_url_ie)) {
      foreach($this->script_url_ie as $url_ie)
        $scriptcode .= sprintf('<!--[if lt IE 7]>'
                               . '<script defer type="text/javascript" src="%s"></script>'
                               . '<![endif]-->' . "\n",
                               htmlspecialchars($this->page->BASE_PATH . $url_ie));
    }

    if (!empty($this->script_ready)) {
      $scriptcode .= '<script language="JavaScript" type="text/javascript">'
                   . 'jQuery(document).ready(function(){ ';
      foreach ($this->script_ready as $ready) {
        $scriptcode .= $ready . "\n" ;
      }
      $scriptcode .= '}); </script>';
    }

    if (!empty($this->script_code)) {
      $scriptcode .= <<<EOT
  <script language="JavaScript" type="text/javascript">
<!--
$this->script_code
  // -->
  </script>
EOT;
    }

    $link_tags = $this->buildHtmlLinkTags();

    $stylecode = '';
    if (!empty($this->style)) {
      $stylecode .= <<<EOT
  <style type="text/css">
$this->style
  </style>
EOT;
    }

    $title = $this->htmlSpecialChars($this->page->title());
    $charset = !empty($this->charset) ? $this->charset : 'iso-8859-15';
    $lang = $this->page->lang();
    $lang_attr = !empty($lang)
      ? sprintf(' lang="%s" xml:lang="%s"', $lang, $lang) : '';

    $html_start = $this->buildHtmlStartTag($lang_attr);

    return <<<EOT
$html_start
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=$charset" />
    <title>$title</title>
    $link_tags
    $scriptcode
    $stylecode
  </head>

EOT;
  }

  function buildBodyStart () {
    $attributes = isset($this->script_onload)
      ? ' onload="'.htmlspecialchars($this->script_onload).'"' : '';

    return <<<EOT
<body$attributes>
EOT;
  }

  function buildBodyEnd () {
    $ret = <<<EOT
</body>

EOT;
    return $ret;
  }

  function buildHtmlEnd () {
return <<<EOT
</html>
EOT;
  }

  function setOutputCompression () {
    static $compress_set = FALSE;  // only send the header once

    if (headers_sent() || $compress_set) {
      return $compress_set;
    }

    // Check if the browser supports gzip encoding, HTTP_ACCEPT_ENCODING
    if (array_key_exists('HTTP_ACCEPT_ENCODING', $_SERVER)
        && strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip'))
    {
      // Start output buffering
      ob_start('ob_gzhandler', 4096);

      // Tell the browser the content is compressed with gzip
      header("Content-Encoding: gzip");
      return $compress_set = TRUE;
    }

    return FALSE;
  }

  function setHttpHeaders () {
    if (headers_sent()) {
      return;
    }
    if (defined('OUTPUT_COMPRESS') && OUTPUT_COMPRESS) {
      $this->setOutputCompression();
    }
  }

  function show () {
    // must come first in case buildContent triggers warnings or has debug output
    // which isn't correctly detected by headers_sent on the production machine
    $this->setHttpHeaders();

    $content = $this->buildContent(); // this one may redirect

    echo $this->buildHtmlStart();
    echo $this->buildBodyStart();

    echo $this->buildMenu();

    $this->showContent($content);

    echo $this->buildBodyEnd();
    echo $this->buildHtmlEnd();
  }

}
