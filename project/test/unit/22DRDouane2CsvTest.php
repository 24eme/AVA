<?php require_once(dirname(__FILE__).'/../bootstrap/common.php');

$csv = new DRDouaneCsvFile(dirname(__FILE__).'/../data/dr_douane_'.$application.'.csv');
$csvExploitant = $csv->convert();
$csvBailleur = $csv->convert();
$linesExploitant = explode("\n", $csvExploitant);
$linesBailleur = explode("\n", $csvBailleur);

$t = new lime_test(((count($linesExploitant) - 1) * 6) + ((count($linesBailleur) - 1)) * 5);

$t->comment("Fichier de test : ".dirname(__FILE__).'/../data/dr_douane_'.$application.'.csv');
$t->diag("Tests sur les données Exploitants");

$i = 0;
$last_l = 9999;
foreach($linesExploitant as $line) {
    if(!$line) {
        continue;
    }

    $line = explode(";", $line);

    if ($last_l < $line[DRCsvFile::CSV_LIGNE_CODE]) {
        $colonneid ++;
    }

    $t->is($line[DRCsvFile::CSV_TYPE], "DR", "Le type de la ligne est DR");
    $t->is($line[DRCsvFile::CSV_CAMPAGNE], date('Y'), "La campagne est 2017");
    $t->is($line[DRCsvFile::CSV_RECOLTANT_CVI], "7523700100", "Le CVI est 7523700100");
    $t->is($line[DRCsvFile::CSV_RECOLTANT_LIBELLE], "\"ACTUALYS JEAN\"", "Le nom est ACTUALYS JEAN");
    $t->is($line[DRCsvFile::CSV_RECOLTANT_COMMUNE], "NEUILLY", "Le commune est NEUILLY");
    $t->is($line[DRCsvFile::CSV_PRODUIT_CERTIFICATION], "AOP", "certification trouvée pour ".$line[DRCsvFile::CSV_PRODUIT_LIBELLE]);
    $t->is($line[DRCsvFile::CSV_COLONNE_ID], $colonneid, "Bon numéro de colonne : (". $colonneid .") #".$line[DRCsvFile::CSV_COLONNE_ID]);
    $i++;
}


$t->diag("Tests sur les données Bailleur");

foreach($linesBailleur as $line) {
    if(!$line) {
        continue;
    }

    $line = explode(";", $line);
    $t->is($line[DRCsvFile::CSV_TYPE], "DR", "Le type de la ligne est DR");
    $t->is($line[DRCsvFile::CSV_CAMPAGNE], date('Y'), "La campagne est 2017");
    $t->is($line[DRCsvFile::CSV_RECOLTANT_CVI], "7523700100", "Le CVI est 7523700100");
    $t->is($line[DRCsvFile::CSV_RECOLTANT_LIBELLE], "\"ACTUALYS JEAN\"", "Le nom est ACTUALYS JEAN");
    $t->is($line[DRCsvFile::CSV_RECOLTANT_COMMUNE], "NEUILLY", "Le commune est NEUILLY");

    $i++;
}
