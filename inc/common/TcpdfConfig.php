<?php

/**
 * Materialarchiv specific settings for TCPDF
 * @author Daniel Burckhardt
 * @copyright 2008 daniel.burckhardt@sur-gmbh.ch
 *
 */


/**
 * url path (http://localhost/tcpdf/)
 */
define ("K_PATH_URL", "http://localhost/tcpdf/");


/**
 * path for PDF fonts
 * use K_PATH_MAIN."fonts/old/" for old non-UTF8 fonts
 */
define ("K_PATH_FONTS", K_PATH_MAIN."fonts/");

/**
 * cache directory for temporary files (full path)
 */
define ("K_PATH_CACHE", K_PATH_MAIN."cache/");

/**
 * cache directory for temporary files (url path)
 */
// define ("K_PATH_URL_CACHE", K_PATH_URL."cache/");

/**
 *images directory
 */
define ("K_PATH_IMAGES", K_PATH_MAIN."images/");

/**
 * blank image
 */
define ("K_BLANK_IMAGE", K_PATH_IMAGES."_blank.png");

/**
 * page format
 */
define ("PDF_PAGE_FORMAT", "A4");

/**
 * page orientation (P=portrait, L=landscape)
 */
define ("PDF_PAGE_ORIENTATION", "P");

/**
 * document creator
 */
define ("PDF_CREATOR", "Materialarchiv");

/**
 * document author
 */
define ("PDF_AUTHOR", "Materialarchiv");

/**
 * header title
 */
define ("PDF_HEADER_TITLE", '');

/**
 * header description string
 */
define ("PDF_HEADER_STRING", '');

/**
 * image logo
 */
define ('PDF_HEADER_LOGO', ''); // "logo_materialarchiv.jpg"

/**
 * header logo image width [mm]
 */
define ("PDF_HEADER_LOGO_WIDTH", 0);

/**
 *  document unit of measure [pt=point, mm=millimeter, cm=centimeter, in=inch]
 */
define ("PDF_UNIT", "mm");

/**
 * header margin
 */
define ("PDF_MARGIN_HEADER", 5);

/**
 * footer margin
 */
define ("PDF_MARGIN_FOOTER", 10);

/**
 * top margin
 */
define ("PDF_MARGIN_TOP", 27);

/**
 * bottom margin
 */
define ("PDF_MARGIN_BOTTOM", 25);

/**
 * left margin
 */
define ("PDF_MARGIN_LEFT", 13.5);

/**
 * right margin
 */
define ("PDF_MARGIN_RIGHT", 27);

/**
 * main font name
 */
define ("PDF_FONT_NAME_MAIN", "vera"); //vera

/**
 * main font size
 */
define ("PDF_FONT_SIZE_MAIN", 10.2);

/**
 * data font name
 */
define ("PDF_FONT_NAME_DATA", "vera"); //vera

/**
 * data font size
 */
define ("PDF_FONT_SIZE_DATA", 8);

/**
 *  scale factor for images (number of points in user unit)
 */
define ("PDF_IMAGE_SCALE_RATIO", 4);

/**
 * magnification factor for titles
 */
define("HEAD_MAGNIFICATION", 1.1);

/**
 * height of cell repect font height
 */
define("K_CELL_HEIGHT_RATIO", 1.25);

/**
 * title magnification respect main font size
 */
define("K_TITLE_MAGNIFICATION", 1.3);

/**
 * reduction factor for small font
 */
define("K_SMALL_RATIO", 2/3);
