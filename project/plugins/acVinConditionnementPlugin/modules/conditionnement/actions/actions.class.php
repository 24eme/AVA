<?php

class conditionnementActions extends sfActions {


    public function executeCreate(sfWebRequest $request) {
        $etablissement = $this->getRoute()->getEtablissement();
        $this->secureEtablissement(EtablissementSecurity::DECLARANT_CONDITIONNEMENT, $etablissement);

        $campagne = $request->getParameter("campagne", ConfigurationClient::getInstance()->getCampagneManager()->getCurrent());
        $conditionnement = ConditionnementClient::getInstance()->createDoc($etablissement->identifiant, $campagne);
        $conditionnement->save();

        return $this->redirect('conditionnement_edit', $conditionnement);
    }

    public function executeCreatePapier(sfWebRequest $request) {
        $etablissement = $this->getRoute()->getEtablissement();
        $this->secureEtablissement(EtablissementSecurity::DECLARANT_DREV, $etablissement);

        $campagne = $request->getParameter("campagne", ConfigurationClient::getInstance()->getCampagneManager()->getCurrent());
        $conditionnement = ConditionnementClient::getInstance()->createDoc($etablissement->identifiant, $campagne, true);
        $conditionnement->save();

        return $this->redirect('conditionnement_edit', $conditionnement);
    }

    public function executeEdit(sfWebRequest $request) {
        $conditionnement = $this->getRoute()->getConditionnement();

        $this->secure(ConditionnementSecurity::EDITION, $conditionnement);

        if ($conditionnement->exist('etape') && $conditionnement->etape) {
            return $this->redirect('conditionnement_' . $conditionnement->etape, $conditionnement);
        }

        return $this->redirect('conditionnement_exploitation', $conditionnement);
    }

    public function executeDelete(sfWebRequest $request) {
        $conditionnement = $this->getRoute()->getConditionnement();
        $etablissement = $conditionnement->getEtablissementObject();
        $this->secure(ConditionnementSecurity::EDITION, $conditionnement);

        $conditionnement->delete();
        $this->getUser()->setFlash("notice", "La déclaration a été supprimée avec succès.");

        return $this->redirect('declaration_etablissement', array('identifiant' => $etablissement->identifiant, 'campagne' => $conditionnement->campagne));
    }

    public function executeDevalidation(sfWebRequest $request) {
        $conditionnement = $this->getRoute()->getConditionnement();
        if (!$this->getUser()->isAdmin()) {
          $this->secure(ConditionnementSecurity::DEVALIDATION , $conditionnement);
        }

        $conditionnement->validation = null;
        $conditionnement->validation_odg = null;
        foreach ($conditionnement->getProduits() as $produit) {
          if($produit->exist('validation_odg') && $produit->validation_odg){
            $produit->validation_odg = null;
          }
        }
        $conditionnement->add('etape', null);
        $conditionnement->devalidate();
        $conditionnement->save();

        $this->getUser()->setFlash("notice", "La déclaration a été dévalidé avec succès.");

        return $this->redirect($this->generateUrl('conditionnement_edit', $conditionnement));
    }

    public function executeExploitation(sfWebRequest $request) {
        $this->conditionnement = $this->getRoute()->getConditionnement();
        $this->secure(ConditionnementSecurity::EDITION, $this->conditionnement);

        if($this->conditionnement->storeEtape($this->getEtape($this->conditionnement, ConditionnementEtapes::ETAPE_EXPLOITATION))) {
            $this->conditionnement->save();
        }

        $this->etablissement = $this->conditionnement->getEtablissementObject();

        $this->form = new EtablissementForm($this->conditionnement->declarant, array("use_email" => !$this->conditionnement->isPapier()));



        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }

        if (ConditionnementConfiguration::getInstance()->hasExploitationSave()) {
          $this->form->save();
        }

        if ($this->form->hasUpdatedValues() && !$this->conditionnement->isPapier()) {
        	Email::getInstance()->sendNotificationModificationsExploitation($this->conditionnement->getEtablissementObject(), $this->form->getUpdatedValues());
        }

        if ($request->isXmlHttpRequest()) {

            return $this->renderText(json_encode(array("success" => true, "document" => array("id" => $this->etablissement->_id, "revision" => $this->etablissement->_rev))));
        }

        if ($request->getParameter('redirect', null)) {
            return $this->redirect('conditionnement_validation', $this->conditionnement);
        }

        return $this->redirect('conditionnement_lots', $this->conditionnement);
    }

    public function executeLots(sfWebRequest $request)
    {
        $this->conditionnement = $this->getRoute()->getConditionnement();
        $this->secure(ConditionnementSecurity::EDITION, $this->conditionnement);
        $this->isAdmin = $this->getUser()->isAdmin();

        $has = false;
        if(count($this->conditionnement->getLots())){
            $has = true;
        }

        if($this->conditionnement->storeEtape($this->getEtape($this->conditionnement, ConditionnementEtapes::ETAPE_LOTS))) {
            $this->conditionnement->save();
        }

        if (count($this->conditionnement->getLots()) == 0 || current(array_reverse($this->conditionnement->getLots()->toArray()))->produit_hash != null || $request->getParameter('submit') == "add") {
            $this->conditionnement->addLot();
        }
        $this->form = new ConditionnementLotsForm($this->conditionnement);

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }

        $this->form->save();

        if($request->getParameter('submit') == 'add') {
            return $this->redirect($this->generateUrl('conditionnement_lots', $this->conditionnement).'#dernier');
        }

        return $this->redirect('conditionnement_validation', $this->conditionnement);
    }

    public function executeDeleteLots(sfWebRequest $request){
        $this->conditionnement = $this->getRoute()->getConditionnement();
        $this->secure(ConditionnementSecurity::EDITION, $this->conditionnement);

        if($this->conditionnement->getLotByNumArchive($request->getParameter('numArchive')) === null){
          throw new sfException("le lot d'index ".$request->getParameter('numArchive')." n'existe pas ");
        }

        $lot = $this->conditionnement->getLotByNumArchive($request->getParameter('numArchive'));
        // $lotCheck = MouvementLotView::getInstance()->getDegustationMouvementLot($this->conditionnement->identifiant, $lot->numero_archive, $this->conditionnement->campagne);
        // if($lotCheck){
        //   throw new sfException("le lot de numero d'archive ".$request->getParameter('numArchive').
        //   " ne peut pas être supprimé car associé à un document son id :\n".$lotCheck->id_document);
        // }

        if($lot){
            $this->conditionnement->remove($lot->getHash());
        }

        $this->conditionnement->save();
        return $this->redirect('conditionnement_lots', $this->conditionnement);

    }

    public function executeValidation(sfWebRequest $request) {
        $this->conditionnement = $this->getRoute()->getConditionnement();
        $this->secure(ConditionnementSecurity::EDITION, $this->conditionnement);
        $this->isAdmin = $this->getUser()->isAdmin();

        if($this->conditionnement->storeEtape($this->getEtape($this->conditionnement, ConditionnementEtapes::ETAPE_VALIDATION))) {
            $this->conditionnement->save();
        }

        $this->conditionnement->cleanDoc();

        $this->validation = new ConditionnementValidation($this->conditionnement);

        $this->form = new ConditionnementValidationForm($this->conditionnement, array(), array('isAdmin' => $this->isAdmin, 'engagements' => $this->validation->getPoints(ConditionnementValidation::TYPE_ENGAGEMENT)));

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        if (!$this->validation->isValide() && $this->conditionnement->isTeledeclare() && !$this->getUser()->hasConditionnementAdmin()) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }
        $this->form->save();
        $dateValidation = date('c');

        if($this->form->getValue("date")) {
            $dt = new DateTime($this->form->getValue("date"));
            $dateValidation = $dt->modify('+1 minute')->format('c');
        }

        $this->conditionnement->validate($dateValidation);
        $this->conditionnement->cleanLots();
        $this->conditionnement->save();

        if($this->getUser()->hasConditionnementAdmin() && ConditionnementConfiguration::getInstance()->hasValidationOdgRegion()) {
            $this->getUser()->setFlash("notice", "La déclaration de conditionnement a été validée, elle devra être approuvée par l'ensemble des ODG concernées");

            return $this->redirect('conditionnement_visualisation', $this->conditionnement);
        }

        if($this->getUser()->hasConditionnementAdmin() && $this->conditionnement->isPapier()) {
            $this->conditionnement->validateOdg();
            $this->conditionnement->cleanLots();
            $this->conditionnement->save();
            $this->getUser()->setFlash("notice", "La déclaration de conditionnement papier a été validée et approuvée, un email a été envoyé au déclarant");

            return $this->redirect('conditionnement_visualisation', $this->conditionnement);
        }

        if($this->getUser()->hasConditionnementAdmin()) {
            $this->conditionnement->validateOdg();
            $this->conditionnement->save();
            $this->getUser()->setFlash("notice", "La déclaration de conditionnement a été validée et approuvée");

            return $this->redirect('conditionnement_visualisation', $this->conditionnement);
        }

        if(ConditionnementConfiguration::getInstance()->hasValidationOdgAuto() && !$this->validation->hasPoints()) {
            $this->conditionnement->validateOdg();
            $this->conditionnement->save();
        }

        //Email::getInstance()->sendConditionnementValidation($this->conditionnement);

        return $this->redirect('conditionnement_confirmation', $this->conditionnement);
    }

    public function executeValidationAdmin(sfWebRequest $request) {
        $this->conditionnement = $this->getRoute()->getConditionnement();
        $this->secure(array(ConditionnementSecurity::VALIDATION_ADMIN), $this->conditionnement);
        $this->regionParam = $request->getParameter('region',null);

        $this->conditionnement->validateOdg(null,$this->regionParam);
        $this->conditionnement->save();

        $mother = $this->conditionnement->getMother();
        while ($mother) {
            $mother->validateOdg(null, $this->regionParam);
            $mother->save();
            $mother = $mother->getMother();
        }

        if($this->conditionnement->validation_odg) {
            Email::getInstance()->sendConditionnementValidation($this->conditionnement);
            $this->getUser()->setFlash("notice", "La déclaration a été approuvée. Un email a été envoyé au télédéclarant.");
        }

        $service = $request->getParameter("service");
        $params = array('sf_subject' => $this->conditionnement, 'service' => isset($service) ? $service : null);
        if($this->regionParam){
          $params = array_merge($params,array('region' => $this->regionParam));
        }
        return $this->redirect('conditionnement_visualisation', $params);
    }



    public function executeConfirmation(sfWebRequest $request) {
        $this->conditionnement = $this->getRoute()->getConditionnement();
        $this->secure(ConditionnementSecurity::VISUALISATION, $this->conditionnement);
    }

    public function executeVisualisation(sfWebRequest $request) {
        $this->conditionnement = $this->getRoute()->getConditionnement();
        $this->secure(ConditionnementSecurity::VISUALISATION, $this->conditionnement);
        $this->isAdmin = $this->getUser()->isAdmin();

        $this->service = $request->getParameter('service');

        $this->regionParam = $request->getParameter('region',null);
        if (!$this->regionParam && $this->getUser()->getCompte() && $this->getUser()->getCompte()->exist('region')) {
            $this->regionParam = $this->getUser()->getCompte()->region;
        }
        $this->form = null;
        if($this->getUser()->hasConditionnementAdmin() || $this->conditionnement->validation) {
            $this->validation = new ConditionnementValidation($this->conditionnement);
            $this->form = new ConditionnementValidationForm($this->conditionnement, array(), array('isAdmin' => $this->isAdmin, 'engagements' => $this->validation->getPoints(ConditionnementValidation::TYPE_ENGAGEMENT)));
        }


        $this->dr = DRClient::getInstance()->findByArgs($this->conditionnement->identifiant, $this->conditionnement->campagne);
        if (!$request->isMethod(sfWebRequest::POST)) {
          return sfView::SUCCESS;
        }
        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }

        $this->form->save();

        if($this->isAdmin && $this->conditionnement->isValidee() && $this->conditionnement->isValideeODG() === false){
          return $this->redirect('conditionnement_validation_admin', $this->conditionnement);
        }

        return $this->redirect('conditionnement_visualisation', $this->conditionnement);
    }



    public function executeModificative(sfWebRequest $request) {
        $conditionnement = $this->getRoute()->getConditionnement();

        $conditionnement_modificative = $conditionnement->generateModificative();
        $conditionnement_modificative->save();
        if(ConfigurationClient::getCurrent()->declaration->isRevendicationParLots()){
          return $this->redirect('conditionnement_lots', $conditionnement_modificative);
        }

        return $this->redirect('conditionnement_edit', $conditionnement_modificative);
    }

    public function executePDF(sfWebRequest $request) {
        $conditionnement = $this->getRoute()->getConditionnement();
        $this->secure(ConditionnementSecurity::PDF, $conditionnement);

        if (!$conditionnement->validation) {
            $conditionnement->cleanDoc();
        }

        $this->document = new ExportConditionnementPdf($conditionnement, $this->getRequestParameter('region', null), $this->getRequestParameter('output', 'pdf'), false);
        $this->document->setPartialFunction(array($this, 'getPartial'));

        if ($request->getParameter('force')) {
            $this->document->removeCache();
        }

        $this->document->generate();

        $this->document->addHeaders($this->getResponse());

        return $this->renderText($this->document->output());
    }

    public function executeMain()
    {
    }

    protected function getEtape($conditionnement, $etape) {
        $conditionnementEtapes = ConditionnementEtapes::getInstance();
        if (!$conditionnement->exist('etape')) {
            return $etape;
        }
        return ($conditionnementEtapes->isLt($conditionnement->etape, $etape)) ? $etape : $conditionnement->etape;
    }

    protected function sendConditionnementValidation($conditionnement) {
        $pdf = new ExportConditionnementPdf($conditionnement, null, 'pdf', true);
        $pdf->setPartialFunction(array($this, 'getPartial'));
        $pdf->removeCache();
        $pdf->generate();
        Email::getInstance()->sendConditionnementValidation($conditionnement);
    }

    protected function sendConditionnementConfirmee($conditionnement) {
        Email::getInstance()->sendConditionnementConfirmee($conditionnement);
    }

    protected function secure($droits, $doc) {
        if (!ConditionnementSecurity::getInstance($this->getUser(), $doc)->isAuthorized($droits)) {

            return $this->forwardSecure();
        }
    }

    protected function secureEtablissement($droits, $etablissement) {
        if (!EtablissementSecurity::getInstance($this->getUser(), $etablissement)->isAuthorized($droits)) {

            return $this->forwardSecure();
        }
    }

    protected function forwardSecure() {
        $this->context->getController()->forward(sfConfig::get('sf_secure_module'), sfConfig::get('sf_secure_action'));

        throw new sfStopException();
    }

}
