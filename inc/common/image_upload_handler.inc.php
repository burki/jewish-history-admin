<?php
 /*
  *
  * img_upload_handler.inc.php
  *
  * Author  : Daniel Burckhardt, daniel.burckhardt@sur-gmbh.ch
  *
  * Version : 2019-01-31 dbu
  *
  * interfaces still not completely finalized,
  * but much better than just plain copy/paste
  *
  */

require_once LIB_PATH . 'img_upload.php';

class ImageUploadHandler
{
  var $item_id;
  var $type;

  function __construct ($item_id, $type) {
    $this->item_id = $item_id;
    $this->type = $type;
  }

  // helper function
  static function directory ($item_id, $type, $full_path = false) {
    global $UPLOAD_TRANSLATE;

    $folder = ($full_path ? UPLOAD_FILEROOT : '')
            . $UPLOAD_TRANSLATE[$type]
            . sprintf('.%03d/id%05d/', intval($item_id / 32768), intval($item_id % 32768));

    return $folder;
  }

  private static function parentDirectory ($dir) {
    return dirname($dir);
  }

  // recursively create the directory
  static function checkDirectory ($dir, $create = true, $mode = 0755) {
    if (file_exists($dir)) {
      return filetype($dir) == 'dir';  // check if it is a directory
    }

    if (!$create) {
      // doesn't exist, don't want to create
      return false;
    }

    // recursively try to create that path
    $parent_dir = self::parentDirectory($dir);
    if (isset($parent_dir) && self::checkDirectory($parent_dir, $create, $mode)) {
      return mkdir($dir, $mode);
    }

    return false;
  }

  // methods
  function delete ($img_name) {
    global $UPLOAD_TRANSLATE;

    $dbconn = new DB;
    $querystr = sprintf("SELECT id, name, ord FROM Media WHERE item_id=%d AND type=%d AND name='%s'", $this->item_id, $this->type, $dbconn->escape_string($img_name));
    $dbconn->query($querystr);
    if ($dbconn->next_record()) {
      $media_id = $dbconn->Record['id'];
      $name = $dbconn->Record['name'];
      if (preg_match('/^(.*?)(\d+)$/', $name, $matches)) {
        $ord_orig = intval($matches[2]);
        $basename = $matches[1];
        // update if there are multiple images
        $querystr = sprintf("SELECT id, name, ord FROM Media WHERE item_id=%d AND type=%d",
                            $this->item_id, $this->type)
                  . " AND name LIKE '" . addslashes($basename) . "%' ORDER BY name";
        $dbconn->query($querystr);
        while ($dbconn->next_record()) {
          if (!preg_match('/(\d+)$/', $dbconn->Record['name'], $matches)) {
            continue;
          }

          $ord = $matches[1];
          if ($ord_orig <= intval($ord)) {
            // rename the entry
            $folder = UPLOAD_FILEROOT . $UPLOAD_TRANSLATE[$this->type]
                    . sprintf('.%03d/id%05d/',
                              intval($this->item_id / 32768), intval($this->item_id % 32768));
            // find matching files
            if ($dh = opendir($folder)) {
              while (($fname = readdir($dh)) !== false) {
                if (preg_match('/^' . $basename . $ord . '/', $fname)) {
                  if ($ord_orig == intval($ord)) {
                    unlink($folder . $fname);
                  }
                  else {
                    $fname_new = preg_replace("/^{$basename}$ord/",
                                              $basename . sprintf("%02d", intval($ord) - 1),
                                              $fname);
                    /* var_dump($fname);
                    var_dump($fname_new); */
                    rename($folder . $fname, $folder . $fname_new);
                  }
                }
              }
              closedir($dh);
            }
            // update the name of the Media
            $dbsub = new DB;
            $querystr = sprintf("UPDATE Media SET name='%s' WHERE id=%d",
                                $dbconn->escape_string($basename . sprintf("%02d", intval($ord) - 1)),
                                $dbconn->Record['id']);
            $dbsub->query($querystr);
          }
        }
      }
      // delete this item
      $querystr = "DELETE FROM Media WHERE id=$media_id";
      $dbconn->query($querystr);

      return $media_id;
    }
  }

  function buildImages ($img_name, $img_params, $max_images = -1,
                        $options = []) {
    global $UPLOAD_TRANSLATE;

    $images = [];

    $append_number = array_key_exists('append_number', $options)
      ? $options['append_number'] : $max_images != 1;
    $legacy = array_key_exists('legacy', $options)
      ? $options['legacy'] : false;
    $img_title = array_key_exists('title', $options)
        ? $options['title'] : null;


    if (isset($this->dbconn)) {
      $dbconn = & $this->dbconn;
    }
    else {
      $dbconn = new DB;
    }

    $querystr = sprintf("SELECT COUNT(*) AS num_imgs FROM Media WHERE item_id=%d AND type=%d", $this->item_id, $this->type)
                      . " AND name LIKE '" . addslashes($img_name) . "%'";
    $num_imgs = 0;
    $dbconn->query($querystr);
    if ($dbconn->next_record()) {
      $num_imgs = $dbconn->Record['num_imgs'];
    }

    for ($i = 0; $i <= $num_imgs && ($max_images <= 0 || $i < $max_images); $i++) {
      $images[$img_name . sprintf("%02d", $i)] = [
        'title' => sprintf('%s %d', tr(!empty($img_title) ? $img_title : 'Image'), $i + 1),
        'ord' => $i,
        'imgparams' => $img_params,
      ];
    }

    return $images;
  }

  function instantiateUploadRecord ($dbconn) {
    // img-upload
    $img_record = new RecordSQL([ 'tables' => 'Media', 'dbconn' => $dbconn ]);
    $img_record->add_fields([
      new Field([ 'name' => 'id', 'type' => 'hidden', 'datatype' => 'int', 'primarykey' => true ]),
      new Field([ 'name' => 'item_id', 'type' => 'hidden', 'datatype' => 'int', 'null' => true ]),
      new Field([ 'name' => 'type', 'type' => 'hidden', 'datatype' => 'int', 'value' => $this->type ]),
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

    return $img_record;
  }

  function instantiateImage ($params) {
    return new Image($params);
  }

  function instantiateImageUpload ($params) {
    return new ImageUpload($params);
  }

  function buildImgFolder () {
    global $UPLOAD_TRANSLATE;

    return sprintf($UPLOAD_TRANSLATE[$this->type] . '.%03d/id%05d/',
                   intval($this->item_id / 32768), intval($this->item_id % 32768));
  }

  function buildUpload (&$images, $action) {
    if (isset($this->dbconn)) {
      $dbconn = & $this->dbconn;
    }
    else {
      $dbconn = new DB;
    }

    $id = $this->item_id;
    foreach ($images as $key => $value) {
      $this->img_titles[$key] = $images[$key]['title'];
      $img_form = $this->instantiateUploadRecord($dbconn);
      // $img_form->set_value('ord', $images[$key]['ord']);
      $this->img_forms[$key] = new FormHTML([], $img_form);
      $this->img_images[] = $this->instantiateImage(array_merge(['name' => $key], $images[$key]['imgparams']));
    }
    $folder = $this->buildImgFolder();

    $img_upload = $this->instantiateImageUpload([
      'action' => $action,
      'upload_fileroot' => UPLOAD_FILEROOT . $folder,
      'upload_urlroot'  => UPLOAD_URLROOT . $folder,
      'imagemagick'     => defined('UPLOAD_PATH2MAGICK') ? UPLOAD_PATH2MAGICK : null,
      'max_file_size'   => UPLOAD_MAX_FILE_SIZE,
    ]);

    $img_upload->add_images($this->img_images);
    // var_dump($img_upload);

    return $img_upload;
  }

  function storeImgData ($img, $img_form, $img_name, $img_original_name = null) {
    $imgdata = isset($img) ? $img->find_imgdata() : [];
    if (!is_null($imgdata) && count($imgdata) > 0) {
      if (isset($this->dbconn)) {
        $dbconn = & $this->dbconn;
      }
      else {
        $dbconn = new DB;
      }

      // we have an image
      $values = [
        'name' => $img_name,
        'width' => isset($imgdata[0]['width']) ? $imgdata[0]['width'] : -1,
        'height' => isset($imgdata[0]['height']) ? $imgdata[0]['height'] : -1,
        'mimetype' => $imgdata[0]['mime'],
      ];

      if (!is_null($img_original_name)) {
        $values['original_name'] = $img_original_name;
      }

      $img_form->set_values($values);

      // find out if we already have an item
      $querystr = sprintf("SELECT id, original_name FROM Media"
                          . " WHERE item_id=%d AND type=%d AND name='%s'"
                          . " ORDER BY ord DESC LIMIT 1",
                          $this->item_id, $this->type, addslashes($img_name));
      $dbconn->query($querystr);
      if ($dbconn->next_record()) {
        $img_form->set_value('id', $dbconn->Record['id']);
        if (is_null($img_original_name) && !is_null($dbconn->Record['original_name'])) {
          $img_form->set_value('original_name', $dbconn->Record['original_name']);
        }
      }

      $img_form->set_values([ 'item_id' => $this->item_id, 'ord' => 0 ]);
      $img_form->store();
    }
  }

  function process (&$img_upload, &$images) {
    $upload_results = [];
    $img_names = array_keys($this->img_forms);

    for ($i = 0; $i < count($img_names); $i++) {
      $img_name = $img_names[$i];
      if (!isset($images[$img_name])) {
        // dbu 2007-09-02 not sure if correct
        continue;
      }

      $img_form = & $this->img_forms[$img_name];
      $img_form->set_values($_POST, ['prepend' => $img_name . '_']);

      if ($img_form->validate()) {
        $res = $img_upload->process(array_merge(['img_name' => $img_name], $images[$img_name]['imgparams']));
        $upload_results[$img_name] = array_key_exists($img_name, $res) ? $res[$img_name] : [];

        $img = $img_upload->image($img_name);
        if (isset($images[$img_name]['imgparams']['pdf']) && $images[$img_name]['imgparams']['pdf']) {
          $img->extensions['application/pdf'] = '.pdf';
        }

        if (isset($images[$img_name]['imgparams']['audio']) && $images[$img_name]['imgparams']['audio']) {
          $img->extensions['audio/mpeg'] = '.mp3';
        }
        if (isset($images[$img_name]['imgparams']['video']) && $images[$img_name]['imgparams']['video']) {
          $img->extensions['video/mp4'] = '.mp4';
        }

        if (isset($images[$img_name]['imgparams']['office']) && $images[$img_name]['imgparams']['office']) {
          $img->extensions['text/rtf'] = '.rtf';
          $img->extensions['application/vnd.oasis.opendocument.text'] = '.odt';
          $img->extensions['application/msword'] = '.doc';
          $img->extensions['application/vnd.openxmlformats-officedocument.wordprocessingml.document'] = '.docx';
        }

        if (isset($images[$img_name]['imgparams']['xml']) && $images[$img_name]['imgparams']['xml']) {
          $img->extensions['application/xml'] = '.xml';
        }

        $this->storeImgData($img, $img_form, $img_name, array_key_exists('original_name', $upload_results[$img_name])
                            ? $upload_results[$img_name]['original_name'] : null);
      }
      else {
        $invalid = $img_form->invalid();
        // var_dump($invalid);
      }
    }  // for

    return $upload_results;
  }

  function fetchAll () {
    foreach ($this->img_titles as $img_name => $title) {
      $img_form = & $this->img_forms[$img_name];
      $ord = $img_form->get_value('ord');
      $img_form->fetch([
        'where' => sprintf("item_id=%d AND type=%d AND name='%s'",
                           $this->item_id, $this->type, addslashes($img_name))
          . (isset($ord) ? " AND ord=$ord" : ''),
      ]);
    }
  }
}
