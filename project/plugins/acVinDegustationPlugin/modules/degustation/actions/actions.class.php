<?php

class degustationActions extends sfActions {

    public function executeIndex(sfWebRequest $request) {
        $newDegutation = new Degustation();
        $this->form = new DegustationCreationForm($newDegutation);
        $this->lotsPrelevables = DegustationClient::getInstance()->getLotsPrelevables();
        $this->lotsElevages = MouvementLotView::getInstance()->getByStatut(null, Lot::STATUT_ELEVAGE)->rows;
        $this->lotsManquements = MouvementLotView::getInstance()->getByStatut(null, Lot::STATUT_MANQUEMENT_EN_ATTENTE)->rows;

        $this->degustations = DegustationClient::getInstance()->getHistory();

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {
            return sfView::SUCCESS;
        }

        $degustation = $this->form->save();

        return $this->redirect('degustation_prelevement_lots', $degustation);
    }

    public function executePrelevementLots(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->infosDegustation = $this->degustation->getInfosDegustation();
        $this->redirectIfIsValidee();

        if ($this->degustation->storeEtape($this->getEtape($this->degustation, DegustationEtapes::ETAPE_LOTS))) {
            $this->degustation->save();
        }

        $this->form = new DegustationPrelevementLotsForm($this->degustation);

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }

        $this->form->save();

        return ($next = $this->getRouteNextEtape(DegustationEtapes::ETAPE_LOTS))? $this->redirect($next, $this->degustation) : $this->redirect('degustation');
    }

    public function executePreleve(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->infosDegustation = $this->degustation->getInfosDegustation();

        $this->form = new DegustationPreleveLotsForm($this->degustation);

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }

        $this->form->save();

        return $this->redirect('degustation_prelevements_etape', $this->degustation);
    }

    public function executeUpdateLot(sfWebRequest $request)
    {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->lotkey = $request->getParameter('lot');
        $this->lot = $this->degustation->lots->get($request->getParameter('lot'));

        $this->form = new DegustationLotForm($this->lot);

        if ($request->isMethod(sfWebRequest::POST)) {
            $this->form->bind($request->getParameter($this->form->getName()));

            if ($this->form->isValid()) {
                $this->form->save();
                return $this->redirect('degustation_preleve', $this->degustation);
            }
        }
    }

    public function executeUpdateLotLogement(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->lot = $request->getParameter('lot');

        $this->form = new DegustationPreleveUpdateLogementForm($this->degustation, $this->lot);

        if ($request->isMethod(sfWebRequest::POST)) {
            $this->form->bind($request->getParameter($this->form->getName()));

            if ($this->form->isValid()) {
                $this->form->save();
                return $this->redirect('degustation_preleve', $this->degustation);
            }
        }
    }

    public function executeSelectionDegustateurs(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->redirectIfIsValidee();
        $this->infosDegustation = $this->degustation->getInfosDegustation();
        $this->colleges = DegustationConfiguration::getInstance()->getColleges();
        $first_college = array_key_first($this->colleges);

        $this->previous_college = null;
        if(!$this->college = $request->getParameter('college')) {

            return $this->redirect('degustation_selection_degustateurs', array('id' => $this->degustation->_id, 'college' => $first_college));
        }

        $colleges_keys = array_keys($this->colleges);
        $currentCollegeKey = array_search($this->college, $colleges_keys);
        $next_college = ($currentCollegeKey+1 >= count($colleges_keys))? null : $colleges_keys[$currentCollegeKey+1];
        $this->previous_college = ($currentCollegeKey-1 < 0 )? null : $colleges_keys[$currentCollegeKey-1];

        if ($this->degustation->storeEtape($this->getEtape($this->degustation, DegustationEtapes::ETAPE_DEGUSTATEURS))) {
            $this->degustation->save();
        }

        $this->form = new DegustationSelectionDegustateursForm($this->degustation,array(),array('college' => $this->college));

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));
        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }

        $this->form->save();

        if ($request->isXmlHttpRequest()) {

          return $this->renderText(json_encode(array("success" => true, "document" => array("id" => $this->degustation->_id, "revision" => $this->degustation->_rev))));
        }

        if(!$next_college){
          ($next = $this->getRouteNextEtape(DegustationEtapes::ETAPE_DEGUSTATEURS))? $this->redirect($next, $this->degustation) : $this->redirect('degustation');
        }

        return $this->redirect('degustation_selection_degustateurs', array('id' => $this->degustation->_id ,'college' => $next_college));
    }

    public function executeValidation(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->redirectIfIsValidee();
        if ($this->degustation->storeEtape($this->getEtape($this->degustation, DegustationEtapes::ETAPE_VALIDATION))) {
            $this->degustation->save();
        }

        $this->validation = new DegustationValidation($this->degustation);
        $this->form = new DegustationValidationForm($this->degustation);

         if (!$request->isMethod(sfWebRequest::POST)) {

             return sfView::SUCCESS;
         }

         $this->form->bind($request->getParameter($this->form->getName()));

         if (!$this->form->isValid()) {

             return sfView::SUCCESS;
         }

         $this->form->save();

        return $this->redirect('degustation');
    }


    public function executePrelevementsEtape(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->infosDegustation = $this->degustation->getInfosDegustation();
        if ($this->degustation->storeEtape($this->getEtape($this->degustation, DegustationEtapes::ETAPE_PRELEVEMENTS))) {
            $this->degustation->save();
        }
    }

    public function executeAnonymatsEtape(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        if ($this->degustation->storeEtape($this->getEtape($this->degustation, DegustationEtapes::ETAPE_ANONYMATS))) {
            $this->degustation->save();
          }
    }

    public function executeCommissionEtape(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->infosDegustation = $this->degustation->getInfosDegustation();
        if ($this->degustation->storeEtape($this->getEtape($this->degustation, DegustationEtapes::ETAPE_COMMISSION))) {
            $this->degustation->save();
          }
    }

    public function executeResultatsEtape(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->infosDegustation = $this->degustation->getInfosDegustation();
        if ($this->degustation->storeEtape($this->getEtape($this->degustation, DegustationEtapes::ETAPE_RESULTATS))) {
            $this->degustation->save();
          }
    }

    public function executeTablesEtape(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->infosDegustation = $this->degustation->getInfosDegustation();
        if ($this->degustation->storeEtape($this->getEtape($this->degustation, DegustationEtapes::ETAPE_TABLES))) {
            $this->degustation->save();
        }
    }

    public function executeNotificationsEtape(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        if ($this->degustation->storeEtape($this->getEtape($this->degustation, DegustationEtapes::ETAPE_NOTIFICATIONS))) {
            $this->degustation->save();
        }
    }

    public function executeConfirmation(sfWebRequest $request) {
      $this->degustation = $this->getRoute()->getDegustation();
    }


    public function executeDegustateursConfirmation(sfWebRequest $request) {
      $this->degustation = $this->getRoute()->getDegustation();

      $this->form = new DegustationDegustateursConfirmationForm($this->degustation);

      if (!$request->isMethod(sfWebRequest::POST)) {

          return sfView::SUCCESS;
      }

      $this->form->bind($request->getParameter($this->form->getName()));

      if (!$this->form->isValid()) {
          return sfView::SUCCESS;
      }
      $this->form->save();

      if ($request->isXmlHttpRequest()) {

        return $this->renderText(json_encode(array("success" => true, "document" => array("id" => $this->degustation->_id, "revision" => $this->degustation->_rev))));
      }

      return $this->redirect('degustation_prelevements_etape', $this->degustation);

    }

    public function executeDegustateurAbsence(sfWebRequest $request) {
      $this->degustation = $this->getRoute()->getDegustation();
      $college = $request->getParameter('college',null);
      $degustateurId = $request->getParameter('degustateurId',null);
      if(!$college || !$degustateurId){
        return $this->redirect('degustation_degustateurs_confirmation', $this->degustation);
      }
      $this->degustation->degustateurs->getOrAdd($college)->getOrAdd($degustateurId)->add('confirmation',false);
      $this->degustation->save();

      return $this->redirect('degustation_degustateurs_confirmation', $this->degustation);

    }

    public function executeOrganisationTable(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        if(!$request->getParameter('numero_table')) {

            return $this->redirect('degustation_organisation_table', array('id' => $this->degustation->_id, 'numero_table' => 1));
        }
        $this->numero_table = $request->getParameter('numero_table');

        if (!$request->getParameter('tri')) {
            $tri_default = 'Couleur|Genre|Appellation';
            return $this->redirect('degustation_organisation_table', array('id' => $this->degustation->_id, 'numero_table' => $this->numero_table, 'tri' => $tri_default));
        }
        $this->tri = $request->getParameter('tri');
        $this->tri_array = explode('|', strtolower($this->tri));

        $this->syntheseLots = $this->degustation->getSyntheseLotsTableCustomTri($this->numero_table, $this->tri_array);
        $this->form = new DegustationOrganisationTableForm($this->degustation, $this->numero_table, $this->tri_array);
        $this->ajoutLeurreForm = new DegustationAjoutLeurreForm($this->degustation, array('table' => $this->numero_table));
        $this->triTableForm = new DegustationTriTableForm($this->tri_array, false);

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }
        $this->form->save();

        if ($request->isXmlHttpRequest()) {

          return $this->renderText(json_encode(array("success" => true, "document" => array("id" => $this->degustation->_id, "revision" => $this->degustation->_rev))));
        }

        if(!count($this->degustation->getLotsTableOrFreeLots($this->numero_table, false)) && $this->degustation->hasFreeLots()) {

            return $this->redirect('degustation_organisation_table_recap', array('id' => $this->degustation->_id));
        }

        if($this->degustation->hasFreeLots()) {

            return $this->redirect('degustation_organisation_table', array('id' => $this->degustation->_id, 'numero_table' => $this->numero_table + 1, 'tri' => $this->tri));
        }

        return $this->redirect('degustation_organisation_table_recap', array('id' => $this->degustation->_id, 'tri' => $this->tri));
    }

    public function executeOrganisationTableRecap(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->tri = $request->getParameter('tri');
        $this->tri_array = explode('|', strtolower($this->tri));

        $this->form = new DegustationOrganisationTableRecapForm($this->degustation);
        $this->triTableForm = new DegustationTriTableForm($this->tri_array, true);

        $this->syntheseLots = $this->degustation->getSyntheseLotsTable(null);

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }
        $this->form->save();

        return $this->redirect('degustation_tables_etape', $this->degustation);
    }

    public function executeAjoutLeurre(sfWebRequest $request){
        $this->degustation = $this->getRoute()->getDegustation();
        $this->ajoutLeurreForm = new DegustationAjoutLeurreForm($this->degustation);
        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }
        $this->ajoutLeurreForm->bind($request->getParameter($this->ajoutLeurreForm->getName()));

        if (!$this->ajoutLeurreForm->isValid()) {

            $this->getUser()->setFlash('error', 'Formulaire d\'ajout de leurre invalide');
            return $this->redirect('degustation_organisation_table', array('id' => $this->degustation->_id, 'numero_table' => 0));
        }
        $this->ajoutLeurreForm->save();

        $table = $this->ajoutLeurreForm->getValue('table');
        if ($table == null) {
            $table = 0;
        }
        return $this->redirect('degustation_organisation_table', array('id' => $this->degustation->_id, 'numero_table' => $table));
    }

      public function executeResultats(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->numero_table = $request->getParameter('numero_table',0);
        $this->popup_validation = $request->getParameter('popup',0);
        $this->etablissementsLotsConforme = $this->degustation->getEtablissementLotsConformesOrNot();
        $this->etablissementsLotsNonConforme = $this->degustation->getEtablissementLotsConformesOrNot(false);
        if(!$this->numero_table && $this->degustation->getFirstNumeroTable()){
          return $this->redirect('degustation_resultats', array('id' => $this->degustation->_id, 'numero_table' => $this->degustation->getFirstNumeroTable()));
        }

        $this->tableLots = $this->degustation->getLotsByTable($this->numero_table);
        $this->nb_tables = count($this->degustation->getTablesWithFreeLots());
        $options = array('numero_table' => $this->numero_table);
        $this->form = new DegustationResultatsForm($this->degustation, $options);

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));
        if (!$this->form->isValid()) {
            return sfView::SUCCESS;
        }

        $this->form->save();

        if($this->popup_validation){
          return $this->redirect('degustation_resultats', array('id' => $this->degustation->_id, 'numero_table' => $this->numero_table));
        }

        if($this->numero_table != $this->nb_tables){
          return $this->redirect('degustation_resultats', array('id' => $this->degustation->_id, 'numero_table' => $this->numero_table+1));
        }

        return $this->redirect('degustation_resultats_etape', $this->degustation);
    }


    public function executePresences(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->numero_table = $request->getParameter('numero_table',0);

        if(!$this->numero_table && $this->degustation->getFirstNumeroTable()){
          return $this->redirect('degustation_presences', array('id' => $this->degustation->_id, 'numero_table' => $this->degustation->getFirstNumeroTable()));
        }

        $this->nb_tables = count($this->degustation->getTablesWithFreeLots());
        $options = array('numero_table' => $this->numero_table);
        $this->form = new DegustationDegustateursTableForm($this->degustation, $options);

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {
            return sfView::SUCCESS;
        }

        if ($request->isXmlHttpRequest()) {
          $this->degustation = $this->getRoute()->getDegustation();
          $this->form->save();
          return $this->renderText(json_encode(array("success" => true, "document" => array("id" => $this->degustation->_id, "revision" => $this->degustation->_rev))));
        }

        $this->form->save();

        if($this->numero_table && ($this->numero_table < $this->degustation->getLastNumeroTable())){
          return $this->redirect('degustation_presences', array('id' => $this->degustation->_id, 'numero_table' => $this->numero_table+1));
        }

        return $this->redirect('degustation_resultats_etape', $this->degustation);
    }

    public function executeDevalidation(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        $this->degustation->devalidate();
        $this->degustation->save();

        $this->getUser()->setFlash("notice", "La déclaration a été dévalidé avec succès.");

        return $this->redirect('degustation_validation', $this->degustation);
    }

    public function executeRedirect(sfWebRequest $request) {
        $this->degustation = $this->getRoute()->getDegustation();
        //$this->redirectIfIsValidee();
        return ($next = $this->getRouteEtape($this->degustation->etape))? $this->redirect($next, $this->degustation) : $this->redirect('degustation_prelevement_lots', $this->degustation);
    }

    public function redirectIfIsValidee(){
      if ($this->degustation->isValidee()) {
          return $this->redirect('degustation_confirmation', $this->degustation);
      }
    }

    protected function getEtape($doc, $etape, $class = "DegustationEtapes") {
        $etapes = $class::getInstance();
        if (!$doc->exist('etape')) {
            return $etape;
        }
        return ($etapes->isLt($doc->etape, $etape)) ? $etape : $doc->etape;
    }

    protected function getRouteEtape($etape = null, $class = "DegustationEtapes") {
        $etapes = $class::getInstance();
        $routes = $etapes->getRouteLinksHash();

        return (isset($routes[$etape]))? $routes[$etape] : null;
    }

    protected function getRouteNextEtape($etape = null, $class = "DegustationEtapes") {
        $etapes = $class::getInstance();
        $routes = $etapes->getRouteLinksHash();
        if (!$etape) {
            $etape = $etapes->getFirst();
        } else {
            $etape = $etapes->getNext($etape);
        }
        return (isset($routes[$etape]))? $routes[$etape] : null;
    }

    public function executeList(sfWebRequest $request) {
        $etablissement_id = $request->getParameter('id');
        $this->etablissement = EtablissementClient::getInstance()->find($etablissement_id);
        $this->forward404Unless($this->etablissement);
        $this->campagne = $request->getParameter('campagne',ConfigurationClient::getInstance()->getCampagneManager()->getCurrent());

        $this->lots = array();

    }

    public function executeLot(sfWebRequest $request) {
        $campagne = $request->getParameter('campagne');
        $lot_id = $request->getParameter('id');
        $this->lotsStepsHistory = array();

    }

    public function executeManquements(sfWebRequest $request) {
      $this->chgtDenoms = [];
      $this->manquements = DegustationClient::getInstance()->getManquements();
    }

    public function executeElevages(sfWebRequest $request) {
      $this->lotsElevages = MouvementLotView::getInstance()->getByStatut(null, Lot::STATUT_ELEVAGE)->rows;
    }

    public function executeRedeguster(sfWebRequest $request) {
        $docid = $request->getParameter('id');
        $ind = $request->getParameter('index');
        $back = $request->getParameter('back');
        $this->forward404Unless($back);
        $doc = acCouchdbManager::getClient()->find($docid);
        $this->forward404Unless($doc);
        $lot = null;
        if ($doc->lots->exist($ind)) {
          $lot = $doc->lots->get($ind);
        }
        $this->forward404Unless($lot);
        //DegustationClient::updatedSpecificite($lot);
        $lot->redegustation();
        $doc->generateMouvementsLots();
        $doc->save();
        return $this->redirect($back);
    }

    public function executeAnonymize(sfWebRequest $request){
      $degustation = $this->getRoute()->getDegustation();
      $degustation->anonymize();
      $degustation->save();
      return $this->redirect('degustation_anonymats_etape', $degustation);
    }

    public function executeDesanonymize(sfWebRequest $request){
      $degustation = $this->getRoute()->getDegustation();
      $degustation->desanonymize();
      $degustation->save();
      return $this->redirect('degustation_anonymats_etape', $degustation);
    }

    public function executeEtiquettesPdf(sfWebRequest $request) {
      $degustation = $this->getRoute()->getDegustation();

      $this->document = new ExportDegustationEtiquettesPdf($degustation, $this->getRequestParameter('output', 'pdf'), false);
      $this->document->setPartialFunction(array($this, 'getPartial'));

      if ($request->getParameter('force')) {
          $this->document->removeCache();
      }

      $this->document->generate();

      $this->document->addHeaders($this->getResponse());

      return $this->renderText($this->document->output());
    }
    public function executeEtiquettesAnonymesPDF(sfWebRequest $request) {
      $degustation = $this->getRoute()->getDegustation();

      $this->document = new ExportDegustationEtiquettesAnonymesPDF($degustation, $this->getRequestParameter('output', 'pdf'), false);
      $this->document->setPartialFunction(array($this, 'getPartial'));

      if ($request->getParameter('force')) {
          $this->document->removeCache();
      }

      $this->document->generate();

      $this->document->addHeaders($this->getResponse());

      return $this->renderText($this->document->output());
    }


    public function executeFicheIndividuellePDF(sfWebRequest $request){
      $degustation = $this->getRoute()->getDegustation();

      $this->document = new ExportDegustationFicheIndividuellePDF($degustation,$this->getRequestParameter('output','pdf'),false);
      $this->document->setPartialFunction(array($this, 'getPartial'));

      if ($request->getParameter('force')) {
          $this->document->removeCache();
      }

      $this->document->generate();

      $this->document->addHeaders($this->getResponse());

      return $this->renderText($this->document->output());

    }

    public function executeFicheEchantillonsPrelevesPDF(sfWebRequest $request){
      $degustation = $this->getRoute()->getDegustation();

      $this->document = new ExportDegustationFicheEchantillonsPrelevesPDF($degustation,$this->getRequestParameter('output','pdf'),false);
      $this->document->setPartialFunction(array($this, 'getPartial'));

      if ($request->getParameter('force')) {
          $this->document->removeCache();
      }

      $this->document->generate();

      $this->document->addHeaders($this->getResponse());

      return $this->renderText($this->document->output());

    }

    public function executeFicheEchantillonsPrelevesTablePDF(sfWebRequest $request){
      $degustation = $this->getRoute()->getDegustation();

      $this->document = new ExportDegustationFicheEchantillonsPrelevesTablePDF($degustation,$this->getRequestParameter('output','pdf'),false);
      $this->document->setPartialFunction(array($this, 'getPartial'));

      if ($request->getParameter('force')) {
          $this->document->removeCache();
      }

      $this->document->generate();

      $this->document->addHeaders($this->getResponse());

      return $this->renderText($this->document->output());

    }

    public function executeDegustationConformitePDF(sfWebRequest $request){
      $degustation = $this->getRoute()->getDegustation();

      $etablissement = EtablissementClient::getInstance()->find("ETABLISSEMENT-".$request['identifiant']);

      $this->document = new ExportDegustationConformitePDF($degustation,$etablissement,$this->getRequestParameter('output','pdf'),false);
      $this->document->setPartialFunction(array($this, 'getPartial'));

      if ($request->getParameter('force')) {
          $this->document->removeCache();
      }

      $this->document->generate();

      $this->document->addHeaders($this->getResponse());

      return $this->renderText($this->document->output());

    }

    public function executeDegustationNonConformitePDF(sfWebRequest $request){
      $degustation = $this->getRoute()->getDegustation();

      $etablissement = EtablissementClient::getInstance()->find("ETABLISSEMENT-".$request['identifiant']);
      $lot_dossier = $request['lot_dossier'];
      $lot_num_anon = $request['lot_num_anon'];

      $this->document = new ExportDegustationNonConformitePDF($degustation,$etablissement,$lot_dossier, $lot_num_anon, $this->getRequestParameter('output','pdf'),false);
      $this->document->setPartialFunction(array($this, 'getPartial'));

      if ($request->getParameter('force')) {
          $this->document->removeCache();
      }

      $this->document->generate();

      $this->document->addHeaders($this->getResponse());

      return $this->renderText($this->document->output());

    }

    public function executeMailPrevisualisation(sfWebRequest $request){
      $this->degustation = $this->getRoute()->getDegustation();

      $this->etablissement = EtablissementClient::getInstance()->findByIdentifiant($request->getParameter('identifiant'));
      $this->emailLinkManager = new DegustationEmailManager($this->degustation,$this->etablissement);

      $this->setTemplate('notificationsEtape');

    }

    public function executeSetEnvoiMail(sfWebRequest $request){
      $this->degustation = $this->getRoute()->getDegustation();
      $date = $request->getParameter('envoye',date('Y-m-d H:i:s'));
      if(!boolval($date)){ $date = null; }

      $this->setTemplate('notificationsEtape');
      $this->degustation->setMailEnvoyeEtablissement($request['identifiant'],$date);
      $this->degustation->save();

      return $this->redirect('degustation_notifications_etape', $this->degustation);
    }

    public function executeRetraitNonConformitePDF(sfWebRequest $request){
      $degustation = $this->getRoute()->getDegustation();

      $etablissement = EtablissementClient::getInstance()->find("ETABLISSEMENT-".$request['identifiant']);
      $lot_dossier = $request['lot_dossier'];

      $this->document = new ExportRetraitNonConformitePDF($degustation,$etablissement,$lot_dossier,$this->getRequestParameter('output','pdf'),false);
      $this->document->setPartialFunction(array($this, 'getPartial'));

      if ($request->getParameter('force')) {
          $this->document->removeCache();
      }

      $this->document->generate();

      $this->document->addHeaders($this->getResponse());

      return $this->renderText($this->document->output());

    }

    public function executeFicheRecapTablesPDF(sfWebRequest $request){
      $degustation = $this->getRoute()->getDegustation();

      $this->document = new ExportDegustationFicheRecapTablesPDF($degustation,$this->getRequestParameter('output','pdf'),false);
      $this->document->setPartialFunction(array($this, 'getPartial'));

      if ($request->getParameter('force')) {
          $this->document->removeCache();
      }

      $this->document->generate();

      $this->document->addHeaders($this->getResponse());

      return $this->renderText($this->document->output());

    }

    public function executeTriTable(sfWebRequest $request) {
        $degustation = $this->getRoute()->getDegustation();
        $numero_table = $request->getParameter('numero_table');
        $this->triTableForm = new DegustationTriTableForm(array());

        if (!$request->isMethod(sfWebRequest::POST)) {
            return $this->redirect('degustation_organisation_table', array('id' => $degustation->_id, 'numero_table' => $numero_table));
        }

        $this->triTableForm->bind($request->getParameter($this->triTableForm->getName()));
        $recap = $this->triTableForm->getValue('recap');

        if (!$this->triTableForm->isValid()) {
            if($recap) {
                return $this->redirect('degustation_organisation_table_recap', array('id' => $degustation->_id));
            }
            return $this->redirect('degustation_organisation_table', array('id' => $degustation->_id, 'numero_table' => $numero_table));
        }

        $values = $this->triTableForm->getValues();
        unset($values['recap']);

        if($recap) {
            return $this->redirect('degustation_organisation_table_recap', array('id' => $degustation->_id, 'tri' => join('|', array_filter(array_values($values)))));
        }
        return $this->redirect('degustation_organisation_table', array('id' => $degustation->_id, 'numero_table' => $numero_table, 'tri' => join('|', array_filter(array_values($values)))));
    }

    public function executeFicheLotsAPreleverPDF(sfWebRequest $request){
      $degustation = $this->getRoute()->getDegustation();

      $this->document = new ExportDegustationFicheLotsAPreleverPDF($degustation,$this->getRequestParameter('output','pdf'),false);
      $this->document->setPartialFunction(array($this, 'getPartial'));

      if ($request->getParameter('force')) {
          $this->document->removeCache();
      }

      $this->document->generate();

      $this->document->addHeaders($this->getResponse());

      return $this->renderText($this->document->output());
    }

    public function executeFicheIndividuelleLotsAPreleverPDF(sfWebRequest $request){
      $degustation = $this->getRoute()->getDegustation();

      $this->document = new ExportDegustationFicheIndividuelleLotsAPreleverPDF($degustation,$this->getRequestParameter('output','pdf'),false);
      $this->document->setPartialFunction(array($this, 'getPartial'));

      if ($request->getParameter('force')) {
          $this->document->removeCache();
      }

      $this->document->generate();

      $this->document->addHeaders($this->getResponse());

      return $this->renderText($this->document->output());
    }

    public function executeFichePresenceDegustateursPDF(sfWebRequest $request){
      $degustation = $this->getRoute()->getDegustation();

      $this->document = new ExportDegustationFichePresenceDegustateursPDF($degustation,$this->getRequestParameter('output','pdf'),false);
      $this->document->setPartialFunction(array($this, 'getPartial'));

      if ($request->getParameter('force')) {
          $this->document->removeCache();
      }

      $this->document->generate();

      $this->document->addHeaders($this->getResponse());

      return $this->renderText($this->document->output());
    }

    public function executeProcesVerbalDegustationPDF(sfWebRequest $request){
      $degustation = $this->getRoute()->getDegustation();

      $this->document = new ExportDegustationFicheProcesVerbalDegustationPDF($degustation,$this->getRequestParameter('output','pdf'),false);
      $this->document->setPartialFunction(array($this, 'getPartial'));

      if ($request->getParameter('force')) {
          $this->document->removeCache();
      }

      $this->document->generate();

      $this->document->addHeaders($this->getResponse());

      return $this->renderText($this->document->output());
    }

}
