#! /usr/bin/perl
#
# Diese Datei generiert aus der Excel-Datei die entsprechenden
# message-Dateien
#
#
# Changes:
#
use utf8;
use Win32::OLE qw();


use Win32::OLE qw(in with);
use Win32::OLE::Const 'Microsoft Excel';

use HTML::Entities ();

Win32::OLE->Option(CP => CP_UTF8);      # set utf8 encoding
$Win32::OLE::Warn = 3;    # die on errors...

my $Excel = Win32::OLE->GetActiveObject('Excel.Application')
    || Win32::OLE->new('Excel.Application', 'Quit');  # get/open Excel

my $xls = shift;
my $Book = $Excel->Workbooks->Open($xls);  # open Excel file

my $Sheet = $Book->Worksheets(1);          # select worksheet number 1
my $line = 1;
my $first = 1;

my @fieldnames = ('orig');
my $last; # last excel column
my @lines = ();

while (1) {
  if ($first) {
    # get fieldnames and $last from first line
    foreach ('B' .. 'Z') {
      my $cell = $Sheet->Range("$_${line}");
      my $lang = $cell->{'Value'};
      last unless $lang =~ /\S/;
      push @fieldnames, $lang;
      $last = $_;
    }
    $first = 0; $line++;
    next;
  }

  my $array = $Sheet->Range("A${line}:$last${line}")->{'Value'};  # get the contents
  my @line = @{$$array[0]};
  my @nonempty = grep(defined($_), @line);
  last if $#nonempty == -1;
  $line++;

  for ($i=0; $i<= $#fieldnames; $i++) {
    if ($line[$i] =~ /\S/) {
      $line[$i] =~ s/\s+$//;
    }
  }
  push @lines, \@line;
}
$Book->Close;

# output for each language
for (my $i = 1; $i <= $#fieldnames; $i++) {
  my $lang = $fieldnames[$i];
  my $fname = $lang . '.inc.php';
  open(OUT, ">$fname") || die "Couldn't create $fname\n";
  binmode OUT, ":unix";
  binmode OUT, ":utf8";
  print OUT <<EOT;
<?php

\$GETTEXT_MESSAGES = array (

EOT
  foreach my $arLine (@lines) {
    my $key = $$arLine[0];
    next unless $key =~ /\S/;
    my $val = $$arLine[$i];
    next unless $key =~ /\S/;
    next unless $val =~ /\S/;
    print OUT php_quote($key) . ' => ' . php_quote($val) . ',' . "\n";
  }

  print OUT <<EOT;
);

EOT
  close(OUT);
}

sub php_quote {
  my ($txt, $replace_numentities) = @_;
  # $txt = encode_numeric_decimal($txt) if $replace_numentities; # currently disabled
  $txt =~ s/'/\\'/g;
  "'" . $txt . "'";
}

sub encode_numeric_decimal {
  my $txt = HTML::Entities::encode_numeric(shift);
  $txt =~ s{&#x([0-9A-F]+);}{'&#'.hex($1).';'}eg;

  $txt
}
