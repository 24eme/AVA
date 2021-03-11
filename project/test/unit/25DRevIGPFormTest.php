<?php

require_once(dirname(__FILE__).'/../bootstrap/common.php');

if ($application != 'igp13') {
    $t = new lime_test(1);
    $t->ok(true, "pass AOC");
    return;
}

$t = new lime_test(127);

$viti =  CompteTagsView::getInstance()->findOneCompteByTag('test', 'test_viti')->getEtablissement();

//Suppression des DRev précédentes
foreach(DRevClient::getInstance()->getHistory($viti->identifiant, acCouchdbClient::HYDRATE_ON_DEMAND) as $k => $v) {
    DRevClient::getInstance()->deleteDoc(DRevClient::getInstance()->find($k, acCouchdbClient::HYDRATE_JSON));
    $dr = DRClient::getInstance()->find(str_replace("DREV-", "DR-", $k), acCouchdbClient::HYDRATE_JSON);
    if($dr) { DRClient::getInstance()->deleteDoc($dr); }
    $sv12 = SV12Client::getInstance()->find(str_replace("DREV-", "SV12-", $k), acCouchdbClient::HYDRATE_JSON);
    if($sv12) { SV12Client::getInstance()->deleteDoc($sv12); }
    $sv11 = SV11Client::getInstance()->find(str_replace("DREV-", "SV11-", $k), acCouchdbClient::HYDRATE_JSON);
    if($sv11) { SV11Client::getInstance()->deleteDoc($sv11); }
}

foreach(HabilitationClient::getInstance()->getHistory($viti->identifiant) as $k => $v) {
    HabilitationClient::getInstance()->deleteDoc(HabilitationClient::getInstance()->find($k, acCouchdbClient::HYDRATE_JSON));
}

$config = ConfigurationClient::getCurrent();
$produitconfig1 = null;
$produitconfig2 = null;
$produitconfig_horsDR = null;
foreach($config->getProduits() as $produitconfig) {
    if(!$produitconfig->getRendement()) {
        continue;
    }
    if(!$produitconfig1) {
        $produitconfig1 = $produitconfig->getCepage();
        continue;
    } elseif(!$produitconfig2) {
        $produitconfig2 = $produitconfig->getCepage();
        continue;
    } elseif(!$produitconfig_horsDR) {
        $produitconfig_horsDR = $produitconfig->getCepage();
        continue;
    }

    break;
}

$csvContentTemplate = file_get_contents(dirname(__FILE__).'/../data/dr_douane.csv');

$csvTmpFile = tempnam(sys_get_temp_dir(), 'openodg').".csv";
file_put_contents($csvTmpFile, str_replace(array("%cvi%", "%code_inao_1%", "%libelle_produit_1%","%code_inao_2%", "%libelle_produit_2%"), array($viti->cvi, $produitconfig1->getCodeDouane(), $produitconfig1->getLibelleComplet(), $produitconfig2->getCodeDouane(), $produitconfig2->getLibelleComplet()), $csvContentTemplate));
$t->comment("utilise le fichier test/data/dr_douane.csv");
$t->comment("%libelle_produit_1% = ".$produitconfig1->getLibelleComplet());
$t->comment("%libelle_produit_2% = ".$produitconfig2->getLibelleComplet());

$campagne = (date('Y')-1)."";

$drev = DRevClient::getInstance()->createDoc($viti->identifiant, $campagne);
$drev->save();
$t->comment($drev->_id);
$t->comment("Récupération des données à partir de la DR");

$dr = DRClient::getInstance()->createDoc($viti->identifiant, $campagne);
$dr->setLibelle("DR $campagne issue de Prodouane (Papier)");
$dr->setDateDepot("$campagne-12-15");
$dr->save();
$dr->storeFichier($csvTmpFile);
$dr->save();

$drev->importFromDocumentDouanier();
$drev->save();

$t->is(count(array_keys($drev->getProduits())), 2 + (!DRevConfiguration::getInstance()->hasDenominationAuto()) * 2, "La DRev a repris le bon nombre (". (2 + (!DRevConfiguration::getInstance()->hasDenominationAuto()) * 2).") de produits du csv de la DR");

$produits = $drev->getProduits();


$produit1 = current($produits);
$produit_hash1 = $produit1->getHash();

next($produits);
$produit2 = current($produits);
$produit_hash2 = $produit2->getHash();

$drev->save();

$t->is($produit1->recolte->superficie_total, 2.4786 * (1 + (DRevConfiguration::getInstance()->hasDenominationAuto())), "La superficie total de la DR pour le produit ".$produit1->getLibelleComplet()." est OK");
$t->is($produit1->recolte->volume_sur_place, 105.18 * (1 + (DRevConfiguration::getInstance()->hasDenominationAuto())), "Le volume sur place pour ce produit ".$produit1->getLibelleComplet()." est OK");
$t->is($produit1->recolte->usages_industriels_total, 3.03 * (1 + (DRevConfiguration::getInstance()->hasDenominationAuto())), "Les usages industriels la DR pour ce produit ".$produit1->getLibelleComplet()." sont OK");
$t->is($produit1->recolte->recolte_nette, 104.1 * (1 + (DRevConfiguration::getInstance()->hasDenominationAuto())), "La récolte nette de la DR pour ce produit ".$produit1->getLibelleComplet()." est OK");
$t->is($produit1->recolte->volume_total, 105.18 * (1 + (DRevConfiguration::getInstance()->hasDenominationAuto())), "Le volume total de la DR pour ce produit ".$produit1->getLibelleComplet()." est OK");

$t->comment('Formulaire de revendication des superficies');

if($drev->storeEtape(DrevEtapes::ETAPE_REVENDICATION_SUPERFICIE)) {
    $drev->save();
}

$form = new DRevSuperficieForm($drev);

$defaults = $form->getDefaults();

$t->is(count(array_keys($form['produits']->getValue())), count(array_keys($drev->getProduits())), "le formulaire a le même nombre de produit (".count(array_keys($drev->getProduits())).") que la DRev (declaration)");

$t->is($form['produits'][$produit_hash1]['recolte']['superficie_total']->getValue(), $produit1->recolte->superficie_total, "La superficie totale de la DR est initialisé dans le form");
$t->is($form['produits'][$produit_hash1]['superficie_revendique']->getValue(), $produit1->superficie_revendique, "La superficie revendique est initialisé dans le form");
$t->ok(!isset($form['produits'][$produit_hash1]['recolte']['volume_total']), "Le volume total de la DR n'est pas proposé dans le formulaire");
$t->ok(!isset($form['produits'][$produit_hash1]['recolte']['recolte_nette']), "Le volume de récolte nette de la DR n'est pas proposé dans le formulaire");
$t->ok(!isset($form['produits'][$produit_hash1]['recolte']['volume_sur_place']), "Le volume sur place de la DR n'est pas proposé dans le formulaire");

$valuesRev = array(
    'produits' => $form['produits']->getValue(),
    '_revision' => $drev->_rev,
);

$valuesRev['produits'][$produit_hash1]['superficie_revendique'] = 10;
$valuesRev['produits'][$produit_hash1]['recolte']['superficie_total'] = 10;
$valuesRev['produits'][$produit_hash2]['recolte']['superficie_total'] = 300;

$form->bind($valuesRev);

$t->ok($form->isValid(), "Le formulaire est valide");
$form->save();

$t->is($produit1->recolte->superficie_total, $valuesRev['produits'][$produit_hash1]['recolte']['superficie_total'], "La superficie total de la DR est enregistré");
$t->is($produit1->superficie_revendique, $valuesRev['produits'][$produit_hash1]['superficie_revendique'], "La superficie revendique est enregistré");

$t->comment("Étape lots");
$t->comment("Vérifier la spécificité");
$drevConfig = DRevConfiguration::getInstance();
$t->is($drevConfig->hasSpecificiteLot(), true, "La configuration a des spécificités de Lots");
$t->ok(count($drevConfig->getSpecificites()), "La configuration retourne bien des spécificités");

if($drev->storeEtape(DrevEtapes::ETAPE_LOTS)) {
    $drev->save();
}

$form = new DRevLotsForm($drev);
$defaults = $form->getDefaults();

$t->is(count($form['lots']), 2, "autant de lots que de colonnes dans le DR");
$t->is($form['lots']['0']['produit_hash']->getValue(), $produit1->getParent()->getHash(), 'lot 1 : un produit est déjà sélectionné');
$t->is($form['lots']['0']['millesime']->getValue(), $campagne, 'lot 1 : le millesime est prérempli');

$valuesRev = array(
    'lots' => $form['lots']->getValue(),
    '_revision' => $drev->_rev,
);
$valuesRev['lots']['0']['numero_logement_operateur'] = "Cuve A";
$valuesRev['lots']['0']['volume'] = 1008.2;
$valuesRev['lots']['0']['destination_type'] = DRevClient::LOT_DESTINATION_VRAC_FRANCE;
$valuesRev['lots']['0']['destination_date'] = '30/11/'.$campagne;
if($drevConfig->hasSpecificiteLot()){
  $t->is($valuesRev['lots']['0']['specificite'], 'UNDEFINED', "Pas de spécificité choisie donc par defaut aucune");
  $valuesRev['lots']['0']['specificite'] = $drevConfig->getSpecificites()['bio'];
}

$form->bind($valuesRev);
$t->ok($form->isValid(), "Le formulaire est valide");
$form->save();

$t->is(count($drev->lots), 2, "Les deux lots sont conservés dans la DRev");
$t->is($drev->lots[0]->numero_logement_operateur, $valuesRev['lots']['0']['numero_logement_operateur'], "Le numéro de cuve du lot 1 est bien enregistré");
$t->is($drev->lots[0]->volume, $valuesRev['lots']['0']['volume'], "Le volume du lot 1 est bien enregistré");
$t->is($drev->lots[0]->destination_type, $valuesRev['lots']['0']['destination_type'], "Le type de destination lot 1 est bien enregistré");
$t->is($drev->lots[0]->destination_date, join('-', array_reverse(explode('/', $valuesRev['lots']['0']['destination_date']))), "La date de destination du lot 1 est bien enregistré");
$t->is($drev->lots[0]->produit_hash, $valuesRev['lots']['0']['produit_hash'], "La hash du produit du lot 1 est bien enregistré");
$t->is($drev->lots[0]->produit_libelle, $produit1->getLibelle(), "Le libellé du produit du lot 1 est bien enregistré");
$t->is($drev->lots[0]->millesime, $valuesRev['lots']['0']['millesime'], "Le millesime du lot 1 est bien enregistré");
$t->is($drev->lots[0]->statut, Lot::STATUT_PRELEVABLE, "Le statut du lot 1 est bien enregistré");
$t->is($drev->lots[0]->document_fils, null, "Le lot n'a pas de fils");
$t->ok($drev->lots[0]->isAffectable(), "Le lot est affectable");
$t->ok(!$drev->lots[0]->isAffecte(), "Le lot est affectable");


if($drev->storeEtape(DrevEtapes::ETAPE_VALIDATION)) {
    $drev->save();
}

$t->comment("Étape validation");

$validation = new DRevValidation($drev);
$erreurs = $validation->getPointsByCodes('erreur');
$vigilances = $validation->getPointsByCodes('vigilance');

$t->is(array_key_first($erreurs), 'lot_volume_total_depasse', "Le volume total du lot est dépassé");
$t->is(count($erreurs), 1, 'Il y a un point bloquant');
$t->is($vigilances, null, "un point de vigilance à la validation");

$drev->lots[0]->volume = 8.2;
$drev->lotsImpactRevendication();
$drev->save();

$validation = new DRevValidation($drev);
$erreurs = $validation->getPointsByCodes('erreur');
$vigilances = $validation->getPointsByCodes('vigilance');

$t->is($erreurs, null, "pas d'erreur à la validation");
$t->is($vigilances, null, "un point de vigilance à la validation");

$t->comment("DRev validée");
$drev->validate();
$drev->save();

$t->is(count($drev->lots), 1, "La DRev validée contient uniquement le lot saisi");
$t->ok(!$drev->mouvements_lots->exist($drev->identifiant), "La DRev non validée ODG ne contient pas de mouvement de lots");

$t->comment("DRev Validée ODG");

$drev->validateOdg();
$drev->save();

$t->is(count($drev->mouvements_lots->get($drev->identifiant)->toArray(true, false)), 2, "La DRev validée contient le mouvement correspondant au lot saisi");

$t->comment("Génération d'un mouvement à partir d'un lot");

$mouvement = $drev->mouvements_lots->get($drev->identifiant)->getFirst();
$lot = $mouvement->getLot();

$t->is($mouvement->getUnicityKey(), $lot->getUnicityKey()."-".KeyInflector::slugify(Lot::STATUT_REVENDIQUE), "Clé unique des mouvements");
$t->is($mouvement->date, $lot->date, "Mouvement date");
$t->is($mouvement->statut, Lot::STATUT_REVENDIQUE, "Mouvement statut");
$t->is($mouvement->numero_dossier, $lot->numero_dossier, "Mouvement numero de dossier");
$t->is($mouvement->numero_archive, $lot->numero_archive, "Mouvement numero d'archive");
$t->is($mouvement->detail, null, "Mouvement détail");
$t->is($mouvement->libelle, $lot->getLibelle(), "Mouvement libellé");
$t->is($mouvement->version, $lot->getVersion(), "Mouvement version");
$t->is($mouvement->document_ordre, "01", "Mouvement numéro d'ordre");
$t->is($mouvement->document_type, DRevClient::TYPE_MODEL, "Mouvement document type");
$t->is($mouvement->document_id, $drev->_id, "Mouvement document id");
$t->is($mouvement->lot_unique_id, $lot->getUnicityKey(), "Mouvement lot unique id");
$t->is($mouvement->lot_hash, $lot->getHash(), "Mouvement lot has");
$t->is($mouvement->declarant_identifiant, $drev->identifiant, "Mouvement declarant identifiant");
$t->is($mouvement->declarant_nom, $drev->declarant->raison_sociale, "Mouvement declarant raison sociale");
$t->is($mouvement->campagne, $drev->getCampagne(), "Mouvement campagne");

$res = MouvementLotView::getInstance()->getByPrelevablePreleveRegionDateIdentifiantDocumentId($drev->campagne, Lot::STATUT_PRELEVABLE, '', $drev->lots[0]->date, $drev->identifiant, $drev->_id);
$t->is(count($res->rows), 1, 'on retrouve le mouvement dans la vue MouvementLot');
$t->is($res->rows[0]->document_id, $drev->_id, 'le mouvement correspond bien à notre drev');
$drevres = DRevClient::getInstance()->find($res->rows[0]->value->origine_document_id);
$t->ok($drevres, 'le mouvement pointe bien sur une Drev existante');
$t->ok( ($drevres instanceof InterfaceMouvementLotsDocument) , 'le mouvement pointe bien vers un document de type InterfaceMouvementLotsDocument');
$lotres = $drevres->get($res->rows[0]->value->origine_hash);
$t->ok($lotres, 'le mouvement correspond bien à un lot');
$mvtres = $drevres->get($res->rows[0]->value->origine_mouvement);
$t->ok($mvtres, 'le mouvement a bien un origine mouvement existant');
$t->ok( ($mvtres instanceof MouvementLots) , 'le mouvement correspond bien à un lot de type MouvementLots');
$t->ok( ($mvtres instanceof InterfaceMouvementLots) , 'le mouvement correspond bien à un lot de type InterfaceMouvementLots');
$t->is($mvtres->origine_mouvement, $res->rows[0]->value->origine_mouvement, "le mouvement l'origine mouvement correspond bien au mouvement");

$t->comment("Test de la synthèse des lots (visu/validation/_recap)");

$synthese = $drev->summerizeProduitsLotsByCouleur();
$t->is(count(array_keys($synthese)), 2, "On a bien toutes les couleurs de la DR en synthèse des lots");
$t->ok(isset($synthese[$drev->lots[0]->getCouleurLibelle()]), "On a bien la couleur du produit 1 en synthèse des lots");
$t->is($synthese[$drev->lots[0]->getCouleurLibelle()]['volume_lots'], 8.2, "On a le bon volume total en synthèse des lots");
$t->is($synthese[$drev->lots[0]->getCouleurLibelle()]['volume_max'], 208.2, "On a le bon volume issu de la dr en synthèse des lots");
$t->is($synthese[$drev->lots[0]->getCouleurLibelle()]['volume_restant'], 200, "On a le bon volume restant en synthèse des lots");

$t->comment("Gestion du prélèvement");
$mvtres->prelever();
$drevres->save();
$t->is($mvtres->statut, Lot::STATUT_PRELEVE,"Le mouvement prelevé est bien indiqué comme tel");
$res = MouvementLotView::getInstance()->getByPrelevablePreleveRegionDateIdentifiantDocumentId($drev->campagne, Lot::STATUT_PRELEVABLE, '', $drev->lots[0]->date, $drev->identifiant, $drev->_id);
$t->is(count($res->rows), 0, 'on retrouve plus le mouvement prelevé dans la vue MouvementLot');

$t->comment("Modificatrice ".$drev->_id."-M01");
$drevBackup = $drev;
$drev_modif = $drev->generateModificative();
$drev_modif->save();
$t->is($drev_modif->_id, $drev->_id.'-M01', "La modification a l'identifiant attendu");
$t->is(count($drev_modif->mouvements_lots), 0, "La Drev modificatrice juste crée a bien aucun mouvement de lots");
$t->is($drev_modif->lots->get(0)->statut, Lot::STATUT_NONPRELEVABLE, "La Drev modificatrice a repris le lot de la drev parente au statut non prélevable");
$t->comment("Suppression de lots");
$lot = $drev_modif->lots->get(0);
$drev_modif->remove('lots');
$drev_modif->add('lots');
$drev_modif->validate();
$drev = $drev_modif->getMother();
$t->is(count($drev_modif->lots), 0, "Le Lot de la DRev modificatrice est correctement supprimé");
$t->is(count($drev_modif->mouvements_lots->get($drev_modif->identifiant)), 0, "Les mvts de lot sont cohérents");
$t->is($drev->lots->get(0)->statut, Lot::STATUT_NONPRELEVABLE, "Validate : La suppression du lot a été répercutée sur la DRev parente (statut passe de prélévable à non prélevable)");
$t->is($drev->mouvements_lots->get($drev->identifiant)->get($drev->lots->get(0)->getUnicityKey())->statut, Lot::STATUT_NONPRELEVABLE, "Validate : Les mvts de lot sont cohérents");
$drev_modif->devalidate();
$drev = $drev_modif->getMother();
$t->is($drev->lots->get(0)->statut, Lot::STATUT_PRELEVABLE, "Devalidate : La suppression du lot a été répercutée sur la DRev parente (statut passe de prélévable à non prélevable)");
$t->is($drev->mouvements_lots->get($drev->identifiant)->get($drev->lots->get(0)->getUnicityKey())->statut, Lot::STATUT_PRELEVABLE, "Devalidate : Les mvts de lot sont cohérents");
$drev_modif->validate();
$drev = $drev_modif->getMother();
$t->is($drev->lots->get(0)->statut, Lot::STATUT_NONPRELEVABLE, "Revalidate : La suppression du lot a été répercutée sur la DRev parente (statut passe de prélévable à non prélevable)");
$t->is($drev->mouvements_lots->get($drev->identifiant)->get($drev->lots->get(0)->getUnicityKey())->statut, Lot::STATUT_NONPRELEVABLE, "Revalidate : Les mvts de lot sont cohérents");
$drev_modif->delete();
$drev = DRevClient::getInstance()->find($drevBackup->_id);
$t->is($drev->lots->get(0)->statut, Lot::STATUT_PRELEVABLE, "Deleted : La suppression du lot a été répercutée sur la DRev parente (statut passe de prélévable à non prélevable)");
$t->is($drev->mouvements_lots->get($drev->identifiant)->get($drev->lots->get(0)->getUnicityKey())->statut, Lot::STATUT_PRELEVABLE, "Deleted : Les mvts de lot sont cohérents");

// Reinit
$drev = $drevBackup;
$drev_modif = $drev->generateModificative();

$t->comment("Ajout de lots");

if($drev_modif->storeEtape(DrevEtapes::ETAPE_LOTS)) {
    $drev_modif->save();
}

$drev_modif->addLot();
$drev_modif->addLot();
$form = new DRevLotsForm($drev_modif);
$defaults = $form->getDefaults();
$valuesRev = array(
    'lots' => $form['lots']->getValue(),
    '_revision' => $drev_modif->_rev,
);

$valuesRev['lots']['1']['numero_logement_operateur'] = "Cuve B";
$valuesRev['lots']['1']['volume'] = 1;
$valuesRev['lots']['1']['destination_type'] = DRevClient::LOT_DESTINATION_VRAC_FRANCE;
$valuesRev['lots']['1']['destination_date'] = '30/11/'.$campagne;
$valuesRev['lots']['1']['produit_hash'] = $produitconfig2->getHash();
$valuesRev['lots']['1']['millesime'] = date('Y') - 1;

$valuesRev['lots']['2'] = $valuesRev['lots']['1'];
$valuesRev['lots']['2']['numero_logement_operateur'] = "Cuve C";
$valuesRev['lots']['2']['produit_hash'] = $produitconfig_horsDR->getHash();

$form->bind($valuesRev);

$t->ok($form->isValid(), "Le formulaire est valide");
$errors = null;
foreach ($form->getErrorSchema() as $key => $err) {
    if ($key) {
        $errors[$key] = $err->getMessage();
    }
}
$t->is($errors, null, "Pas d'erreur dans le formulaire validé");
$form->save();
$t->is($drev_modif->lots[1]->numero_logement_operateur, $valuesRev['lots']['1']['numero_logement_operateur'], "Le numéro de cuve du lot 2 est bien enregistré");
$t->is($drev_modif->lots[1]->volume, $valuesRev['lots']['1']['volume'], "Le volume du lot 2 est bien enregistré");
$t->is($drev_modif->lots[1]->produit_libelle, $produitconfig2->getLibelleComplet(), "Le libellé du produit du lot 2 est bien enregistré");
$t->is($drev_modif->lots[1]->millesime, $valuesRev['lots']['1']['millesime'], "Le millesime du lot 2 est bien enregistré");
$t->is($drev_modif->lots[2]->numero_logement_operateur, $valuesRev['lots']['2']['numero_logement_operateur'], "Le numéro de cuve du lot 3 est bien enregistré");
$t->is($drev_modif->lots[2]->volume, $valuesRev['lots']['2']['volume'], "Le volume du lot 3 est bien enregistré");
$t->is($drev_modif->lots[2]->produit_libelle, $produitconfig_horsDR->getLibelleComplet(), "Le libellé du produit du lot 3 est bien enregistré");
$t->is($drev_modif->lots[2]->millesime, $valuesRev['lots']['2']['millesime'], "Le millesime du lot 3 est bien enregistré");

//$drev_modif->remove('/lots/0');
$drev_modif->save();
$t->is(count($drev_modif->lots), 3, "l'ajout des deux produit a bien impacté le nombre de lots");

if($drev_modif->storeEtape(DrevEtapes::ETAPE_VALIDATION)) {
    $drev_modif->save();
}
$validation = new DRevValidation($drev_modif);
$erreurs = $validation->getPointsByCodes('erreur');
$vigilances = $validation->getPointsByCodes('vigilance');

$t->is($erreurs, null, "pas d'erreur");
$t->is(count($vigilances), 1, "un point de vigilances");
$t->ok(isset($vigilances['lot_igp_inexistant_dans_dr_warn']), "le point vigilance indique que le produit du 2d lot ne fait pas partie de la DR comme attendu");

$form = new DRevLotsForm($drev_modif);
$valuesRev = array(
    'lots' => $form['lots']->getValue(),
    '_revision' => $drev_modif->_rev,
);
$valuesRev['lots']['2']['produit_hash'] = $produitconfig1->getHash();
$form->bind($valuesRev);
$t->ok($form->isValid(), "Le formulaire de changement de nom du produit est valide");
$form->save();

$validation = new DRevValidation($drev_modif);
$erreurs = $validation->getPointsByCodes('erreur');
$vigilances = $validation->getPointsByCodes('vigilance');
$t->is($vigilances, null, "après le changement de produit hors DR, plus de point de vigilances");
$t->is($erreurs, null, "après le changement de produit hors DR, toujours pas d'erreur");

$drev_modif->validate();
$drev_modif->save();
$t->is(count($drev_modif->lots), 3, "Après la validation, le nombre de lots n'a pas changé");
$t->is($drev_modif->lots[0]->produit_libelle, $produitconfig1->getLibelleComplet(), "Après la validation, le lot 1 n'a pas changé");
$t->is($drev_modif->lots[1]->produit_libelle, $produitconfig2->getLibelleComplet(), "Après la validation, le lot 2 n'a pas changé");
$t->is($drev_modif->lots[2]->produit_libelle, $produitconfig1->getLibelleComplet(), "Après la validation, le lot 3 n'a pas changé");
$lotsPrelevables = 0;
foreach($drev_modif->mouvements_lots->{$drev_modif->identifiant} as $k => $mvt) {
  if ($mvt->statut == Lot::STATUT_PRELEVABLE)
    $lotsPrelevables++;
}
$t->is($lotsPrelevables, 2, "La Drev modificatrice validée a bien généré que 2 mouvements de lots prélevables (pour les seuls deux nouveaux lots)");

$t->is($mvt->version, 'M01', 'Le mouvement a le bon numéro de version');
$t->is($mvt->produit_hash, $produitconfig1->getHash(), 'Le mouvement a le bon hash');
$t->is($mvt->statut, Lot::STATUT_PRELEVABLE, 'Le mouvement est prelevable');
$t->is($mvt->declarant_identifiant, $drev_modif->identifiant, 'Le mouvement a le bon identifiant de déclarant');
$t->is($mvt->declarant_nom, $drev->declarant->raison_sociale, 'Le mouvement a le bon nom de déclarant');
$t->is($mvt->statut, Lot::STATUT_PRELEVABLE, 'Le mouvement est prelevable');

$res = MouvementLotView::getInstance()->getByPrelevablePreleveRegionDateIdentifiantDocumentId($drev_modif->campagne, Lot::STATUT_PRELEVABLE, '', $drev_modif->lots[0]->date, $drev->identifiant, $drev_modif->_id);
$t->is(count($res->rows), 2, 'on retrouve plus les 2 mouvements dans la vue MouvementLot');


$drev_modif2 = $drev_modif->generateModificative();

$drev_modif2->remove('/lots/0');
$t->is($drev_modif2->lots[1]->produit_libelle, $produitconfig2->getLibelleComplet(), "Après la suppression du lot 1, le 1er lot qui vient d'être ajouté est à la bonne place");
$t->is($drev_modif2->lots[2]->produit_libelle, $produitconfig1->getLibelleComplet(), "Après la suppression du lot 1, le 2d lot qui vient d'être ajouté est à la bonne place");
$drev_modif2->validate();
$drev_modif2->save();
$t->is($drev_modif2->lots[0]->produit_libelle, $produitconfig2->getLibelleComplet(), "Après la validation de la suppression du lot 1, le 1er lot est à la bonne place");
$t->is($drev_modif2->lots[1]->produit_libelle, $produitconfig1->getLibelleComplet(), "Après la validation de la suppression du lot 1, le 2d lot est à la bonne place");
$lotsPrelevables = 0;
foreach($drev_modif2->mouvements_lots->{$drev_modif2->identifiant} as $k => $mvt) {
  if ($mvt->statut == Lot::STATUT_PRELEVABLE)
    $lotsPrelevables++;
}
$t->is($lotsPrelevables, 0, "La 2d Drev modificatrice validée n'a pas généré de mouvement de lots");
$t->is($mvt->version, 'M02', 'Le mouvement a le bon numéro de version');
$t->is($mvt->produit_hash, $produitconfig1->getHash(), 'Le mouvement a le bon hash');
$t->is($mvt->statut, Lot::STATUT_NONPRELEVABLE, 'Le mouvement est prelevable');
$res = MouvementLotView::getInstance()->getByPrelevablePreleveRegionDateIdentifiantDocumentId($drev->campagne, Lot::STATUT_PRELEVABLE, '', $drev->lots[0]->date, $drev->identifiant, $drev_modif2->_id);
$t->is(count($res->rows), 0, 'on ne retrouve pas le mouvement comme prelevable dans la vue MouvementLot');
$res = MouvementLotView::getInstance()->getByPrelevablePreleveRegionDateIdentifiantDocumentId($drev->campagne, Lot::STATUT_NONPRELEVABLE, '', $drev->lots[0]->date, $drev->identifiant, $drev_modif2->_id);
$t->is(count($res->rows), 0, 'on retrouve le mouvement comme non prelevable dans la vue MouvementLot');

$res = MouvementLotView::getInstance()->getByDeclarantIdentifiant($drev->identifiant, $drev->campagne);
$ok = false;
foreach($res->rows as $r) {
    if ($r->value->origine_document_id == $drev->_id) {
        $ok = true;
    }
}
$t->ok($ok, "La vue de récupération par identifiant fonctionne");
