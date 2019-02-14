<?php
 /*
  *
  * xml_upload_handler.inc.php
  *
  * Author  : Daniel Burckhardt, daniel.burckhardt@sur-gmbh.ch
  *
  * Version : 2019-02-14 dbu
  *
  *
  */

require_once INC_PATH . 'common/image_upload_handler.inc.php';

class XmlUploadHandler
extends ImageUploadHandler
{
  public function buildEntry($view, $img_name, $params_self)
  {
    require_once INC_PATH . 'common/PresentationService.php';
    $presentationService = new PresentationService(new DB_Presentation());

    $item_id = $this->item_id;
    $type = $this->type;
    $append_uid = true;

    $ret = '';

    $dbconn = new DB;

    $img = $view->fetchImage($dbconn, $item_id, $type, $img_name);
    if (isset($img)) {
      $caption = $img['caption'];

      $copyright = $img['copyright'];
      $img_url = $view->buildImgUrl($item_id, $type, $img_name, $img['mimetype'], $append_uid);

      if (in_array($img['mimetype'], [
            'text/rtf',
            'application/vnd.oasis.opendocument.text',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document']))
      {
        $img_tag = sprintf('<a href="%s" target="_blank">%s</a>%s',
                           htmlspecialchars($img_url),
                           $view->formatText(true || empty($caption) ? 'Office-Datei' : $caption),
                           !empty($img['original_name']) ? ' [' . $view->formatText($img['original_name']) . ']' : '');
      }
      else if (in_array($img['mimetype'], [ 'application/xml' ])) {
        $img_tag = sprintf('<a class="previewOverlayTrigger" href="%s" target="_blank">%s</a> ',
                           htmlspecialchars(BASE_PATH . 'xml.php?media_id=' . $img['media_id']),
                           $view->formatText('HTML-Vorschau'));
        $img_tag .= sprintf(' <a href="%s">%s</a> ',
                           htmlspecialchars(BASE_PATH . 'xml.php?format=docx&media_id=' . $img['media_id']),
                           $view->formatText('Word-Export'));
        $img_tag .= sprintf('<a href="%s" target="_blank">%s</a>%s',
                           htmlspecialchars($img_url),
                           $view->formatText(true || empty($caption) ? 'XML-Datei' : $caption),
                           !empty($img['original_name']) ? ' [' . $view->formatText($img['original_name']) . ']' : '');

        if (false !== $presentationService->lookupArticle($item_id, $img['original_name'])) {
          $img_tag .= sprintf('<br /><a class="previewOverlayTrigger" href="%s" target="_blank">%s</a> ',
                              htmlspecialchars($view->page->buildLink([
                                'pn' => 'presentation',
                                'view' => $img['media_id'],
                                'display' => 'embed',
                              ])),
                              $view->formatText(tr('Refresh Presentation')));
        }
      }
      else if (in_array($img['mimetype'], [
            'audio/mpeg',
          ]))
      {
        $img_tag = sprintf('<audio src="%s" preload="none" controls></audio>',
                           htmlspecialchars($img_url));
        $img_tag .= sprintf('<br /><a href="%s" target="_blank">%s</a>%s',
                           htmlspecialchars($img_url),
                           $view->formatText(true || empty($caption) ? 'Audio' : $caption),
                           !empty($img['original_name']) ? ' [' . $view->formatText($img['original_name']) . ']' : '');
      }
      else if (in_array($img['mimetype'], [
            'video/mp4',
          ]))
      {
        $img_tag = sprintf('<div style="max-width: 800px"><video style="width: 100%%; height: auto;" src="%s" preload="none" controls></video></div>',
                           htmlspecialchars($img_url));
        $img_tag .= sprintf('<br /><a href="%s" target="_blank">%s</a>%s',
                           htmlspecialchars($img_url),
                           $view->formatText(true || empty($caption) ? 'Video' : $caption),
                           !empty($img['original_name']) ? ' [' . $view->formatText($img['original_name']) . ']' : '');
      }
      else if ('application/pdf' == $img['mimetype']) {
        if (!$append_uid) {
          $img_tag = $view->buildPdfViewer($img_url, empty($caption) ? 'PDF' : $caption,
                                           ['thumbnail' => $view->buildThumbnailUrl($item_id, $type, $img_name, $img['mimetype'])]);
        }
        else {
          $img_tag = sprintf('<a href="%s" target="_blank">%s</a>%s',
                             htmlspecialchars($img_url),
                             $view->formatText(true || empty($caption) ? 'PDF' : $caption),
                             !empty($img['original_name']) ? ' [' . $view->formatText($img['original_name']) . ']' : '');
        }
      }
      else {
        $params = [
          'width' => $img['width'], 'height' => $img['height'],
          'enlarge' => $enlarge,
          'enlarge_caption' => $view->formatText($caption), 'border' => 0,
        ];
        if (null !== $alt) {
          $params['alt'] = $params['title'] = $alt;
        }

        $img_tag = $view->buildImgTag($img_url, $params);
      }

      if (!empty($img_tag)) {
        $url_delete = $view->page->buildLink(array_merge($params_self,
                                                         [ 'delete_img' => $img_name ]));

        $ret = '<p><div style="margin-right: 2em; margin-bottom: 1em; float: left;">' . $img_tag . '</div>'
                    . sprintf('[<a href="%s">%s</a>]<br clear="left" />',
                              htmlspecialchars($url_delete),
                              $view->htmlSpecialchars(tr('delete')))
                    . '</p>'
                    . (!empty($caption) ? '<p>' . $view->formatText($caption) . '</p>' : '')
                    ;
      }
    }

    return $ret;
  }
}
