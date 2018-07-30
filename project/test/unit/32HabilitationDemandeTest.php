<?php

require_once(dirname(__FILE__).'/../bootstrap/common.php');

sfContext::createInstance($configuration);

$t = new lime_test(64);

$viti =  CompteTagsView::getInstance()->findOneCompteByTag('test', 'test_viti')->getEtablissement();

//Suppression des habilitations précédentes
foreach(HabilitationClient::getInstance()->getHistory($viti->identifiant) as $k => $v) {
  $habilitation = HabilitationClient::getInstance()->find($k);
  $habilitation->delete(false);
}

$config = ConfigurationClient::getCurrent();

$produitConfig = null;
$produitConfig2 = null;
foreach($config->getProduitsCahierDesCharges() as $p) {
    if(!$produitConfig) {
        $produitConfig = $p;
    } else {
        $produitConfig2 = $p;
        break;
    }
}

$demandeStatut = "HABILITATION";

$t->comment("Création d'une demande");
$date = (new DateTime("-6 month"))->format('Y-m-d');
$statut = "DEPOT";
$commentaire = "Envoyé par courrier";
$premierCommentaire = $commentaire;
$auteur = "Syndicat";
$activites = array(HabilitationClient::ACTIVITE_VINIFICATEUR, HabilitationClient::ACTIVITE_ELABORATEUR);

$demande = HabilitationClient::getInstance()->createDemandeAndSave($viti->identifiant, $demandeStatut, $produitConfig->getHash(), $activites, $statut, $date, $commentaire,  $auteur);
$habilitation = $demande->getDocument();

$demande2 = HabilitationClient::getInstance()->createDemandeAndSave($viti->identifiant, $demandeStatut, $produitConfig->getHash(), $activites, $statut, $date, $commentaire,  $auteur);

$key2 = $viti->identifiant."-".str_replace("-", "", $date)."02";
$t->is($demande2->getKey(), $key2, "La clé de la seconde demande est : ".$key2);

$idDocHabilitation = 'HABILITATION-'.$viti->identifiant.'-'.str_replace('-', '', $date);
$keyDemande1 = $viti->identifiant."-".str_replace("-", "", $date)."01";
$t->is($habilitation->_id, $idDocHabilitation, "L'id du doc d'habilitation est ".$idDocHabilitation);
$t->ok($demande instanceof HabilitationDemande, "La demande est une instance de HabilitationDemande");
$t->is($demande->getKey(), $keyDemande1, "La clé de la demande est ".$keyDemande1);
$t->is($demande->produit, $produitConfig->getHash(), "La hash produit est ".$produitConfig->getHash());
$t->is($demande->libelle, $produitConfig->getLibelleComplet().": Vinificateur, Élaborateur", "Le libellé produit est ".$produitConfig->getLibelleComplet());
$t->is($demande->activites->toArray(true, false), $activites, "Les activites sont bien stockées");
$t->is($demande->date, $date, "La date du statut est ".$date);
$t->is($demande->date_habilitation, $date, "La date d'habilitation est ".$date);
$t->is($demande->demande, $demandeStatut, "La demande est ".$demandeStatut);
$t->is($demande->commentaire, $commentaire, "La commentaire est ".$commentaire);
$t->is($demande->statut, $statut, "La statut de la demande est ".$statut);

$t->is(count($habilitation->historique), 1, "L'historique de cette habilitation a 1 élément");
$t->is($habilitation->historique->get(0)->iddoc, $habilitation->_id.":".$demande->getHash(), "L'id du doc contient la hash");
$t->is($habilitation->historique->get(0)->description, "La demande d'habilitation \"".$produitConfig->getLibelleComplet().": Vinificateur, Élaborateur\" a été créée au statut \"Dépôt\"", "La description de l'action est ok");
$t->is($habilitation->historique->get(0)->statut, $demande->statut, "Le statut de la demande est dans l'historique");
$t->is($habilitation->historique->get(0)->commentaire, $commentaire, "Le commentaire est ".$commentaire);
$t->is($habilitation->historique->get(0)->date, $date, "La date est ".$date);

$t->comment("Changement de statut de la 1ère demande");

$date = (new DateTime("now - 3 month"))->format('Y-m-d');
$statut = "COMPLET";
$commentaire = "Après 3 relance";
$auteur = "Syndicat";

$demande = HabilitationClient::getInstance()->updateDemandeAndSave($viti->identifiant, $keyDemande1, $date, $statut, $commentaire, $auteur);
$dateEnregistrement = (new DateTime("now - 2 month -15 day"))->format('Y-m-d');
HabilitationClient::getInstance()->triggerDemandeStatutAndSave($demande, $dateEnregistrement, $commentaire, $auteur);
$habilitation = $demande->getDocument();

$idDocHabilitation = 'HABILITATION-'.$viti->identifiant.'-'.str_replace('-', '', $date);
$t->is($habilitation->_id, $idDocHabilitation, "L'id du doc d'habilitation est ".$idDocHabilitation);

$t->is($demande->getKey(), $keyDemande1, "La clé de la demande est ".$keyDemande1);
$t->is($demande->date, $date, "La date du statut est ".$dateEnregistrement);
$t->is($demande->date_habilitation, $date, "La date d'habilitation est ".$date);
$t->is($demande->commentaire, $premierCommentaire, "La commentaire n'a pas bougé");
$t->is($demande->statut, $statut, "La statut de la demande est ".$statut);

$t->is(count($habilitation->historique), 1, "L'historique de cette habilitation a 1 élément");
$t->is($habilitation->historique->get(0)->description, "La demande d'habilitation \"".$produitConfig->getLibelleComplet().": Vinificateur, Élaborateur\" est passée au statut \"Complet\"", "La description de l'action est ok");
$t->is($habilitation->historique->get(0)->commentaire, $commentaire, "Le commentaire est ".$commentaire);
$t->is($habilitation->historique->get(0)->date, $date, "La date est ".$date);

$habilitationLast = HabilitationClient::getInstance()->getLastHabilitation($viti->identifiant);
$demandeLast = $habilitationLast->demandes->get($keyDemande1);
$t->is($demandeLast->statut, 'ENREGISTREMENT', "La statut enregistrement a été créé tout seul");
$t->is($demandeLast->date, $dateEnregistrement, "La date est celle d'aujourd'hui");
$t->is($demandeLast->date_habilitation, $date, "La date d'habilitation est celle du passage au statut complet");
$t->ok($habilitationLast->exist($demande->produit), " a été créé dans l'habilitation");
$habilitationProduit = $habilitationLast->get($demande->produit);
$t->is($habilitationProduit->activites->get($activites[0])->statut, "DEMANDE_HABILITATION", "La première activité est en attente d'habilitation");
$t->is($habilitationProduit->activites->get($activites[0])->date, $date, "La première activité à pour date ".$date);
$t->is($habilitationProduit->activites->get($activites[0])->commentaire, $commentaire, "La première activité est à pour commentaire ".$commentaire);

$t->comment("Validation de la 1ère demande");

$date = (new DateTime("now"))->format('Y-m-d');
$statut = "VALIDE";
$commentaire = "Cool";
$auteur = "Syndicat";

$demande = HabilitationClient::getInstance()->updateDemandeAndSave($viti->identifiant, $keyDemande1, $date, $statut, $commentaire, $auteur);
$habilitation = $demande->getDocument();

$idDocHabilitation = 'HABILITATION-'.$viti->identifiant.'-'.str_replace('-', '', $date);
$t->is($habilitation->_id, $idDocHabilitation, "L'id du doc d'habilitation est ".$idDocHabilitation);

$habilitationLast = HabilitationClient::getInstance()->getLastHabilitation($viti->identifiant);

$t->ok($habilitationLast->exist($demande->produit), " a été créé dans l'habilitation");
$habilitationProduit = $habilitationLast->get($demande->produit);
$t->is($habilitationProduit->activites->get($activites[0])->statut, "HABILITE", "La première activité est habilité");
$t->is($habilitationProduit->activites->get($activites[0])->date, $date, "La première activité à pour date ".$date);
$t->is($habilitationProduit->activites->get($activites[0])->commentaire, $commentaire, "La première activité est à pour commentaire ".$commentaire);

$t->comment("Ajout d'un statut antérieur pour la 1ère demande");

$date = (new DateTime("now -2 month"))->format('Y-m-d');
$statut = "TRANSMIS_OIVR";
$commentaire = "Transmis à l'OIVR";
$auteur = "Syndicat";

$demande = HabilitationClient::getInstance()->updateDemandeAndSave($viti->identifiant, $keyDemande1, $date, $statut, $commentaire, $auteur);
$habilitation = $demande->getDocument();

$idDocHabilitation = 'HABILITATION-'.$viti->identifiant.'-'.str_replace('-', '', $date);
$t->is($habilitation->_id, $idDocHabilitation, "L'id du doc d'habilitation est ".$idDocHabilitation);

$habilitation = $demande->getDocument();
$habilitationLast = HabilitationClient::getInstance()->getLastHabilitation($viti->identifiant);
$t->is($habilitationLast->demandes->get($demande->getKey())->statut, "VALIDE", "Le statut final est toujour VALIDE");

$t->comment("Création d'une 2ème demande");

$date = (new DateTime("-8 month"))->format('Y-m-d');
$statut = "DEPOT";
$commentaire = "";
$auteur = "Syndicat";
$activites = array(HabilitationClient::ACTIVITE_PRODUCTEUR);

$demande = HabilitationClient::getInstance()->createDemandeAndSave($viti->identifiant, $demandeStatut, $produitConfig->getHash(), $activites, $statut, $date, $commentaire, $auteur);

$habilitation = $demande->getDocument();
$keyDemande2 = $demande->getKey();
$idDocHabilitation = 'HABILITATION-'.$viti->identifiant.'-'.str_replace('-', '', $date);
$t->is($habilitation->_id, $idDocHabilitation, "L'id du doc d'habilitation est ".$idDocHabilitation);

$habilitationLast = HabilitationClient::getInstance()->getLastHabilitation($viti->identifiant);
$t->is($habilitationLast->demandes->get($demande->getKey())->toArray(true, false), $demande->toArray(true, false), "La demande a bien été repliquée sur l'habilitation la plus récente");

$t->comment("Changement de statut de la 2ème demande");

$date = (new DateTime("now - 4 month"))->format('Y-m-d');
$statut = "COMPLET";
$commentaire = "Directement";
$auteur = "Syndicat";

$demande = HabilitationClient::getInstance()->updateDemandeAndSave($viti->identifiant, $keyDemande2, $date, $statut, $commentaire, $auteur);
$habilitation = $demande->getDocument();

$idDocHabilitation = 'HABILITATION-'.$viti->identifiant.'-'.str_replace('-', '', $date);
$t->is($habilitation->_id, $idDocHabilitation, "L'id du doc d'habilitation est ".$idDocHabilitation);

$habilitationLast = HabilitationClient::getInstance()->getLastHabilitation($viti->identifiant);
$t->is($habilitationLast->demandes->get($demande->getKey())->toArray(true, false), $demande->toArray(true, false), "La changement de la demande a bien été repliquée sur l'habilitation la plus récente");

$t->comment("Création d'une demande d'habilitation produit par formulaire");

$habilitation = HabilitationClient::getInstance()->getLastHabilitation($viti->identifiant);

$form = new HabilitationDemandeCreationForm($habilitation);

$defaults = $form->getDefaults();

$t->is($defaults, array('_revision' => $habilitation->_rev), "Aucune valeur par défaut");

$values = array(
    '_revision' => $habilitation->_rev,
    'produit' => $produitConfig2->getHash(),
    'activites' => array(HabilitationClient::ACTIVITE_VINIFICATEUR, HabilitationClient::ACTIVITE_ELABORATEUR),
    'demande' => 'RETRAIT',
    'date' => (new DateTime("now - 1 week"))->format('d/m/Y'),
    'statut' => 'DEPOT',
    'commentaire' => "Est venu directement nous voir",
);

$form->bind($values);

$t->ok($form->isValid(), "Le formulaire est valide");

$demande = $form->save();

$habilitation = HabilitationClient::getInstance()->find($demande->getDocument()->_id);
$demande = $habilitation->demandes->get($demande->getKey());
$t->ok($habilitation->demandes->exist($demande->getKey()), "La demande a été créée");
$t->is($demande->produit, $values['produit'], "La hash produit a été enregistrée");
$t->is($demande->activites->toArray(true, false), $values['activites'], "Les activités ont été enregistrées");
$t->is($demande->demande, $values['demande'], "La demande a été enregistrée");
$t->is($demande->date, preg_replace("|([0-9]+)/([0-9]+)/([0-9]+)|", '\3-\2-\1', $values['date']), "La date a été enregistrée");
$t->is($demande->statut, $values['statut'], "Le statut a été enregistrée");
$t->is($habilitation->historique->get(0)->commentaire, $values['commentaire'], "Le commentaire a été enregistrée");

$t->comment("Modification de la demande par formulaire");

$habilitation = HabilitationClient::getInstance()->getLastHabilitation($viti->identifiant);
$demande = $habilitation->demandes->get($demande->getKey());

$form = new HabilitationDemandeEditionForm($demande);

$defaults = $form->getDefaults();

$t->is($defaults, array('_revision' => $habilitation->_rev), "Les valeurs par défaut du formulaire sont diponibles");

$values = array(
    '_revision' => $habilitation->_rev,
    'date' => (new DateTime("now - 1 day"))->format('d/m/Y'),
    'statut' => 'COMPLET',
    'commentaire' => "Le document n'était pas signé",
);

$form->bind($values);

$t->ok($form->isValid(), "Le formulaire est valide");

$demande = $form->save();

$habilitation = HabilitationClient::getInstance()->find($demande->getDocument()->_id);
$demande = $habilitation->demandes->get($demande->getKey());

$t->is($demande->date, preg_replace("|([0-9]+)/([0-9]+)/([0-9]+)|", '\3-\2-\1', $values['date']), "La date a été enregistrée");
$t->is($demande->statut, $values['statut'], "Le statut a été enregistrée");
$t->is($habilitation->historique->get(0)->commentaire, $values['commentaire'], "Le commentaire a été enregistrée");

$habilitation = HabilitationClient::getInstance()->getLastHabilitation($viti->identifiant);
$demande = $habilitation->demandes->get($demande->getKey());

$t->is($demande->statut, "ENREGISTREMENT", "Le statut enregistrement a été créé automatiquement");

$t->comment("Création d'une demande d'habilitation globale");

$habilitation = HabilitationClient::getInstance()->getLastHabilitation($viti->identifiant);

$form = new HabilitationDemandeGlobaleForm($habilitation);

$defaults = $form->getDefaults();

$t->is($defaults, array('_revision' => $habilitation->_rev), "Les valeurs par défaut du formulaire sont diponibles");

$values = array(
    '_revision' => $habilitation->_rev,
    'demande' => 'HABILITATION',
    'date' => (new DateTime("now"))->format('d/m/Y'),
    'statut' => 'COMPLET',
    'commentaire' => "Changement de CVI",
);

$form->bind($values);

$t->ok($form->isValid(), "Le formulaire est valide");

$demandes = $form->save();

$t->is(count($demandes), count($habilitation->getProduitsHabilites()), "La form a générée autant de demande que de produit dans l'habilitation (".count($demandes).")");
