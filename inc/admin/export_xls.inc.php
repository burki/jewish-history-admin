<?php
/*
 * export_xls.inc.php
 *
 * Trait for xls-Export
 *
 * (c) 2007-2024 daniel.burckhardt@sur-gmbh.ch
 *
 * Version: 2024-04-30 dbu
 *
 * Changes:
 *
 */

trait ExportXls
{
   function escapeXls ($str) {
    if (is_null($str)) {
      return '';
    }

    if (is_object($this->xls_data)) {
      return $str;
    }

    // for class Excel_XML
    return htmlspecialchars($str, ENT_XML1, 'UTF-8');
  }

  function formatXls ($data, $replaceNewline = ', ') {
    $val = rtrim($this->escapeXls($data));
    $val = preg_replace('/\t/', ' ', $val); // tabs otherwise get removed

    if (false === $replaceNewline) {
      return $val;
    }

    return preg_replace('/\r?\n/', $replaceNewline, $val);
  }

  function addRowXls (&$xls_row) {
    if (is_object($this->xls_data)) {
      $this->xls_data->addRow($xls_row);
    }
    else {
      $this->xls_data[] = $xls_row;
    }
  }

  function buildListingRowXls (&$row) {
    $xls_row = [];

    for ($i = 0; $i < $this->cols_listing_count; $i++) {
      $xls_row[] = $this->formatXls($row[$i]);
    }

    $this->addRowXls($xls_row);
  }

  function buildListingRow (&$row) {
    if ('xls' == $this->page->display) {
      return $this->buildListingRowXls($row);
    }

    return parent::buildListingRow($row);
  }
}
