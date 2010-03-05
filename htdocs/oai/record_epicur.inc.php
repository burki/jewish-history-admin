<?PHP

      $output .= '
      <metadata>
        <epicur
            xmlns="urn:nbn:de:1111-2004033116"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xsi:schemaLocation="urn:nbn:de:1111-2004033116
            http://nbn-resolving.de/urn/resolver.pl?urn=urn:nbn:de:1111-2004033116">';

      if ((int)$tmp_array[3] == 1)
        $urn_state = "urn_new";
      else
        $urn_state = "url_update_general";

      $output .= '
          <administrative_data>
	    <delivery>
	      <update_status type="'.$urn_state.'"/>
            </delivery>
	  </administrative_data>';

      $mime = $tmp_array[4];

      $output .= '
          <record>
            <identifier scheme="urn:nbn:de">'.htmlspecialchars($tmp_array[1]).'</identifier>';
      if ((int)$tmp_array[3] != 3 && ereg("^http://", $tmp_array[2]))
        $output .= '
	    <resource>
	      <identifier scheme="url">'.htmlspecialchars($tmp_array[2]).'</identifier>
              <format scheme="imt">'.$mime.'</format>
	    </resource>';
      $output .= '
	  </record>';

      $output .= '</epicur>

      </metadata>';
?>