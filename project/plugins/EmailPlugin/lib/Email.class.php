<?php

class Email {

    private static $_instance = null;
    protected $_context;

    public function __construct($context = null) {
        $this->_context = ($context) ? $context : sfContext::getInstance();
    }

    public static function getInstance($context = null) {
        if (is_null(self::$_instance)) {
            self::$_instance = new Email($context);
        }
        return self::$_instance;
    }

    public function sendDRevValidation($drev) {
        if (!$drev->declarant->email) {

            return;
        }
        $from = array(sfConfig::get('app_email_plugin_from_adresse') => sfConfig::get('app_email_plugin_from_name'));
        $to = array($drev->declarant->email);
        $subject = 'Validation de votre Déclaration de Revendication';
        $body = $this->getBodyFromPartial('send_drev_validation', array('drev' => $drev));
        $message = Swift_Message::newInstance()
                ->setFrom($from)
                ->setTo($to)
                ->setSubject($subject)
                ->setBody($body)
                ->setContentType('text/plain');

        return $this->getMailer()->send($message);
    }

    public function sendDRevConfirmee($drev) {
        if (!$drev->declarant->email) {

            return;
        }

        $pdf = new ExportDRevPdf($drev);
        $pdf->setPartialFunction(array($this, 'getPartial'));
        $pdf->generate();
        $pdfAttachment = new Swift_Attachment($pdf->output(), $pdf->getFileName(), 'application/pdf');

        $from = array(sfConfig::get('app_email_plugin_from_adresse') => sfConfig::get('app_email_plugin_from_name'));
        $to = array($drev->declarant->email);
        $subject = 'Validation définitive de votre Déclaration de Revendication';
        $body = $this->getBodyFromPartial('send_drev_confirmee', array('drev' => $drev));
        $message = Swift_Message::newInstance()
                ->setFrom($from)
                ->setTo($to)
                ->setSubject($subject)
                ->setBody($body)
                ->setContentType('text/plain')
                ->attach($pdfAttachment);

        return $this->getMailer()->send($message);
    }

    public function sendDRevRappelDocuments($drev) {
        if (!$drev->declarant->email) {

            return;
        }

        if ($drev->hasCompleteDocuments()) {

            return;
        }

        $partial = 'send_drev_rappel_documents';
        $subject = "Rappel - Documents à envoyer pour votre déclaration de Revendication";

        if ($drev->exist('documents_rappels') && count($drev->documents_rappels->toArray(true, false)) > 0) {
            $partial = 'send_drev_rappel_documents_second';
            $subject = "2ème Rappel - Documents à envoyer pour la validation définitive de votre déclaration de Revendication";
        }

        $from = array(sfConfig::get('app_email_plugin_from_adresse') => sfConfig::get('app_email_plugin_from_name'));
        $to = array($drev->declarant->email);
        $body = $this->getBodyFromPartial($partial, array('drev' => $drev));
        $message = Swift_Message::newInstance()
                ->setFrom($from)
                ->setTo($to)
                ->setSubject($subject)
                ->setBody($body)
                ->setContentType('text/plain');

        return $this->getMailer()->send($message);
    }

    public function sendDRevMarcValidation($drevmarc) {
        if (!$drevmarc->declarant->email) {

            return;
        }

        $from = array(sfConfig::get('app_email_plugin_from_adresse') => sfConfig::get('app_email_plugin_from_name'));
        $to = array($drevmarc->declarant->email);
        $subject = "Validation de votre Déclaration de Revendication Marc d'Alsace Gewurztraminer";
        $body = $this->getBodyFromPartial('send_drevmarc_validation', array('drevmarc' => $drevmarc));
        $message = Swift_Message::newInstance()
                ->setFrom($from)
                ->setTo($to)
                ->setSubject($subject)
                ->setBody($body)
                ->setContentType('text/plain');

        return $this->getMailer()->send($message);
    }

    public function sendDRevMarcConfirmee($drevmarc) {
        if (!$drevmarc->declarant->email) {

            return;
        }

        $pdf = new ExportDRevMarcPDF($drevmarc);
        $pdf->setPartialFunction(array($this, 'getPartial'));
        $pdf->generate();
        $pdfAttachment = new Swift_Attachment($pdf->output(), $pdf->getFileName(), 'application/pdf');

        $from = array(sfConfig::get('app_email_plugin_from_adresse') => sfConfig::get('app_email_plugin_from_name'));
        $to = array($drevmarc->declarant->email);
        $subject = "Validation définitive de votre Déclaration de Revendication Marc d'Alsace Gewurztraminer";
        $body = $this->getBodyFromPartial('send_drevmarc_confirmee', array('drevmarc' => $drevmarc));
        $message = Swift_Message::newInstance()
                ->setFrom($from)
                ->setTo($to)
                ->setSubject($subject)
                ->setBody($body)
                ->setContentType('text/plain')
                ->attach($pdfAttachment);

        return $this->getMailer()->send($message);
    }

    public function sendParcellaireValidation($parcellaire) {
        if (!$parcellaire->declarant->email) {

            return;
        }

        $pdf = new ExportParcellairePDF($parcellaire);
        $pdf->setPartialFunction(array($this, 'getPartial'));
        $pdf->generate();
        $pdfAttachment = new Swift_Attachment($pdf->output(), $pdf->getFileName(), 'application/pdf');

        $from = array(sfConfig::get('app_email_plugin_from_adresse') => sfConfig::get('app_email_plugin_from_name'));
        $to = array($parcellaire->declarant->email);
        $subject = "Validation de votre déclaration d'affectation parcellaire";
        $body = $this->getBodyFromPartial('send_parcellaire_validation', array('parcellaire' => $parcellaire));
        $message = Swift_Message::newInstance()
                ->setFrom($from)
                ->setTo($to)
                ->setSubject($subject)
                ->setBody($body)
                ->setContentType('text/plain')
                ->attach($pdfAttachment);
        return $this->getMailer()->send($message);
    }

    public function sendDegustationOperateursMails($degustation) {
        $from = array(sfConfig::get('app_email_plugin_from_adresse') => sfConfig::get('app_email_plugin_from_name'));
        $reply_to = array(sfConfig::get('app_email_plugin_reply_to_adresse') => sfConfig::get('app_email_plugin_reply_to_name'));
        foreach ($degustation->operateurs as $key => $operateur) {
            $to = $operateur->email;
            $subject = "Avis de passage en vue d'une dégustation conseil ODG-AVA le ".Date::francizeDate($operateur->date);
            $body = $this->getBodyFromPartial('send_degustation_operateur', array('operateur' => $operateur));

            $message = Swift_Message::newInstance()
                    ->setFrom($from)
                    ->setReplyTo($reply_to)
                    ->setTo($to)
                    ->setSubject($subject)
                    ->setBody($body);
            $this->getMailer()->send($message);
        }
        return true;
    }

    public function sendDegustationDegustateursMails($degustation) {
        $from = array(sfConfig::get('app_email_plugin_from_adresse') => sfConfig::get('app_email_plugin_from_name'));
        $reply_to = array(sfConfig::get('app_email_plugin_reply_to_adresse') => sfConfig::get('app_email_plugin_reply_to_name'));
        foreach ($degustation->degustateurs as $types_degustateur => $comptes) {
            foreach ($comptes as $id_compte => $degustateur_node) {
                $to = $degustateur_node->email;
                $subject = "L'AVA vous invite à une dégustation conseil le ".Date::francizeDate($degustation->date).' à '.$degustation->heure;
                $body = $this->getBodyFromPartial('send_degustation_degustateur', array('degustation' => $degustation));
                $message = Swift_Message::newInstance()
                        ->setFrom($from)
                        ->setReplyTo($reply_to)
                        ->setTo($to)
                        ->setSubject($subject)
                        ->setBody($body)
                        ->setContentType('text/plain');
               $this->getMailer()->send($message);
            }
        }
        return true;
    }

    protected function getMailer() {
        return $this->_context->getMailer();
    }

    protected function getBodyFromPartial($partial, $vars = null) {
        return $this->_context->getController()->getAction('Email', 'main')->getPartial('Email/' . $partial, $vars);
    }

    public function getPartial($templateName, $vars = null) {
        sfContext::getInstance()->getConfiguration()->loadHelpers('Partial');

        $vars = null !== $vars ? $vars : $this->varHolder->getAll();

        return get_partial($templateName, $vars);
    }

}
