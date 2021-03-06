<?php

class DegustationCourrierForm extends acCouchdbObjectForm {

    public function __construct(\acCouchdbJson $object, $options = array(), $CSRFSecret = null) {
        parent::__construct($object, $options, $CSRFSecret);
        $this->updateDefaults();
    }

    public function configure() {
        foreach ($this->getObject()->getNotes() as $note) {
            if($note->prelevement->courrier_envoye) {
                continue;
            }

            $keyForNote = $this->constructKeyForNote($note);

            $this->setWidget($keyForNote, new sfWidgetFormChoice(array('choices' => $this->getTypesCourrier())));
            $this->setValidator($keyForNote, new sfValidatorChoice(array('choices' => array_keys($this->getTypesCourrier()), 'required' => false)));

            $this->setWidget('visite_date_' . $keyForNote, new sfWidgetFormInput(array(), array()));
            $this->setValidator('visite_date_' . $keyForNote, new sfValidatorDate(array('date_output' => 'Y-m-d', 'date_format' => '~(?P<day>\d{2})/(?P<month>\d{2})/(?P<year>\d{4})~', 'required' => false)));

            $this->setWidget('visite_heure_' . $keyForNote, new sfWidgetFormInput(array(), array()));
            $this->setValidator('visite_heure_' . $keyForNote, new sfValidatorTime(array('time_output' => 'H:i', 'time_format' => '~(?P<hour>\d{2}):(?P<minute>\d{2})~', 'required' => false)));
        }
        $this->widgetSchema->setNameFormat('degustation_courrier[%s]');
    }

    protected function updateDefaults() {
        foreach ($this->getObject()->getNotes() as $note) {
            if($note->prelevement->courrier_envoye) {
                continue;
            }
            $keyForNote = $this->constructKeyForNote($note);

            if((!$note->prelevement->exist('type_courrier') || !$note->prelevement->type_courrier) && !$note->prelevement->hasMauvaiseNote()) {
                $note->prelevement->add('type_courrier', DegustationClient::COURRIER_TYPE_OK);
            }

            if ($note->prelevement->exist('type_courrier')) {
                $this->setDefault($keyForNote, $note->prelevement->type_courrier);
            }
            if ($note->prelevement->exist('visite_date') && $note->prelevement->get('visite_date')) {
                $dateArr = explode('-', $note->prelevement->visite_date);
                $date = $dateArr[2] . '/' . $dateArr[1] . '/' . $dateArr[0];
                $this->setDefault('visite_date_' . $keyForNote, $date);
            }
            if ($note->prelevement->exist('visite_heure') && $note->prelevement->get('visite_heure')) {
                $this->setDefault('visite_heure_' . $keyForNote, $note->prelevement->visite_heure);
            }
        }
    }

    public function getTypesCourrier() {
        return array_merge(array("" => ""), DegustationClient::$types_courrier_libelle);
    }

    public function update() {
        $values = $this->values;
        foreach ($values as $key => $value) {
            if (preg_match('/^([0-9]{10})-(.*)/', $key, $matches)) {
                $cvi_operateur = $matches[1];
                $id_degustation = $this->getObject()->degustations[$cvi_operateur];
                $degustation = DegustationClient::getInstance()->find($id_degustation);
                $realKeyPrelevement = str_replace('-', '/', $matches[2]);
                if(!$degustation){
                    throw new sfException("La degustation ".$id_degustation." n'existe pas");
                }
                $degustation->get($realKeyPrelevement)->add('type_courrier', $value);
                if(!$value){
                    $degustation->get($realKeyPrelevement)->type_courrier = null;
                } 
                if ($value == DegustationClient::COURRIER_TYPE_VISITE) {
                    $degustation->get($realKeyPrelevement)->add('visite_date', $values['visite_date_' . $key]);
                    $degustation->get($realKeyPrelevement)->add('visite_heure', $values['visite_heure_' . $key]);
                } else {
                    $degustation->get($realKeyPrelevement)->add('visite_date', null);
                    $degustation->get($realKeyPrelevement)->add('visite_heure', null);
                }

                $degustation->save();
            }
        }
    }

    public function constructKeyForNote($note) {
        return $note->operateur->cvi . $note->prelevement->getHashForKey();
    }

}
