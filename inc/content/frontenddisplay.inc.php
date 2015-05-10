<?php
/*
 * pagedisplay.inc.php
 *
 * Base Display class for Admin-pages
 *
 * (c) 2006-2015 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2015-03-13 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/pagedisplay.inc.php';

class FrontendDisplay extends PageDisplayBase
{
  var $page;
  var $charset = 'utf-8';
  var $invalid;
  var $is_internal = FALSE;
  var $issue;
  var $location;
  var $stylesheet = 'css/style.css';
  var $span_range = NULL; // '[\x{3400}-\x{9faf}]';
  var $span_class = ''; // 'cn';
  var $xls_data = array();

  function buildHtmlStartTag ($lang_attr = '') {
    return '<!DOCTYPE html>'
      . '<html' . $lang_attr . '>'
      . "\n";

  }

  function buildImgUrl ($item_id, $type, $name, $mime, $append_uid = FALSE) {
    global $MEDIA_EXTENSIONS, $UPLOAD_TRANSLATE;
    static $uid;

    if (empty($uid)) {
      $uid = uniqid();
    }

    $folder = UPLOAD_URLROOT . $UPLOAD_TRANSLATE[$type]
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
    $querystr = sprintf("SELECT item_id, caption, copyright, width, height, mimetype FROM Media WHERE item_id=%d AND type=%d AND name='%s'",
                        $item_id, $type, $dbconn->escape_string($img_name));

    $dbconn->query($querystr);
    if ($dbconn->next_record()) {
      return $dbconn->Record;
    }
  }

  function buildImage ($item_id, $type, $img_name,
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
      else if (in_array($img['mimetype'], array('application/xml'))) {
        $img_tag = sprintf('<a href="%s" target="_blank">%s</a>',
                           htmlspecialchars($img_url),
                           $this->formatText(empty($caption) ? 'XML-Datei' : $caption));
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
      else if (in_array($img['mimetype'], array(
                                                'video/mp4',
                                                )))
      {
        $img_tag = sprintf('<div style="max-width: 800px"><video style="width: 100%%; height: auto;" src="%s" preload="none" controls></video></div>',
                           htmlspecialchars($img_url));
        $img_tag .= sprintf('<br /><a href="%s" target="_blank">%s</a>',
                           htmlspecialchars($img_url),
                           $this->formatText(empty($caption) ? 'Video' : $caption));
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

  function buildMenu () {
    global $SITE_DESCRIPTION;

    $url_main = $this->page->buildLink(array('pn' => '')); // '../';

    $ret = '<div id="header">';

    if (false && !empty($this->page->user)) {
      $ret .= '<div id="menuAccount" style="font-size: 9pt; float: right">'
            . $this->formatText($this->page->user['login'])
            . ' | <a class="inverse" href="'
            . htmlspecialchars($this->page->buildLink(array('pn' => 'account', 'edit' => $this->page->user['id']))) . '">'
            . tr('My Account')
            . '</a>'
            . ' | <a class="inverse" href="'
            . htmlspecialchars($this->page->buildLink(array('pn' => '', 'do_logout' => 1)))
            . '">'
            . tr('Sign out')
            . '</a></div>';
    }

    $ret .= sprintf('<a href="%s"><img src="%s" style="margin-left: 4px; vertical-align: text-bottom; border: 0;" width="151" height="160" alt="%s" /></a> ',
                    htmlspecialchars('http://www.igdj-hh.de/'),
                    $this->page->BASE_PATH . 'media/logo_IGDJ.jpg',
                    $this->htmlSpecialchars(tr($SITE_DESCRIPTION['title'])));
    $entries = array();

    if (isset($this->page->path)) {
      foreach ($this->page->path as $entry) {
        $url = $this->page->buildLink(array('pn' => $entry));
        $entries[] = '<a class="inverse" href="' . htmlspecialchars($url) . '">'
                   . $this->htmlSpecialchars($this->page->buildPageTitle($entry))
                   . '</a>';

      }
    }

    $entries[] = '<a class="inverse" href="./admin.php">'
               . $this->htmlSpecialchars(tr('Redaktionsumgebung (für Herausgeber)'))
               . '</a>';


    if (false && count(Page::$languages) > 0) {
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
      $ret .= implode(' &nbsp &nbsp; ', $entries);
      $ret .= '</div>';
    }

    $ret .= '</div>'; // id="header"

    return $ret;
  }

  function show () {
    $content = $this->buildContent(); // this one may redirect

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
