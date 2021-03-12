<?php

class transactionActions extends sfActions {


    public function executeCreate(sfWebRequest $request) {
        $etablissement = $this->getRoute()->getEtablissement();
        $this->secureEtablissement(EtablissementSecurity::DECLARANT_TRANSACTION, $etablissement);

        $campagne = $request->getParameter("campagne", ConfigurationClient::getInstance()->getCampagneManager()->getCurrent());
        $transaction = TransactionClient::getInstance()->createDoc($etablissement->identifiant, $campagne);
        $transaction->save();

        return $this->redirect('transaction_edit', $transaction);
    }

    public function executeCreatePapier(sfWebRequest $request) {
        $etablissement = $this->getRoute()->getEtablissement();
        $this->secureEtablissement(EtablissementSecurity::DECLARANT_TRANSACTION, $etablissement);

        $campagne = $request->getParameter("campagne", ConfigurationClient::getInstance()->getCampagneManager()->getCurrent());
        $transaction = TransactionClient::getInstance()->createDoc($etablissement->identifiant, $campagne, true);
        $transaction->save();

        return $this->redirect('transaction_edit', $transaction);
    }

    public function executeEdit(sfWebRequest $request) {
        $transaction = $this->getRoute()->getTransaction();

        $this->secure(TransactionSecurity::EDITION, $transaction);

        if ($transaction->exist('etape') && $transaction->etape) {
            return $this->redirect('transaction_' . $transaction->etape, $transaction);
        }

        return $this->redirect('transaction_exploitation', $transaction);
    }

    public function executeDelete(sfWebRequest $request) {
        $transaction = $this->getRoute()->getTransaction();
        $etablissement = $transaction->getEtablissementObject();
        $this->secure(TransactionSecurity::EDITION, $transaction);

        $transaction->delete();
        $this->getUser()->setFlash("notice", "La déclaration a été supprimée avec succès.");

        return $this->redirect('declaration_etablissement', array('identifiant' => $etablissement->identifiant, 'campagne' => $transaction->campagne));
    }

    public function executeDevalidation(sfWebRequest $request) {
        $transaction = $this->getRoute()->getTransaction();
        if (!$this->getUser()->isAdmin()) {
          $this->secure(TransactionSecurity::DEVALIDATION , $transaction);
        }

        $transaction->validation = null;
        $transaction->validation_odg = null;
        foreach ($transaction->getProduits() as $produit) {
          if($produit->exist('validation_odg') && $produit->validation_odg){
            $produit->validation_odg = null;
          }
        }
        $transaction->add('etape', null);
        $transaction->devalidate();
        $transaction->save();

        $this->getUser()->setFlash("notice", "La déclaration a été dévalidé avec succès.");

        return $this->redirect($this->generateUrl('transaction_edit', $transaction));
    }

    public function executeExploitation(sfWebRequest $request) {
        $this->transaction = $this->getRoute()->getTransaction();
        $this->secure(TransactionSecurity::EDITION, $this->transaction);

        if($this->transaction->storeEtape($this->getEtape($this->transaction, TransactionEtapes::ETAPE_EXPLOITATION))) {
            $this->transaction->save();
        }

        $this->etablissement = $this->transaction->getEtablissementObject();

        $this->form = new EtablissementForm($this->transaction->declarant, array("use_email" => !$this->transaction->isPapier()));



        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }

        if (TransactionConfiguration::getInstance()->hasExploitationSave()) {
          $this->form->save();
        }

        if ($this->form->hasUpdatedValues() && !$this->transaction->isPapier()) {
        	Email::getInstance()->sendNotificationModificationsExploitation($this->transaction->getEtablissementObject(), $this->form->getUpdatedValues());
        }

        if ($request->isXmlHttpRequest()) {

            return $this->renderText(json_encode(array("success" => true, "document" => array("id" => $this->etablissement->_id, "revision" => $this->etablissement->_rev))));
        }

        if ($request->getParameter('redirect', null)) {
            return $this->redirect('transaction_validation', $this->transaction);
        }

        return $this->redirect('transaction_lots', $this->transaction);
    }

    public function executeLots(sfWebRequest $request)
    {
        $this->transaction = $this->getRoute()->getTransaction();
        $this->secure(TransactionSecurity::EDITION, $this->transaction);
        $this->isAdmin = $this->getUser()->isAdmin();

        $has = false;
        if(count($this->transaction->getLots())){
            $has = true;
        }

        if($this->transaction->storeEtape($this->getEtape($this->transaction, TransactionEtapes::ETAPE_LOTS))) {
            $this->transaction->save();
        }

        if (count($this->transaction->getLots()) == 0 || current(array_reverse($this->transaction->getLots()->toArray()))->produit_hash != null || $request->getParameter('submit') == "add") {
            $this->transaction->addLot();
        }
        $this->form = new TransactionLotsForm($this->transaction);

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }

        $this->form->save();

        if($request->getParameter('submit') == 'add') {
            return $this->redirect($this->generateUrl('transaction_lots', $this->transaction).'#dernier');
        }

        return $this->redirect('transaction_validation', $this->transaction);
    }

    public function executeDeleteLots(sfWebRequest $request){
        $this->transaction = $this->getRoute()->getTransaction();
        $this->secure(TransactionSecurity::EDITION, $this->transaction);

        if($this->transaction->getLotByNumArchive($request->getParameter('numArchive')) === null){
          throw new sfException("le lot d'index ".$request->getParameter('numArchive')." n'existe pas ");
        }

        $lot = $this->transaction->getLotByNumArchive($request->getParameter('numArchive'));
        // $lotCheck = MouvementLotView::getInstance()->getDegustationMouvementLot($this->transaction->identifiant, $lot->numero_archive, $this->transaction->campagne);
        // if($lotCheck){
        //   throw new sfException("le lot de numero d'archive ".$request->getParameter('numArchive').
        //   " ne peut pas être supprimé car associé à un document son id :\n".$lotCheck->id_document);
        // }

        if($lot){
            $this->transaction->remove($lot->getHash());
        }

        $this->transaction->save();
        return $this->redirect('transaction_lots', $this->transaction);

    }

    public function executeValidation(sfWebRequest $request) {
        $this->transaction = $this->getRoute()->getTransaction();
        $this->secure(TransactionSecurity::EDITION, $this->transaction);
        $this->isAdmin = $this->getUser()->isAdmin();

        if($this->transaction->storeEtape($this->getEtape($this->transaction, TransactionEtapes::ETAPE_VALIDATION))) {
            $this->transaction->save();
        }

        $this->validation = new TransactionValidation($this->transaction);

        $this->form = new TransactionValidationForm($this->transaction, array(), array('isAdmin' => $this->isAdmin, 'engagements' => $this->validation->getPoints(TransactionValidation::TYPE_ENGAGEMENT)));

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        if (!$this->validation->isValide() && $this->transaction->isTeledeclare() && !$this->getUser()->hasTransactionAdmin()) {

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

        $this->transaction->validate($dateValidation);
        $this->transaction->cleanLots();
        $this->transaction->save();

        if($this->getUser()->hasTransactionAdmin() && TransactionConfiguration::getInstance()->hasValidationOdgRegion()) {
            $this->getUser()->setFlash("notice", "La déclaration de transaction a été validée, elle devra être approuvée par l'ensemble des ODG concernées");

            return $this->redirect('transaction_visualisation', $this->transaction);
        }

        if($this->getUser()->hasTransactionAdmin() && $this->transaction->isPapier()) {
            $this->transaction->validateOdg();
            $this->transaction->cleanLots();
            $this->transaction->save();
            $this->getUser()->setFlash("notice", "La déclaration de transaction papier a été validée et approuvée, un email a été envoyé au déclarant");

            return $this->redirect('transaction_visualisation', $this->transaction);
        }

        if($this->getUser()->hasTransactionAdmin()) {
            $this->transaction->validateOdg();
            $this->transaction->save();
            $this->getUser()->setFlash("notice", "La déclaration de transaction a été validée et approuvée");

            return $this->redirect('transaction_visualisation', $this->transaction);
        }

        if(TransactionConfiguration::getInstance()->hasValidationOdgAuto() && !$this->validation->hasPoints()) {
            $this->transaction->validateOdg();
            $this->transaction->save();
        }

        //Email::getInstance()->sendTransactionValidation($this->transaction);

        return $this->redirect('transaction_confirmation', $this->transaction);
    }

    public function executeValidationAdmin(sfWebRequest $request) {
        $this->transaction = $this->getRoute()->getTransaction();
        $this->secure(array(TransactionSecurity::VALIDATION_ADMIN), $this->transaction);
        $this->regionParam = $request->getParameter('region',null);

        $this->transaction->validateOdg(null,$this->regionParam);
        $this->transaction->save();

        $mother = $this->transaction->getMother();
        while ($mother) {
            $mother->validateOdg(null, $this->regionParam);
            $mother->save();
            $mother = $mother->getMother();
        }

        if($this->transaction->validation_odg) {
            Email::getInstance()->sendTransactionValidation($this->transaction);
            $this->getUser()->setFlash("notice", "La déclaration a été approuvée. Un email a été envoyé au télédéclarant.");
        }

        $service = $request->getParameter("service");
        $params = array('sf_subject' => $this->transaction, 'service' => isset($service) ? $service : null);
        if($this->regionParam){
          $params = array_merge($params,array('region' => $this->regionParam));
        }
        return $this->redirect('transaction_visualisation', $params);
    }



    public function executeConfirmation(sfWebRequest $request) {
        $this->transaction = $this->getRoute()->getTransaction();
        $this->secure(TransactionSecurity::VISUALISATION, $this->transaction);
    }

    public function executeVisualisation(sfWebRequest $request) {
        $this->transaction = $this->getRoute()->getTransaction();
        $this->secure(TransactionSecurity::VISUALISATION, $this->transaction);
        $this->isAdmin = $this->getUser()->isAdmin();
        $this->service = $request->getParameter('service');

        $this->regionParam = $request->getParameter('region',null);
        if (!$this->regionParam && $this->getUser()->getCompte() && $this->getUser()->getCompte()->exist('region')) {
            $this->regionParam = $this->getUser()->getCompte()->region;
        }
        $this->form = null;
        if($this->getUser()->hasTransactionAdmin() || $this->transaction->validation) {
            $this->validation = new TransactionValidation($this->transaction);
            $this->form = new TransactionValidationForm($this->transaction, array(), array('isAdmin' => $this->isAdmin, 'engagements' => $this->validation->getPoints(TransactionValidation::TYPE_ENGAGEMENT)));
        }


        $this->dr = DRClient::getInstance()->findByArgs($this->transaction->identifiant, $this->transaction->campagne);
        if (!$request->isMethod(sfWebRequest::POST)) {
          return sfView::SUCCESS;
        }
        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }

        $this->form->save();

        if($this->isAdmin && $this->transaction->isValidee() && $this->transaction->isValideeODG() === false){
          return $this->redirect('transaction_validation_admin', $this->transaction);
        }

        return $this->redirect('transaction_visualisation', $this->transaction);
    }



    public function executeModificative(sfWebRequest $request) {
        $transaction = $this->getRoute()->getTransaction();

        $transaction_modificative = $transaction->generateModificative();
        $transaction_modificative->save();
        if(ConfigurationClient::getCurrent()->declaration->isRevendicationParLots()){
          return $this->redirect('transaction_lots', $transaction_modificative);
        }

        return $this->redirect('transaction_edit', $transaction_modificative);
    }

    public function executePDF(sfWebRequest $request) {
        $transaction = $this->getRoute()->getTransaction();
        $this->secure(TransactionSecurity::PDF, $transaction);

        if (!$transaction->validation) {
            $transaction->cleanDoc();
        }

        $this->document = new ExportTransactionPdf($transaction, $this->getRequestParameter('region', null), $this->getRequestParameter('output', 'pdf'), false);
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

    protected function getEtape($transaction, $etape) {
        $transactionEtapes = TransactionEtapes::getInstance();
        if (!$transaction->exist('etape')) {
            return $etape;
        }
        return ($transactionEtapes->isLt($transaction->etape, $etape)) ? $etape : $transaction->etape;
    }

    protected function sendTransactionValidation($transaction) {
        $pdf = new ExportTransactionPdf($transaction, null, 'pdf', true);
        $pdf->setPartialFunction(array($this, 'getPartial'));
        $pdf->removeCache();
        $pdf->generate();
        Email::getInstance()->sendTransactionValidation($transaction);
    }

    protected function sendTransactionConfirmee($transaction) {
        Email::getInstance()->sendTransactionConfirmee($transaction);
    }

    protected function secure($droits, $doc) {
        if (!TransactionSecurity::getInstance($this->getUser(), $doc)->isAuthorized($droits)) {

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
