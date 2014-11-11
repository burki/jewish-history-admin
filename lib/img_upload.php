<?php
 /*
  * img_upload.php
  *
  * Author  : Daniel Burckhardt, daniel.burckhardt@sur-gmbh.ch
  *
  * (c) 2000-2014
  *
  * Version : 2014-10-29 dbu
  *
  * Changes :
  *           2008-05-26 dbu silence warnings
  *           2007-01-31 dbu remove pre-PHP 4.2 code
  *           2006-12-08 dbu make it work without register_global
  *           2006-10-20 dbu strip ps7 info from jpegs that messes up IE
  *                           also in kept originals
  *           2005-06-15 dbu improved error handling, giving meaningful messages for files that are too large
  *           2004-12-16 dbu use move_uploaded_file() instead of copy() to cope with open_basedir restriction
  *           2004-12-04 dbu corrected nasty bug with scale: 'both'
  *           2004-09-23 dbu add -colorspace RGB to convert CMYK-images
  *           2004-08-20 dbu prelimenary hacks for new hetzler-server: parse 4M
  *           2004-07-22 dbu add +profile "*" to strip ps7 info from jpegs that messes up IE
  *           2003-06-05 dbu use -format to make identify more reliable
  *           2002-11-29 dbu automatic tif to jpeg conversion
  *           2001-07-21 dbu added scaling support through ImageMagick
  *           2001-03-27 dbu moved determine_size to independant method
  *           2001-03-10 dbu clearstatcache() after unlinking
  *           2001-03-08 dbu corrected error to prevent upscaling to max_width/max_height
  *           2001-02-27 dbu added 'mime' to status if successful
  *           2001-02-26 dbu corrected error-messages
  *           2000-09-13 dbu IE sends mime 'image/pjpeg' for jpeg-files
  *
  */

  class Image
  {
    var $extensions = array(
                            'image/gif' => '.gif', 'image/jpeg' => '.jpg', 'image/png' => '.png',
                            'application/pdf' => '.pdf',
                            );

    var $params;

    // constructor
    function __construct ($params) {
      $this->params = $params;
    }

    // accessor functions
    function name () {
      return $this->params['name'];
    }

    function set ($key, $value) {
      $this->params[$key] = $value;
    }

    function get ($key) {
      return isset($this->params[$key]) ? $this->params[$key] : NULL;
    }

    function determine_size ($fname, $width_physical = NULL, $height_physical = NULL) {
      if (!isset($width_physical) || !isset($width_physical)) {
        $size = @getimagesize($fname);
        if ($size !== FALSE) {
          $width_physical = $size[0];
          $height_physical = $size[1];
        }
      }
      if (!isset($height_physical)) {
        // TODO: make the code run anyway
        return;
      }

      $sizes = array();

      $sizes['width_physical'] = $width_physical;
      $sizes['height_physical'] = $height_physical;

      $width = $this->get('width');
      $height = $this->get('height');
      $max_width = $this->get('max_width');
      $max_height = $this->get('max_height');

      if (!isset($width) && !isset($height)
         && (isset($max_width) || isset($max_height)))
      {
        if (isset($max_width) && isset($max_height) && isset($sizes['height_physical'])) {
          if ($max_width / $max_height > $sizes['width_physical'] / $sizes['height_physical']) {
            unset($max_width);
          }
          else {
            unset($max_height);
          }
        }

        if (isset($max_width)) {
          $width = $sizes['width_physical'] > $max_width
            ? $max_width : $sizes['width_physical'];
        }
        else {
          $height = $sizes['height_physical'] > $max_height
            ? $max_height : $sizes['height_physical'];
        }
      }

      $sizes['scaled'] = FALSE;
      if (!isset($width) && !isset($height)) {
        // both not set
        $width  = $sizes['width_physical'];
        $height = $sizes['height_physical'];
      }
      else if (!isset($width)) {
        $width = $sizes['height_physical'] == 0
            ? $sizes['width_physical']
            : intval($sizes['width_physical'] * ($height / $sizes['height_physical']));
        $sizes['scaled'] = $width != $sizes['width_physical'];
      }
      else if (!isset($height)) {
        $height = $sizes['width_physical'] == 0
            ? $sizes['height_physical']
            : intval($sizes['height_physical'] * ($width / $sizes['width_physical']));
        $sizes['scaled'] = $height != $sizes['height_physical'];
      }
      else {
        $sizes['scaled']
          = $sizes['width_physical'] != $width || $sizes['height_physical'] != $height;
      }

      $sizes['width'] = $width; $sizes['height'] = $height;

      return $sizes;
    }

    function find_imgdata () {
      $upload_fileroot = $this->params['upload_fileroot'];
      if (isset($upload_fileroot)) {
        // find that baby
        $fname = $this->get('filename');
        if (!isset($fname)) {
          $fname = $this->name();
        }

        $extensions = $this->extensions;
        $found = 0;
        foreach ($extensions as $thismime => $ext) {
          if (file_exists($upload_fileroot . $fname . $ext)) {
            $fname = $fname . $ext;
            $mime = $thismime;
            $found = 1;
            break;
          }
        }
        if (!$found) {
          return;
        }

        $img_data = $this->determine_size($upload_fileroot . $fname);
        if (!isset($img_data)) {
          // couldn't determine size
          $img_data = array();
        }
        $img_data['mime'] = $mime;
        $img_data['fname'] = $upload_fileroot . $fname;
        $img_data['url'] = $this->params['upload_urlroot'] . $fname;

        $images[] = $img_data;

        return $images;
      }
    }

    function show_upload_field ($attrs = '') {
      // can't really be styled except weird hacks: http://www.quirksmode.org/dom/inputfile.html
      return '<input type="hidden" name="_img_upload_names[]" value="' . $this->name() . '" />'
             . '<input type="file" name="_img_upload_files[]" />';
    }
  } // Images

  class ImageUpload
  {
    var $MAGICK_MIME = array(
                             'GIF' => 'image/gif',
                             'JPEG' => 'image/jpeg',
                             'TIFF' => 'image/tiff',
                             'PDF' => 'application/pdf',
                             );
    var $MAGICK_BINARIES = array('convert'  => 'convert',
                                 'mogrify' => 'mogrify',
                                 'identify' => 'identify');
    var $params;
    var $images;
    var $max_file_size;
    var $extensions = array('image/gif' => '.gif', 'image/jpeg' => '.jpg', 'image/png' => '.png',
                            'application/pdf' => '.pdf',
                            'text/rtf' => '.rtf',
                            'application/vnd.oasis.opendocument.text' => '.odt',
                            'application/msword' => '.doc',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
                            );
    var $translate = array('image/tiff' => 'image/jpeg');

    function __construct ($params) {
      $this->params = $params;
      $this->max_file_size = -1;
      if (isset($params['max_file_size'])) {
        $this->max_file_size = $params['max_file_size'];
      }

      if (get_cfg_var('upload_max_filesize')) {
        $max_file_size = get_cfg_var('upload_max_filesize');
        // hack to make this work with settings like 2M
        if (preg_match('/(\d+)M/', $max_file_size, $matches)) {
          $max_file_size = $matches[1] * 1000000;
        }

        if ($this->max_file_size < 0  || $this->max_file_size > $max_file_size) {
          $this->max_file_size = $max_file_size;
        }
      }
      else {
        $this->max_file_size = 2097152; // set a default value
      }
    }

    // accessor functions
    function set ($key, $value) {
      $this->params[$key] = $value;
    }

    function get ($key) {
      return $this->params[$key];
    }

    function extensions () {
      return $this->extensions;
    }

    function submitted () {
      if (isset($_POST['_img_upload_submit'])) {
        return TRUE;
      }
      else if (ini_get('register_globals')) {
        global $_img_upload_submit;
        return isset($_img_upload_submit);
      }
      return FALSE;
    }

    function parent_directory ($dir) {
      $regexp = PHP_OS == 'WINNT' ? '/[\/\\\\][^\/\\\\]+$/' : '/[\/][^\/]+$/';
      if (preg_match($regexp, $dir)) {
        return preg_replace($regexp, '', $dir);
      }
    }

    // recursively create the directory
    function check_directory ($dir, $create = 1, $mode = 0755) {
      if (file_exists($dir)) {
        return filetype($dir) == 'dir';  // check if it is a directory
      }
      if (!$create) {
        // doesn't exist, don't want to create
        return 0;
      }
      // recursively try to create that path
      $parent_dir = $this->parent_directory($dir);
      if (isset($parent_dir) && $this->check_directory($parent_dir, 1, $mode)) {
        return mkdir($dir, $mode);
      }
      return 0;
    }

    function delete_image ($name = '', $keep = '', $args='') {
      if (empty($name)) {
        if (is_array($args)) {
          $image = $this->images[$args['name']];
        }
        if (!isset($image)) {
          return;
        }
        $name = $image->get('filename');
        if (!isset($name)) {
          $name = $image->name();
        }

        $upload_fileroot = is_array($args) && isset($args['upload_fileroot'])
                         ? $args['upload_fileroot'] : $this->params['upload_fileroot'];
        $name = $upload_fileroot . $name;
      }

      foreach ($this->extensions as $mime => $extension) {
        if ($mime == $keep) {
          continue;
        }
        $remove = array('');
        if (is_array($args) && isset($args['variants'])) {
          $variants = & $args['variants'];
          if (is_array($variants)) {
            $remove = array_merge($remove, $variants);
          }
          else {
            $remove[] = $variants;
          }
        }
        if (count($remove) > 0) {
          clearstatcache();
        }

        for ($i = 0; $i < count($remove); $i++) {
          $append = !empty($remove[$i]) ? '_' . $remove[$i] : '';
          if (file_exists($name . $append . $extension)) {
            @unlink($name . $append . $extension);
            clearstatcache();
          }
        }
      }
    }

    function process_image ($image, $orig_name, $tmp_name, $size, $type, $args = '') {
      $new_name = $image->get('filename');
      if (!isset($new_name)) {
        $new_name = $image->name();
      }

      $scale = (gettype($args) == 'array' && isset($args['scale']))
             ? $args['scale'] : $image->get('scale');

      $keep  = (gettype($args) == 'array' && isset($args['keep']))
             ? $args['keep'] : $image->get('keep');

      $upload_fileroot = is_array($args) && isset($args['upload_fileroot'])
                       ? $args['upload_fileroot'] : $this->params['upload_fileroot'];
      $new_name = $upload_fileroot.$new_name;
      if (!$this->check_directory(dirname($new_name))) {
        return array('status' => -3, 'msg' => "Directory " . dirname($new_name) . " doesn't exist");
      }
      $ret = FALSE;
      if (isset($this->extensions[$type])) {
        $filename = $new_name . $this->extensions[$type];
        $ret = move_uploaded_file($tmp_name, $filename);
      }
      else if (isset($this->translate[$type])) {
        $filename = $new_name . $this->extensions[$this->translate[$type]];
        $path2magick = $this->params['imagemagick'];
        if (isset($path2magick) && isset($this->MAGICK_BINARIES['convert'])) {
          $cmd = $path2magick . $this->MAGICK_BINARIES['convert'] . ' ' . $tmp_name . ' ' . $filename;

          exec($cmd, $lines, $retval);
          if ($retval == 0) {
            $type = $this->translate[$type];
            $ret = TRUE;
          }
        }
      }

      if ($ret) {
        $sizes = $image->determine_size($filename);

        // delete a possible img of the other type
        $this->delete_image($new_name, $type);

        if (isset($scale)) {
          // we might have to remove more stuff
          $extension = '';
          if (!empty($keep)) {
            $extension = $keep;
          }
          else {
            if ($scale == 'down') {
              $extension = 'large';
            }
            else if ($scale == 'up') {
              $extension = 'small';
            }
            else {
              $extension = 'original';
            }
          }
          if (!empty($extension)) {
            $this->delete_image($new_name . '_' . $extension);
          }
        }

        if ($sizes['scaled'] && !empty($scale)) {
          if (($scale == 'down' && ($sizes['width'] < $sizes['width_physical'] || $sizes['height'] < $sizes['height_physical']))
             || ($scale == 'up' && ($sizes['width'] > $sizes['width_physical'] || $sizes['height'] > $sizes['height_physical']))
             || $scale == 'both')
          {
            if (!empty($keep)) {
              $extension = $keep;
            }
            else if (isset($keep) && $keep == '') {
              $extension = '';
            }
            else if ($scale == 'down') {
              $extension = 'large';
            }
            else if ($scale == 'up') {
              $extension = 'small';
            }
            else {
              $extension = 'original';
            }
            $path2magick = $this->params['imagemagick'];

            if (isset($path2magick) && isset($this->MAGICK_BINARIES['convert'])) {
              if ($extension != '') {
                // make a copy of the original
                if ($type == 'image/jpeg') {
                  // strip those nasty Photoshop 7 profiles
                  $cmd = $path2magick.$this->MAGICK_BINARIES['convert']
                       . ' +profile "*" -colorspace RGB ';
                  $cmd .= $filename . ' ' . $new_name . '_' . $extension . $this->extensions[$type];
                  // echo $cmd;
                  $ret = exec($cmd, $lines, $retval);
                }
                else {
                  $ret = copy($filename, $new_name . '_' . $extension . $this->extensions[$type]);
                }
              }

              $cmd = $path2magick . $this->MAGICK_BINARIES['convert']
                   . ' -geometry ' . $sizes['width'] . 'x' . $sizes['height'] . '! ';
              if ($type == 'image/jpeg') {
                $cmd .= '+profile "*" -colorspace RGB ';
              }
              $cmd .= $filename . ' ' . $filename;
              // echo $cmd;
              $ret = exec($cmd, $lines, $retval);
            }
          }
        }

        return array('status' => 1, 'msg' => 'File uploaded', 'mime' => $type, 'width' => $sizes['width'], 'height' => $sizes['height']);
      }
      else {
        return array('status' => -4, 'msg' => "Couldn't copy file to new location");
      }
    }

    // isn't used yet in the library
    function determine_size_mimetype ($filename) {
      // try to use identify if available
      $path2magick = $this->params['imagemagick'];

      if (isset($path2magick) && isset($this->MAGICK_BINARIES['identify'])) {
        $cmd = $path2magick . $this->MAGICK_BINARIES['identify']
             . ' -format "%m %wx%h" '
             . escapeshellarg($filename);

        unset($lines);
        $ret = exec($cmd, $lines, $retval);

        if ($retval == 0 && count($lines) > 0) {
          if (preg_match('/^(\w+)\s([0-9]+)x([0-9]+)$/', $lines[0], $matches)) {
            die($lines[0]." w: $matches[2] h: $matches[3] type: $matches[1]");
            list($width, $height) = array(intval($matches[2]), intval($matches[3]));
            if (isset($this->MAGICK_MIME[$matches[1]])) {
              $type = $this->MAGICK_MIME[$matches[1]];
            }
            else {
              $type = NULL;
            }

            return array($width, $height, $type);
          }
        }
      }
      $size = @getimagesize($filename);
      if ($size !== FALSE) {
        return array($size[0], $size[1], $size['mime']);
      }

      return FALSE;
    }

    function determine_mimetype ($filename, $guess) {
      $type = $guess;

      if ($type == 'image/pjpeg') {
        // IE sends 'image/pjpeg'
        $type = 'image/jpeg';
      }
      else if ($type == 'text/pdf') {
        // both are in use
        $type = 'application/pdf';
      }

      $path2magick = $this->params['imagemagick'];

      if (isset($path2magick) && isset($this->MAGICK_BINARIES['identify'])) {
        $cmd = $path2magick . $this->MAGICK_BINARIES['identify']
             . ' -format "%m %wx%h" '
             . escapeshellarg($filename)  . '[0]'; // use only first page, important for long pdfs;

        unset($lines);
        $ret = exec($cmd, $lines, $retval);
        if ($retval == 0 && count($lines) > 0) {
          if (preg_match('/^(\w+)\s([0-9]+)x([0-9]+)/', $lines[0], $matches)) {
            // die($lines[0]." w: $matches[2] h: $matches[3] type: $matches[1]");
            if (isset($this->MAGICK_MIME[$matches[1]])) {
              $type = $this->MAGICK_MIME[$matches[1]];
            }
          }
        }
        else
          unset($type);
      }
      else if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME,
                            defined('FILEINFO_MAGICFILE') ? FILEINFO_MAGICFILE : NULL); // return mime type ala mimetype extension
        if ($finfo) {
          $res = @finfo_file($finfo, $filename);
          if (FALSE !== $res
              && preg_match('/([a-z\.\-]+\/[a-z\.\-]+)/',
                            $res, $matches))
          {
            $res = $matches[1];
          }
          finfo_close($finfo);
          if (FALSE !== $res) {
            $type = $res;
          }
        }
      }
      /* else {
        require_once 'MIME/Type.php';
        $res = MIME_Type::autoDetect($filename); // Silence Strict standards: Non-static method MIME_Type::autoDetect() should not be called statically
        if (!@PEAR::isError($res)) {
          $type = $res;
        }
      }*/
      return isset($type) ? $type : NULL;
    }

    function process ($args = '') {
      // var_dump($_FILES);
      if (gettype($args) == 'string' && !empty($args)) {
        $img_name = $args;
      }
      else if (isset($args['img_name'])) {
        $img_name = $args['img_name'];
      }

      $status = array();
      $nonzero_count = 0;
      $upload_info = &$_FILES['_img_upload_files']; // from PHP 4.2 on
// var_dump($upload_info);
      for ($i = 0; $i < count($upload_info['tmp_name']); $i++) {
        $name = $_POST['_img_upload_names'][$i];
        $orig_name = $upload_info['name'][$i]; // isn't really used
        $tmp_name  = $upload_info['tmp_name'][$i];
        $size      = $upload_info['size'][$i];
        $type      = $upload_info['type'][$i];
// echo("$name $orig_name $tmp_name $size $type<br />");
        if ($size == 0) {
          $error = -1; // unknown error
          if (isset($upload_info) && $upload_info['error'][$i] != UPLOAD_ERR_OK) {
            // get error from error code
            $error = $upload_info['error'][$i];
          }
          else {
            // guess - the most probable reason if a filename is given is too large a file
            $error = !empty($orig_name) ? UPLOAD_ERR_FORM_SIZE : UPLOAD_ERR_NO_FILE;
          }

          $msgs = array(
            UPLOAD_ERR_INI_SIZE  => "The uploaded image exceeds the maximum file size",
            UPLOAD_ERR_FORM_SIZE => "The uploaded image exceeds the maximum allowed file size",
            UPLOAD_ERR_PARTIAL => "The image was only partially uploaded. Please try again",
          );

          switch ($error) {
            case UPLOAD_ERR_NO_FILE:
              $status[$name] = array('status' => 0, 'msg' => 'No file specified');
              break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
            case UPLOAD_ERR_PARTIAL:
              $msg = $msgs[$error];
              if ($error == UPLOAD_ERR_FORM_SIZE && $this->params['max_file_size'] > 0) {
                $msg .= ' (' . $this->params['max_file_size'] . ' bytes)';
              }
              $status[$name] = array('status' => -$error, 'msg' => $msg);
              break;
            default:
              $status = array('status' => -99, 'msg' => 'An error occurred uploading your image. If this error persists, please contact the administrator'); // unknown error code
          }
        }
        else {
          $type = $this->determine_mimetype($tmp_name, $type);
          $nonzero_count++;

          if (!isset($img_name) || $img_name == $name) {
            if (isset($this->extensions[$type])
               || isset($this->translate[$type])) {
              $status[$name] = $this->process_image($this->images[$name], $orig_name, $tmp_name, $size, $type, $args);
            }
            else {
              $status[$name] = array('status' => -1,
                                     'msg'  => empty($type) ? "Can't handle this datatype" : "Can't handle datatype $type");
            }
          }
        }
      }
      return $status;
    }

    function show_start () {
      return '<form enctype="multipart/form-data" method="post" action="'
           . $this->params['action']
           . '"><input type="hidden" name="_img_upload_submit" value="' . uniqid('') . '" />'
           . '<input type="hidden" name="MAX_FILE_SIZE" value="' . $this->max_file_size . '" />';
    }

    function show_end () {
      return '</form>';
    }

    function show_submit ($label = '', $attrs = '') {
      $value = trim($label) != '' ? ' value="' . $label . '"' : '';
      return '<input type="submit"' . $value . (!empty($attrs) ? ' ' . $attrs : '') . ' />';
    }

    function add_images ($images) {
      if (gettype($images) == 'array') {
        for ($i = 0; $i < count($images); $i++) {
          $this->images[$images[$i]->name()] = $images[$i];
          $this->images[$images[$i]->name()]->set('upload_fileroot',
                                                  $this->params['upload_fileroot']);
          $this->images[$images[$i]->name()]->set('upload_urlroot',
                                                  $this->params['upload_urlroot']);
        }
      }
    }

    function image ($name) {
      return $this->images[$name];
    }

  } // ImageUpload
