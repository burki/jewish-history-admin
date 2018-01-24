<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:tei="http://www.tei-c.org/ns/1.0"
  xpath-default-namespace="http://www.tei-c.org/ns/1.0"
  version="2.0">

  <!-- converter fails on this tag that we use for audio/video -->
  <xsl:template match="media"></xsl:template>

  <xsl:template match="@*|node()">
    <xsl:copy>
      <xsl:apply-templates select="@*|node()"/>
    </xsl:copy>
  </xsl:template>

  <xsl:template match="body">
    <tei:body>
      <xsl:if test="/TEI/teiHeader/fileDesc/notesStmt/note">
        <tei:div n="2">
          <tei:head>Quellenbeschreibung</tei:head>
          <p><xsl:apply-templates select="/TEI/teiHeader/fileDesc/notesStmt/note/node()"/></p>
        </tei:div>
      </xsl:if>
      <xsl:apply-templates select="node()"/>
    </tei:body>
  </xsl:template>

</xsl:stylesheet>