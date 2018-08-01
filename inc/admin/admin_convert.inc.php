<?php
/*
 * admin_convert.inc.php
 *
 * TEI to HTML converter (based on )
 *
 * (c) 2014-2018 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2018-05-22 dbu
 *
 * Changes:
 *
 */


/* Converts any HTML-entities into characters */
function decode_numericentity($t)
{
    $convmap = [ 0x0, 0x2FFFF, 0, 0xFFFF ];

    return mb_decode_numericentity($t, $convmap, 'UTF-8');
}

class DisplayConvert
extends PageDisplay
{
  /**
   * Performs the XML to HTML processing operation.
   * @param xml
   *   The xml document as a text string (mostly obtained from the body of a node)
   * @param path_to_xslt
   *   The file path to the XSLT script to be used for processing
   * @param params
   *   An array of name-value pairs for special parameters to be passed to the XSLT processor
   *   before invoking it on the XML data document. Examples include namespace settings and
   *   XSL parameters.
   */
   function teicontent_transform($xml, $path_to_xslt, $path_to_css = '', $params = []) {

    if (!$xml) {
      return $xml;
    }

    // Load the XML document
    $dom = new DomDocument('1.0', 'UTF-8');
    @$valid = $dom->loadXML($xml);
    if (!$valid) {
      return $xml;
    }

    //debug($xml);

    // Load the XSLT script
    // var_dump($path_to_xslt);
    $xsl = new DomDocument('1.0', 'UTF-8');
    //debug("path is: ". $path_to_xslt);
    $xsl->load($path_to_xslt);
    // Create the XSLT processor
    $proc = new XsltProcessor();
    $xsl = $proc->importStylesheet($xsl);

     // Currently the empty &quot;&quot; namespace is used.
    if  (!defined('XMLCONTENT_DFLT_NS' )) {
      define('XMLCONTENT_DFLT_NS', '');
    }

     // initialize the processor with the parameters when defined
    foreach  ($params as $key => $value) {
      $proc->setParameter(XMLCONTENT_DFLT_NS, $key, $value);
    }

    //debug($dom);

    // Transform
    $newdom = $proc->transformToDoc($dom);

    $out = $newdom->saveXML();

    return $out;
  }

  function buildContent () {
    $converted = '';

    $body_value = $html = '';
    if (!empty($_POST['body'])) {
      $body = $_POST['body'];
      $body_value = htmlspecialchars($body, ENT_COMPAT, 'utf-8');
      $path_to_xslt = INC_PATH . '/common/drp_style.xsl';
      // $html = '<pre>' . htmlspecialchars(decode_numericentity($body), ENT_COMPAT, 'utf-8') . '</pre>';
      $html = '<hr noshade="noshade" />'
            . $this->teicontent_transform($body, $path_to_xslt);
      // $html = '<pre>' . htmlspecialchars($html, ENT_COMPAT, 'utf-8') . '</pre>';
    }
    $url_self = htmlspecialchars($this->page->buildLink(['pn' => $this->page->name]));
    $convert = tr('convert');

    $form = <<<EOT
<form method="post" action="$url_self">
<textarea cols="80" rows="30" name="body" style="font-family: monospace">$body_value</textarea>
<input type="submit" value="$convert" />
</form>
EOT;

    return $form . $html;
  } // buildContent
}

$page->setDisplay(new DisplayConvert($page));
