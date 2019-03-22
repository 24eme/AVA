<?php
class HabilitationDemandeEditionForm extends acCouchdbForm
{
    protected $demande = null;

    public function __construct($doc, $defaults = array(), $options = array(), $CSRFSecret = null) {
        if($doc instanceof HabilitationDemande) {
            $this->demande = $doc;
            $doc = $doc->getDocument();
        }

        parent::__construct($doc, $defaults, $options, $CSRFSecret);
    }

    public function configure()
    {
        $statuts = $this->getStatuts();

        $this->setWidgets(array(
            'date' => new sfWidgetFormInput(array(), array()),
            'statut' => new sfWidgetFormChoice(array('choices' => $statuts)),
            'commentaire' => new sfWidgetFormInput(array(), array()),
        ));
        $this->widgetSchema->setLabels(array(
            'date' => 'Date: ',
            'statut' => 'Statut: ',
            'commentaire' => 'Commentaire: ',
        ));

        $this->setValidators(array(
            'date' => new sfValidatorDate(
                array('date_output' => 'Y-m-d',
                'date_format' => '~(?P<day>\d{2})/(?P<month>\d{2})/(?P<year>\d{4})~',
                'required' => true,
                'max' => date("Y-m-d")),array('max' => 'La date doit être inférieure à la date du jour ('.date('d/m/Y').')')),
            'statut' => new sfValidatorChoice(array('required' => true, 'choices' => array_keys($statuts))),
            'commentaire' => new sfValidatorString(array("required" => false)),
        ));

        $this->widgetSchema->setNameFormat('habilitation_demande_edition[%s]');
    }

    public function getStatuts(){

        $statuts = HabilitationClient::getInstance()->getDemandeStatuts();
        foreach($statuts as $key => $libelle) {
            if($this->getOption('filtre') && !preg_match("/".$this->getOption('filtre')."/i", $key)) {
                unset($statuts[$key]);
                continue;
            }

            if(HabilitationClient::getInstance()->getDemandeAutomatiqueStatut($key)) {
                $statuts[$key] .= ' ('.HabilitationClient::getInstance()->getDemandeStatutLibelle(HabilitationClient::getInstance()->getDemandeAutomatiqueStatut($key)).')';
            }

        }

        return array_merge(array("" => ""), $statuts);
    }

    public function save()
    {
        $values = $this->getValues();

        if($this->demande->date > $values['date']) {
            throw new Exception("Il n'est pas possible de mettre un statut à une date inférieur");
        }

        $demande = HabilitationClient::getInstance()->updateDemandeAndSave(
                                                              $this->getDocument()->identifiant,
                                                              $this->demande->getKey(),
                                                              $values['date'],
                                                              $values['statut'],
                                                              $values['commentaire'],
                                                              null,
                                                              true
                                                              );

        return $demande;
    }
}
