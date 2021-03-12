<?php

require_once(dirname(__FILE__).'/../bootstrap/common.php');

if ($application != 'igp13') {
    $t = new lime_test(1);
    $t->ok(true, "pass AOC");
    return;
}

$t = new lime_test(35);

$campagne = (date('Y')-1)."";
$degust_date = $campagne.'-09-01 12:45';
$degust_date_fr = '01/09/'.$campagne;
$degust_time_fr = '12:45';
$provenance = 'DREV';
$viti =  CompteTagsView::getInstance()->findOneCompteByTag('test', 'test_viti')->getEtablissement();
$degust =  CompteTagsView::getInstance()->findOneCompteByTag('automatique', 'degustateur_porteur_de_memoire');

foreach(DRevClient::getInstance()->getHistory($viti->identifiant, acCouchdbClient::HYDRATE_ON_DEMAND) as $k => $v) {
    DRevClient::getInstance()->deleteDoc(DRevClient::getInstance()->find($k, acCouchdbClient::HYDRATE_JSON));
}
$docid = "DEGUSTATION-".str_replace("-", "", preg_replace("/(.+) (.+):(.+)$/","$1$2$3",$degust_date))."-SYNDICAT-VIGNERONS-ARLES";
$doc = acCouchdbManager::getClient()->find($docid);
if ($doc) {
    $doc->delete();
}

$config = ConfigurationClient::getCurrent();
$produitconfig1 = null;
foreach($config->getProduits() as $produitconfig) {
    if(!$produitconfig->getRendement()) {
        continue;
    }
    if(!$produitconfig1) {
        $produitconfig1 = $produitconfig->getCepage();
        continue;
    }
    break;
}
$produitconfig_hash1 = $produitconfig1->getHash();
$commissions = DegustationConfiguration::getInstance()->getCommissions();

$t->comment("prépartion avec une DRev");
$drev = DRevClient::getInstance()->createDoc($viti->identifiant, $campagne);
$drev->save();
$produit1 = $drev->addProduit($produitconfig_hash1);
$produit1->superficie_revendique = 200;
$produit1->recolte->superficie_total = 200;
$produit1->volume_revendique_issu_recolte = 80;
$drev->addLot();
$drev->lots[0]->numero_logement_operateur = '1';
$drev->lots[0]->volume = 1;
$drev->lots[1] = clone $drev->lots[0];
$drev->lots[1]->numero_logement_operateur = '2';
$drev->lots[1]->volume = 2;
$drev->validate();
$drev->validateOdg();
$drev->save();
$t->comment($drev->_id);
$res_mvt = MouvementLotView::getInstance()->getByStatut(Lot::STATUT_AFFECTABLE);
$t->is(count($res_mvt->rows), 2, 'on a au moins un mouvement de lot prélevable');

$t->comment("Test de la dégustation : $docid");
$t->comment("Création de la dégustation");
$degustation = new Degustation();
$form = new DegustationCreationForm($degustation);
$values = array('date' => $degust_date_fr, 'time' => $degust_time_fr, 'lieu' => $commissions[0], 'provenance' => $provenance);

$form->bind($values);
$t->ok($form->isValid(), "Le formulaire de création est valide");
$degustation = $form->save();
$t->ok($degustation->_id, "la création donne un id à la degustation");
$t->is($degustation->_id, $docid, "doc id");

$degustation = DegustationClient::getInstance()->find($degustation->_id);
$t->is($degustation->date, $degust_date, "La date de la degustation est la bonne");
$t->is($degustation->lieu, $commissions[0], "La commission de la degustation est la bonne");
$t->comment("Prélèvement");
$form = new DegustationPrelevementLotsForm($degustation);


$valuesRev = array(
    'lots' => $form['lots']->getValue(),
    '_revision' => $degustation->_rev,
);
$lot_key1 = null;
$lot_key2 = null;

foreach($res_mvt->rows as $item) {
    if (!$lot_key1) {
        $lot_key1 = $item->key[MouvementLotView::KEY_LOT_UNIQUE_ID];
        continue;
    }
    if (!$lot_key2) {
        $lot_key2 = $item->key[MouvementLotView::KEY_LOT_UNIQUE_ID];
        $lot_mvt2 = $item->value;
        break;
    }
}

$t->ok(isset($valuesRev['lots'][$lot_key2]), 'On retrouve le lot dans le formulaire sur la base de la vue');

$t->comment('On décoche les lots et on en sélectionne qu\'un');
foreach ($valuesRev['lots'] as &$lot) {
    unset($lot['preleve']);
}
$valuesRev['lots'][$lot_key2]['preleve'] = 1;

$form->bind($valuesRev);
$form->save();
$degustation = DegustationClient::getInstance()->find($degustation->_id);

$t->is(count($degustation->lots), 1, 'Il y a un lot dans la dégustation');
$t->ok($degustation->lots[0], 'Le lot indiqué comme prelevé est bien celui qui est enregistré');
$t->is($degustation->lots[0]->volume, $lot_mvt2->volume, 'Le lot a le bon volume');
$t->is($degustation->lots[0]->numero_logement_operateur, $lot_mvt2->numero_logement_operateur, 'Le lot a le bon numero de cuve');
$t->is($degustation->lots[0]->origine_mouvement, $lot_mvt2->origine_mouvement, 'Le lot a la bonne origine de mouvement');
$t->is($degustation->lots[0]->origine_document_id, $drev->_id, "Le lot a le bon document d'origine");
$t->is($degustation->lots[0]->declarant_identifiant, $drev->identifiant, 'Le lot a le bon declarant');
$t->is($degustation->lots[0]->declarant_nom, $drev->declarant->raison_sociale, 'Le lot a le bon nom de declarant');
$t->is($degustation->lots[0]->statut, Lot::STATUT_ATTENTE_PRELEVEMENT, 'Le lot a le bon statut');
$degustation->generateMouvementsLots();
$t->is(count($degustation->mouvements_lots->get($drev->identifiant)->toArray(true, false)), 2, 'La génération de mouvement a produit deux mouvements');
foreach($degustation->mouvements_lots->get($drev->identifiant) as $k => $mvt) {
    $mouvement[$mvt->statut] = $mvt;
}
$t->is($mouvement[Lot::STATUT_ATTENTE_PRELEVEMENT]->document_id, $degustation->_id, 'le mvt lot permet de retrouver la degustation via id_document');
$t->is($mouvement[Lot::STATUT_ATTENTE_PRELEVEMENT]->statut, Lot::STATUT_ATTENTE_PRELEVEMENT, "le mvt lot du lot est en attente de prélèvement");
$t->is($mouvement[Lot::STATUT_AFFECTE_DEST]->statut, Lot::STATUT_AFFECTE_DEST, "le deuxième mvt lot du lot est affecté destination");

$t->comment("Prélévé");

$form = new DegustationPreleveLotsForm($degustation);
$defaults = $form->getDefaults();
$t->is($defaults['lots'][0]['preleve'], false, "Le lot est marqué comme non prélevé dans le form");
$valuesRev = array(
    '_revision' => $degustation->_rev,
    'lots' => array()
);

$valuesRev['lots'][0]['preleve'] = true;

$form->bind($valuesRev);
$form->save();
$degustation = DegustationClient::getInstance()->find($degustation->_id);
$t->is($degustation->lots[0]->statut, Lot::STATUT_PRELEVE, 'Le lot est marqué comme prélevé');

$degustation->generateMouvementsLots();
$t->is(count($degustation->mouvements_lots->{$drev->identifiant}), 3, 'La génération a généré 3 mouvements');

$form = new DegustationPreleveLotsForm($degustation);
$defaults = $form->getDefaults();

$t->is($defaults['lots'][0]['preleve'], true, "Le lot est marqué comme prélevé dans le form");

$t->comment('Changement de logement');
$degustation->updateLotLogement($degustation->lots[0], $degustation->lots[0]->numero_logement_operateur + 1);
$t->is($degustation->lots[0]->numero_logement_operateur, $lot_mvt2->numero_logement_operateur + 1, "Le changement de logement est effectif dans la dégustation");

$t->comment("Dégustateurs");
$formPorteurDeMemoire = new DegustationSelectionDegustateursForm($degustation, array(), array('college' => 'degustateur_porteur_de_memoire'));
$defaultsPorteurDeMemoire = $formPorteurDeMemoire->getDefaults();

$t->ok(isset($defaultsPorteurDeMemoire['degustateurs']['degustateur_porteur_de_memoire'][$degust->_id]), 'Notre dégustateur est dans le formulaire comme porteur de mémoire');
$valuesRev = array(
    '_revision' => $degustation->_rev,
);

$valuesRev['degustateurs']['degustateur_porteur_de_memoire'][$degust->_id]['selectionne'] = 1;
$formPorteurDeMemoire->bind($valuesRev);
$formPorteurDeMemoire->save();
$t->is(count($degustation->degustateurs->degustateur_porteur_de_memoire), 1, 'On a bien notre dégustateur porteur de mémoire');

$degustation = DegustationClient::getInstance()->find($degustation->_id);

$formTechnicien = new DegustationSelectionDegustateursForm($degustation, array(), array('college' => 'degustateur_technicien'));
$defaultsTechnicien = $formTechnicien->getDefaults();
$t->ok(isset($defaultsTechnicien['degustateurs']['degustateur_technicien'][$degust->_id]), 'Notre dégustateur est dans le formulaire comme technicien');
$valuesRev = array(
    '_revision' => $degustation->_rev,
);

$valuesRev['degustateurs']['degustateur_technicien'][$degust->_id]['selectionne'] = 1;
$formTechnicien->bind($valuesRev);
$formTechnicien->save();
$t->is(count($degustation->degustateurs->degustateur_technicien), 1, 'On a bien le dégustateur technicien');

$degustation = DegustationClient::getInstance()->find($degustation->_id);

$formUsager = new DegustationSelectionDegustateursForm($degustation, array(), array('college' => 'degustateur_usager_du_produit'));
$defaultsUsager = $formUsager->getDefaults();
$t->ok(isset($defaultsUsager['degustateurs']['degustateur_usager_du_produit'][$degust->_id]), 'Notre dégustateur est dans le formulaire comme usager du produit');

$valuesRev = array(
    '_revision' => $degustation->_rev,
);

$valuesRev['degustateurs']['degustateur_usager_du_produit'][$degust->_id]['selectionne'] = 1;

$formUsager->bind($valuesRev);
$formUsager->save();

$t->is(count($degustation->degustateurs->degustateur_usager_du_produit), 1, 'On a bien le dégustateur usager du produit');

$degustation = DegustationClient::getInstance()->find($degustation->_id);
$t->is(count($degustation->degustateurs), 3, 'On a bien les trois collèges');


$t->comment('Présence dégustateur');
$t->comment('On confirme les deux premiers degustateurs');
$degustation->degustateurs->degustateur_usager_du_produit->get($degust->_id)->add('confirmation', 1);
$degustation->degustateurs->degustateur_technicien->get($degust->_id)->add('confirmation', 1);

$t->is($degustation->hasAllDegustateursConfirmation(), false, "Les dégustateurs n'ont pas tous signalé leurs présence");

$t->comment('On confirme le dernier degustateur');
$degustation->degustateurs->degustateur_porteur_de_memoire->get($degust->_id)->add('confirmation', 1);

$t->is($degustation->hasAllDegustateursConfirmation(), true, "Les dégustateurs ont tous signalé leurs présence");
