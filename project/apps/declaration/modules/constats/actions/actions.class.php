<?php

class constatsActions extends sfActions {

    public function executeIndex(sfWebRequest $request) {
        $this->getUser()->signOutEtablissement();

        $this->jour = $request->getParameter('jour');

        $this->organisationJournee = RendezvousClient::getInstance()->buildOrganisationNbDays(2, $this->jour);
        $this->form = new LoginForm();

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }
        $this->getUser()->signInEtablissement($this->form->getValue('etablissement'));

        return $this->redirect('rendezvous_declarant', $this->getUser()->getEtablissement()->getCompte());
    }

    public function executePlanificationJour(sfWebRequest $request) {
        $this->jour = $request->getParameter('jour');
        $this->rendezvousJournee = RendezvousClient::getInstance()->buildRendezvousJournee($this->jour);
        $this->tourneesJournee = TourneeClient::getInstance()->buildTourneesJournee($this->jour);
       
    }
    
    public function executeTourneeAgentRendezvous(sfWebRequest $request) {
        $this->tournee = $this->getRoute()->getTournee();
        $rdv0 = RendezvousClient::getInstance()->find("RENDEZVOUS-6823700100-201509081131");
         
        $this->tournee->addRendezVous($rdv0,"15:20");
        //$this->tournee->addRendezVous($rdv1,"16:20")
        $this->tournee->addRendezVousAndGenerateConstat($rdv0,"15:20");
        //$this->tournee->addRendezVousAndGenerateConstat($rdv1,"16:20");
        $this->tournee->save();
    }

    public function executeAjoutAgentTournee(sfWebRequest $request) {
        sfContext::getInstance()->getConfiguration()->loadHelpers(array('Date'));
        $this->jour = $request->getParameter('jour');
        $this->form = new TourneeAddAgentForm(array('date' => format_date($this->jour, "dd/MM/yyyy", "fr_FR")));
        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }

        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }

        $compteAgent = CompteClient::getInstance()->find('COMPTE-' . $this->form->getValue('agent'));
        $tournee = TourneeClient::getInstance()->findOrAddByDateAndAgent($this->form->getValue('date'), $compteAgent);
        $this->redirect('constats_planification_jour', array('jour' => $this->jour));
    }

    public function executePlanifications(sfWebRequest $request) {
        $this->jour = '2015-09-08';
        $rdvs = RendezvousClient::getInstance()->buildRendezvousJournee($this->jour);
        $this->rdvsPris = $rdvs[RendezvousClient::RENDEZVOUS_STATUT_PRIS];
        $this->tournees = TourneeClient::getInstance()->buildTourneesJournee($this->jour);

        $this->heures = array();
        for ($i = 7; $i <= 20; $i++) {
            $this->heures[sprintf("%02d:00", $i)] = sprintf("%02d", $i);
        }

        foreach($this->tournees->tourneesJournee as $tournee) {
            $this->tournee = $tournee->tournee;
        }

        $this->rdvs = $this->tournee->getRendezVousOrderByHour();

    }

    public function executeRendezvousDeclarant(sfWebRequest $request) {
        $this->compte = $this->getRoute()->getCompte();
        $this->rendezvousDeclarant = RendezvousClient::getInstance()->getRendezvousByCompte($this->compte->cvi);
        $this->formsRendezVous = array();
        $this->form = new LoginForm();

        foreach ($this->compte->getChais() as $chaiKey => $chai) {
            $rendezvous = new Rendezvous();
            $rendezvous->identifiant = $this->compte->identifiant;
            $rendezvous->idchai = $chaiKey;
            $this->formsRendezVous[$chaiKey] = new RendezvousDeclarantForm($rendezvous);
        }

        if (!$request->isMethod(sfWebRequest::POST)) {

            return sfView::SUCCESS;
        }
        $this->form->bind($request->getParameter($this->form->getName()));

        if (!$this->form->isValid()) {

            return sfView::SUCCESS;
        }
        $this->getUser()->signInEtablissement($this->form->getValue('etablissement'));

        return $this->redirect('rendezvous_declarant', $this->getUser()->getEtablissement()->getCompte());
    }

    public function executeRendezvousModification(sfWebRequest $request) {
        $this->rendezvous = $this->getRoute()->getRendezvous();
        $this->chai = $this->rendezvous->getChai();
        $this->form = new RendezvousDeclarantForm($this->rendezvous);
        if (!$request->isMethod(sfWebRequest::POST)) {
            return sfView::SUCCESS;
        }
        $this->form->bind($request->getParameter($this->form->getName()));
        if (!$this->form->isValid()) {
            return $this->getTemplate('rendezvousDeclarant');
        }
        $this->form->save();
        $this->redirect('rendezvous_declarant', $this->rendezvous->getCompte());
    }

    public function executeRendezvousCreation(sfWebRequest $request) {
        $this->compte = $this->getRoute()->getCompte();
        $this->idchai = $request->getParameter('idchai');
        $rendezvous = new Rendezvous();
        $rendezvous->idchai = $this->idchai;
        $this->form = new RendezvousDeclarantForm($rendezvous);

        if (!$request->isMethod(sfWebRequest::POST)) {
            return sfView::SUCCESS;
        }
        $this->form->bind($request->getParameter($this->form->getName()));
        if (!$this->form->isValid()) {
            return $this->getTemplate('rendezvousDeclarant');
        }
        $date = $this->form->getValue('date');
        $heure = $this->form->getValue('heure');
        $commentaire = $this->form->getValue('commentaire');
        $rendezvous = RendezvousClient::getInstance()->findOrCreate($this->compte, $this->idchai, $date, $heure, $commentaire);
        $rendezvous->save();
        $this->redirect('rendezvous_declarant', $this->compte);
    }

}
