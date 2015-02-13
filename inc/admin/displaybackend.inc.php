<?php
/*
 * displabackend.inc.php
 *
 * Base-Class for backend
 *
 * (c) 2007-2015 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2015-02-13 dbu
 *
 * Changes:
 *
 */

require_once INC_PATH . 'common/tablemanager.inc.php';
require_once INC_PATH . 'common/image_upload_handler.inc.php';

// small helper function
function array_merge_at ($array1, $array2, $after_field = NULL) {
  if (empty($after_field)) {
    return array_merge($array1, $array2);
  }
  $ret = array();
  foreach ($array1 as $key => $val) {
    if ('integer' == gettype($key)) {
      $ret[] = $val; // renumber numeric indices
    }
    else {
      $ret[$key] = $val;
    }
    if (isset($after_field) && $key == $after_field) {
      //var_dump($after_field);
      $ret = array_merge($ret, $array2);
      unset($after_field);
      //var_dump($ret);
    }
  }
  return $ret;
}

class DisplayBackendFlow extends TableManagerFlow
{
  function __construct ($page) {
    parent::TableManagerFlow(TRUE); // view after edit
  }

  function init ($page) {
    $ret = parent::init($page);


    if (TABLEMANAGER_EDIT == $ret) {
      global $RIGHTS_EDITOR, $RIGHTS_ADMIN;
      if (empty($this->page->user)
          || 0 == ($this->page->user['privs'] & ($RIGHTS_ADMIN | $RIGHTS_EDITOR)))
      {
        // only view
        return isset($this->id) ? TABLEMANAGER_VIEW : TABLEMANAGER_LIST;
      }
    }

    return $ret;
  }

}


/* Common base class for the backend with paging and upload handling */
class DisplayBackend extends DisplayTable
{
  var $listing_default_action = TABLEMANAGER_EDIT;
  var $datetime_style = 'DD/MM/YYYY';

  function __construct (&$page, $workflow = '') {
    if (!is_object($workflow)) {
      $workflow = new DisplayBackendFlow($page);
    }
    parent::__construct($page, $workflow);
  }


  function checkAction ($step) {
    if ($this->page->isAdminUser()) {
      // admins have all rights
      return TRUE;
    }

    if (TABLEMANAGER_EDIT == $step) {
      // all editors and admins may edit by default (item overrides this)
      global $RIGHTS_EDITOR, $RIGHTS_ADMIN;

      if (!empty($this->page->user)
          && 0 != ($this->page->user['privs'] & ($RIGHTS_ADMIN | $RIGHTS_EDITOR)))
      {
        return TRUE;
      }
    }

    return FALSE;
  }

  function mayEdit ($row) {
    return $this->checkAction(TABLEMANAGER_EDIT);
  }

  function buildListingAddItem () {
    if (!$this->checkAction(TABLEMANAGER_EDIT)) {
      return '';
    }
    return parent::buildListingAddItem();
  }

  function buildEditButton () {
    if (!$this->checkAction(TABLEMANAGER_EDIT)) {
      return '';
    }

    return sprintf(' <span class="regular">[<a href="%s">%s</a>]</span>',
                   htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_EDIT) => $this->id))),
                   tr('edit'));
  }

  function buildSearchBar () {
    // var_dump($this->search);

    $ret = sprintf('<form action="%s" method="post" name="search">',
                   htmlspecialchars($this->page->buildLink(array('pn' => $this->page->name, 'page_id' => 0))));

    if (method_exists($this, 'buildSearchFields')) {
      $search = $this->buildSearchFields();
    }
    else {
        $search = sprintf('<input type="text" name="search" value="%s" size="40" /><input class="submit" type="submit" value="%s" />',
                          $this->htmlSpecialchars(array_key_exists('search', $this->search) ?  $this->search['search'] : ''),
                          tr('Search'));
    }

    $ret .= sprintf('<tr><td colspan="%d" nowrap="nowrap">%s</td></tr>',
                    $this->cols_listing_count,
                    $search);

    $ret .= '</form>';

    return $ret;
  } // buildSearchBar

  function getImageDescriptions () {
    // override for actual images
  }

  function instantiateUploadHandler () {
    list($media_type, $this->images) = $this->getImageDescriptions();

    if (isset($this->images)) {
      // check if we have to setup an image for body-rendering
      foreach ($this->images as $name => $descr) {
        if (isset($descr['placement']) && 'body' == $descr['placement']) {
          $this->image = array('message_id' => $this->workflow->primaryKey(),
                               'type' => $media_type, 'name' => $name);
        }
      }
      return new ImageUploadHandler($this->workflow->primaryKey(), $media_type);
    }
  }

  function processUpload (&$imageUploadHandler) {
    $action = $this->page->buildLink(array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $this->id));
    $upload_results = array();
    foreach ($this->images as $img_name => $img_descr) {
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
      if (array_key_exists('delete_img', $this->page->parameters)) {
        $imageUploadHandler->delete($this->page->parameters['delete_img']);
      }

      $images = $imageUploadHandler->buildImages($img_name, $img_params, $max_images);
      $imageUpload = $imageUploadHandler->buildUpload($images, $action);

      if ($imageUpload->submitted()) {
        $upload_results[$img_name] = $imageUploadHandler->process($imageUpload, $images);
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

  function renderUpload (&$imageUploadHandler, $title = 'File Upload') {
    $ret = '<h2>' . $this->formatText(tr($title)) . '</h2>';

    $params_self = array('pn' => $this->page->name, $this->workflow->name(TABLEMANAGER_VIEW) => $this->id);
    $action = $this->page->buildLink($params_self);

    $first = TRUE;

    foreach ($this->images as $img_name => $img_descr) {
      $rows = array();
      if (isset($img_descr['title'])) {
        $ret .= '<h3>' . $img_descr['title'] . '</h3>';
      }

      $upload_results = isset($this->upload_results[$img_name])
        ? $this->upload_results[$img_name]: array();
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

      $images = $imageUploadHandler->buildImages($img_name, $img_params, $max_images, $options);
      $imageUpload = $imageUploadHandler->buildUpload($images, $action);

      if ($first) {
        $ret .= $imageUpload->show_start();
        $first = FALSE;
      }

      $imageUploadHandler->fetchAll();

      $count = 0;
      foreach ($imageUploadHandler->img_titles as $img_name => $title) {
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
            $img_field .= '<div class="message">'
                        . $upload_results[$img_name]['msg']
                        . '</div>';
          }

          $url_delete = $this->page->buildLink(array_merge($params_self,
                                               array('delete_img' => $img_name)));

          list($img_tag, $caption, $copyright) = $this->buildImage($imageUploadHandler->item_id, $imageUploadHandler->type, $img_name, TRUE, TRUE, TRUE);
          // var_dump($img_tag);
          if (!empty($img_tag)) {
            $delete = '';
            if ($this->checkAction(TABLEMANAGER_EDIT)) {
              $delete = '[<a href="' . htmlspecialchars($url_delete) . '">' . tr('delete') . '</a>]';
            }

            $img_field .= '<p>'
                        . '<div style="margin-right: 2em; margin-bottom: 1em; float: left;">'
                        . $img_tag
                        . '</div>'
                        . (!empty($caption) ? $this->formatText($caption).'<br />&nbsp;<br />' : '')
                        . $delete
                        . '<br clear="left" />'
                        . '</p>';
          }

          $rows[] = $img_field;

          if ($this->checkAction(TABLEMANAGER_EDIT)) {
            $rows[] = array('File', $img->show_upload_field());
            $rows[] = array('Image Caption',
                            $this->getUploadFormField($img_form, 'caption', array('prepend' => $img_name . '_')));
            $rows[] = array('Copyright-Notice',
                            $this->getUploadFormField($img_form, 'copyright', array('prepend' => $img_name . '_')));

            $rows[] = array('', '<input type="submit" value="' . ucfirst(tr('upload')) . '" />');
          }
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

}
