<?php
/*
 * img.php
 *
 * show enlarged image
 *
 * (c) 2009-2019 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2019-01-31 dbu
 *
 * Changes:
 */

// a bunch of common include-files
define('INC_PATH', '../inc/');


// setup all server specific paths and settings
require_once INC_PATH . 'local.inc.php';

// include all site-specific settings
require_once INC_PATH . 'sitesettings.inc.php';

$url = $width = $height = $caption = '';
foreach ([ 'url', 'width', 'height', 'caption' ] as $key) {
  $$key = array_key_exists($key, $_GET) ? $_GET[$key] : '';
}

$url_large = !isset($_GET['large']) || $_GET['large']
  ? preg_replace('/(\_small)?\.([^\.]+)$/', '_large.\2', $url) : $url;

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="de_DE" xml:lang="de_DE">
<head>
  <title><?php echo htmlspecialchars($SITE['pagetitle'], ENT_COMPAT, 'utf-8') ?> / image</title>
  <link rel="stylesheet" href="./css/styles_img.css" type="text/css" />
  <script src="./script/jquery-2.2.4.min.js"></script>
  <script src="./script/e-smart-zoom-jquery.min.js"></script>
  <meta http-equiv="content-type" content="text/html;charset=utf-8" />
		<!--[if lt IE 9]>
			<script src="//html5shiv.googlecode.com/svn/trunk/html5.js"></script>
		<![endif]-->
		<script>
			$(document).ready(function() {
              resizeDivs();

				$('#imageFullScreen').smartZoom({'containerClass':'zoomableContainer'});

				$('#topPositionMap,#leftPositionMap,#rightPositionMap,#bottomPositionMap').bind("click", moveButtonClickHandler);
  				$('#zoomInButton,#zoomOutButton').bind("click", zoomButtonClickHandler);

				function zoomButtonClickHandler(e){
			    	var scaleToAdd = 0.8;
					if(e.target.id == 'zoomOutButton')
						scaleToAdd = -scaleToAdd;
					$('#imageFullScreen').smartZoom('zoom', scaleToAdd);
			    }

			    function moveButtonClickHandler(e){
			    	var pixelsToMoveOnX = 0;
					var pixelsToMoveOnY = 0;

					switch(e.target.id){
						case "leftPositionMap":
							pixelsToMoveOnX = 50;
						break;
						case "rightPositionMap":
							pixelsToMoveOnX = -50;
						break;
						case "topPositionMap":
							pixelsToMoveOnY = 50;
						break;
						case "bottomPositionMap":
							pixelsToMoveOnY = -50;
						break;
					}
					$('#imageFullScreen').smartZoom('pan', pixelsToMoveOnX, pixelsToMoveOnY);
			    }

                function resizeDivs() {
                  var vpw = $(window).width();
                  var vph = $(window).height();
                  $('#pageContent').css({'width': vpw + 'px'});
                  $('#imgContainer').css({'width': vpw + 'px',
                                         'height': vph + 'px'});
                }

			});
		</script>
</head>

<body>
  <!--<div class="Caption">
    <p><?php echo preg_replace('/\n/', "<br />", htmlspecialchars($caption, ENT_COMPAT, 'utf-8')) ?></p>
  </div>-->
  <div id="page">
    <div id="pageContent">
        <div id="imgContainer">
            <img id="imageFullScreen" src="<?php echo htmlspecialchars($url_large) ?>"/>
        </div>
        <div id="positionButtonDiv">
            <p>Zoom :
                <span>
                    <img id="zoomInButton" class="zoomButton" src="./media/zoom/zoomIn.png" title="zoom in" alt="zoom in" />
                    <img id="zoomOutButton" class="zoomButton" src="./media/zoom/zoomOut.png" title="zoom out" alt="zoom out" />
                </span>
            </p>
            <p>
                <span class="positionButtonSpan">
                    <map name="positionMap" class="positionMapClass">
                        <area id="topPositionMap" shape="rect" coords="20,0,40,20" title="move up" alt="move up"/>
                        <area id="leftPositionMap" shape="rect" coords="0,20,20,40" title="move left" alt="move left"/>
                        <area id="rightPositionMap" shape="rect" coords="40,20,60,40" title="move right" alt="move right"/>
                        <area id="bottomPositionMap" shape="rect" coords="20,40,40,60" title="move bottom" alt="move bottom"/>
                    </map>
                    <img src="./media/zoom/position.png" usemap="#positionMap" />
                </span>
            </p>
        </div>
    </div>
  </div>
</body>

</html>
