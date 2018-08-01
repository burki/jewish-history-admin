<?php

/**
 * installation path (/var/www/tcpdf/)
 */

/* tcpdf must be defined and configured like:

define('K_PATH_MAIN', LIB_PATH . 'tcpdf/');

define('K_TCPDF_EXTERNAL_CONFIG', true); // we don't want default-config taken in
require_once('./TcpdfConfig.php'); // site-specific config
require_once(K_PATH_MAIN . 'tcpdf.php');
*/

class PdfExtended
extends TCPDF
{
  protected $print_header_suppress_first = true;

  /**
   * @var String to print on document footer.
   * @access protected
   */
  protected $footer_string = "";

  public function setFooterData($fs="") {
    $this->footer_string = $fs;
  }

  // no header on first page
  public function setPrintHeaderSuppressFirst($val=true) {
      $this->print_header_suppress_first = $val;
  }

  public function Header() {
    if ($this->print_header_suppress_first)
      $this->print_header_suppress_first = false;
    else {
      // parent::Header();
            $ormargins = $this->getOriginalMargins();
            $headerfont = $this->getHeaderFont();
            $headerdata = $this->getHeaderData();
            if (($headerdata['logo']) AND ($headerdata['logo'] != K_BLANK_IMAGE)) {
                $this->Image(K_PATH_IMAGES.$headerdata['logo'], $this->GetX(), $this->getHeaderMargin(), $headerdata['logo_width']);
                $imgy = $this->getImageRBY();
            } else {
                $imgy = $this->GetY();
            }
            $cell_height = round(($this->getCellHeightRatio() * $headerfont[2]) / $this->getScaleFactor(), 2);
            // set starting margin for text data cell
            if ($this->getRTL()) {
                $header_x = $ormargins['right'] + ($headerdata['logo_width'] * 1.1);
            } else {
                $header_x = $ormargins['left'] + ($headerdata['logo_width'] * 1.1);
            }
            $this->SetTextColor(0, 0, 0);
            // header title
            $this->SetFont($headerfont[0], '', $headerfont[2] + 1);
            $this->SetX($header_x);
            $this->Cell(0, $cell_height, $headerdata['title'], 0, 1, '');
            // header string
            $this->SetFont($headerfont[0], $headerfont[1], $headerfont[2]);
            $this->SetX($header_x);
            $this->MultiCell(0, $cell_height, $headerdata['string'], 0, '', 0, 1, 0, 0, true, 0);
            // print an ending header line
            $this->SetLineStyle(["width" => 0.85 / $this->getScaleFactor(), "cap" => "butt", "join" => "miter", "dash" => 0, "color" => [0, 0, 0]]);
            $this->SetY(1 + max($imgy, $this->GetY()));
            if ($this->getRTL()) {
                $this->SetX($ormargins['right']);
            } else {
                $this->SetX($ormargins['left']);
            }
            // $this->Cell(0, 0, '', 'T', 0, 'C');

    }
  }

  protected function openHTMLTagHandler(&$dom, $key, $cell=false) {
      $tag = $dom[$key];
      $parent = $dom[($dom[$key]['parent'])];
      //Closing tag
      if ($tag['value'] == 'p')
        $this->Ln();
      else
        parent::openHTMLTagHandler($dom, $key, $cell);
  }

  protected function closedHTMLTagHandler(&$dom, $key, $cell=false) {
      $tag = $dom[$key];
      $parent = $dom[($dom[$key]['parent'])];
      //Closing tag
      if ($tag['value'] == 'p')
        $this->Ln();
      else
        parent::closedHTMLTagHandler($dom, $key, $cell);
  }

  /**
   * This method is used to render the page footer.
   * It is automatically called by AddPage() and could be overwritten in your own inherited class.
   */
  public function Footer() {
      if ($this->print_footer) {
          if (!isset($this->original_lMargin)) {
              $this->original_lMargin = $this->lMargin;
          }
          if (!isset($this->original_rMargin)) {
              $this->original_rMargin = $this->rMargin;
          }
          // reset original header margins
          $this->rMargin = $this->original_rMargin;
          $this->lMargin = $this->original_lMargin;
          // save current font values
          $font_family =  $this->FontFamily;
          $font_style = $this->FontStyle;
          $font_size = $this->FontSizePt;
          $this->SetTextColor(0, 0, 0);
          //set font
          $this->SetFont($this->footer_font[0], $this->footer_font[1] , $this->footer_font[2]);
          //set style for cell border
          $prevlinewidth = $this->GetLineWidth();
          $line_width = 0; // hairline 0.85 / $this->k;
          $this->SetLineWidth($line_width);
          $this->SetDrawColorArray([0, 0, 0]);
          $footer_height = round(($this->cell_height_ratio * $this->footer_font[2]) / $this->k, 2); //footer height
          //get footer y position
          $footer_y = $this->h - $this->footer_margin - $footer_height;
          //set current position
          if ($this->rtl) {
              $this->SetXY($this->original_rMargin, $footer_y);
          } else {
              $this->SetXY($this->original_lMargin, $footer_y);
          }
          // dbu: print footer text
          if (!empty($this->footer_string)) {
            $this->SetX($this->original_lMargin);
            $this->Cell(0, $footer_height, $this->footer_string, 0, 0, 'L');
          }

          $pagenumtxt = $this->l['w_page']." ".$this->PageNo().' / {nb}';
          $this->SetY($footer_y);
          //Print page number
          if ($this->rtl) {
              $this->SetX($this->original_rMargin);
              $this->Cell(0, $footer_height, $pagenumtxt, 'T', 0, 'L');
          } else {
              $this->SetX($this->original_lMargin);
              $this->Cell(0, $footer_height, $pagenumtxt, 0, 0, 'R');
          }
          // restore line width
          $this->SetLineWidth($prevlinewidth);
          // restore font values
          $this->SetFont($font_family, $font_style, $font_size);
      }
  }
}
