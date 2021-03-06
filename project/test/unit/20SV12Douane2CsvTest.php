<?php require_once(dirname(__FILE__).'/../bootstrap/common.php');

$config = ConfigurationClient::getCurrent();
foreach($config->getProduits() as $produit) {
    if(!$produit->getRendement()) {
        continue;
    }
    break;
}

$t = new lime_test(27);

$t->ok($produit->getLibelleComplet(), "configuration de base est OK : on a un libellé de produit");
$t->ok($produit->getCodeDouane(), "configuration de base est OK : le produit a un code douane");

$csvContentTemplate = file_get_contents(dirname(__FILE__).'/../data/sv12_douane.csv');

$csvTmpFile = tempnam(sys_get_temp_dir(), 'openodg');
file_put_contents($csvTmpFile, str_replace(array("%cvi%", "%code_inao%", "%libelle_produit%"), array("7523700100", $produit->getCodeDouane(), $produit->getLibelleComplet()), $csvContentTemplate));

$csv = new SV12DouaneCsvFile($csvTmpFile);
$csvConvert = $csv->convert();
unlink($csvTmpFile);

$lines = explode("\n", $csvConvert);


$nb = 0;
foreach($lines as $line) {
    if(!$line) {
        continue;
    }
    $nb++;
}
$t->is($nb, 4, "Le CSV a 4 lignes");

$line = explode(";", $lines[0]);


$t->is($line[SV12CsvFile::CSV_TYPE], "SV12", "Le type de la ligne est SV12");
$t->is($line[SV12CsvFile::CSV_CAMPAGNE], date('Y'), "La campagne est ".date('Y'));
$t->is($line[SV12CsvFile::CSV_RECOLTANT_CVI], "7523700100", "Le CVI est 7523700100");
$t->is($line[SV12CsvFile::CSV_PRODUIT_CERTIFICATION], $produit->getCertification()->getKey(), "Certification OK");
$t->is($line[SV12CsvFile::CSV_PRODUIT_GENRE], $produit->getGenre()->getKey(), "Genre OK");
$t->is($line[SV12CsvFile::CSV_PRODUIT_APPELLATION], $produit->getAppellation()->getKey(), "Appellation OK");
$t->is($line[SV12CsvFile::CSV_PRODUIT_MENTION], $produit->getMention()->getKey(), "Mention OK");
$t->is($line[SV12CsvFile::CSV_PRODUIT_LIEU], $produit->getLieu()->getKey(), "Lieu OK");
$t->is($line[SV12CsvFile::CSV_PRODUIT_COULEUR], $produit->getCouleur()->getKey(), "Couleur OK");
$t->is($line[SV12CsvFile::CSV_PRODUIT_CEPAGE], $produit->getCepage()->getKey(), "Cepage OK");
$t->is($line[SV12CsvFile::CSV_PRODUIT_INAO], $produit->getCodeDouane(), "Le code inao est OK");
$t->is($line[SV12CsvFile::CSV_PRODUIT_LIBELLE], $produit->getLibelleComplet(), "Libelle complet OK");

$t->is($line[SV12CsvFile::CSV_LIGNE_CODE], "07", "Code du type de mouvement");
$t->is($line[SV12CsvFile::CSV_LIGNE_LIBELLE], "Quantité de VF", "Libelle du type de mouvement");
$t->is(round(str_replace(",", ".", $line[SV12CsvFile::CSV_VALEUR]), 4), 25105, "Valeur");

$line = explode(";", $lines[1]);
$t->is($line[SV12CsvFile::CSV_LIGNE_CODE], "09", "Code du type de mouvement");
$t->is($line[SV12CsvFile::CSV_LIGNE_LIBELLE], "Superficie de récolte", "Libelle du type de mouvement");
$t->is(round(str_replace(",", ".", $line[SV12CsvFile::CSV_VALEUR]), 4), 6.202, "Valeur");

$line = explode(";", $lines[2]);
$t->is($line[SV12CsvFile::CSV_LIGNE_CODE], "10", "Code du type de mouvement");
$t->is($line[SV12CsvFile::CSV_LIGNE_LIBELLE], "Volume issu de VF", "Libelle du type de mouvement");
$t->is(round(str_replace(",", ".", $line[SV12CsvFile::CSV_VALEUR]), 4), 180, "Valeur");

$line = explode(";", $lines[3]);
$t->is($line[SV12CsvFile::CSV_LIGNE_CODE], "12", "Code du type de mouvement");
$t->is($line[SV12CsvFile::CSV_LIGNE_LIBELLE], "Total produit", "Libelle du type de mouvement");
$t->is(round(str_replace(",", ".", $line[SV12CsvFile::CSV_VALEUR]), 4), 180, "Valeur");
