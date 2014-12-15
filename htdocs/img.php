<?php
/*
 * img.php
 *
 * show enlarged image
 *
 * (c) 2009 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2009-03-30 dbu
 *
 * Changes:
 */

// a bunch of common include-files
define('INC_PATH', '../inc/');


// setup all server specific paths and settings
require_once INC_PATH . 'local.inc.php';

// include all site-specific settings
require_once INC_PATH . 'sitesettings.inc.php';

$STRIP_SLASHES = get_magic_quotes_gpc();  // set to 1 in php3 where this function doesn't exist

$url = $width = $height = $caption = '';
foreach (array('url', 'width', 'height', 'caption') as $key) {
  $$key = array_key_exists($key, $_GET) ? $_GET[$key] : '';
  if ($STRIP_SLASHES)
    $$key = stripslashes($$key);
}

$url_large = !isset($_GET['large']) || $_GET['large']
  ? preg_replace('/(\_small)?\.([^\.]+)$/', '_large.\2', $url) : $url;

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="de_DE" xml:lang="de_DE">
<head>
  <title><?php echo htmlspecialchars($SITE['pagetitle']) ?> / image</title>
  <link rel="stylesheet" type="text/css" href="css/style.php" />
  <meta http-equiv="content-type" content="text/html;charset=utf-8" />
</head>

<body>
<div class="Popup" style="width: <?php echo $width ?>;">
<a href="javascript:window.close();">
<img src="<?php echo htmlspecialchars($url_large) ?>" width="<?php echo intval($width) ?>" height="<?php echo intval($height) ?>" alt="Click to close" border="0" /></a>
<div class="Caption">
<p><?php echo preg_replace('/\n/', "<br />", htmlspecialchars($caption, ENT_COMPAT, 'utf-8')) ?></p></div>
</div>
</body>
</html>
