<?php include_partial('parcellaire/step', array('step' => 'validation', 'parcellaire' => $parcellaire)) ?>
<div class="page-header">
    <h2>Validation de votre parcellaire</h2>
</div>

<div class="row col-xs-12">
    <h3>Voici les changements de parcelles sur l'année <?php echo $parcellaire->campagne; ?></h3>
    <p>Le Pdf vous fourni une version complète de votre parcellaire</p>
</div>

<?php if ($validation->hasPoints()): ?>
    <?php include_partial('parcellaire/pointsAttentions', array('parcellaire' => $parcellaire, 'validation' => $validation)); ?>
<?php endif; ?>
<?php include_partial('parcellaire/recap', array('parcellaire' => $parcellaire, 'parcellesByCommunes' => $parcellesByCommunes, 'parcellesByCommunesLastCampagne' => $parcellesByCommunesLastCampagne)); ?>

<div class="row row-margin row-button">
    <div class="col-xs-4">
        <a href="<?php echo url_for("parcellaire_parcelles", array('id' => $parcellaire->_id, 'appellation' => 'LIEUDIT')); ?>" class="btn btn-primary btn-lg btn-upper"><span class="eleganticon arrow_carrot-left"></span>&nbsp;&nbsp;Retourner <small>à l'étape précédente</small></a>
    </div>
    <div class="col-xs-4 text-center">
        <a href="<?php echo url_for("parcellaire_export_pdf", $parcellaire) ?>" class="btn btn-warning btn-lg">
            <span class="glyphicon glyphicon-file"></span>&nbsp;&nbsp;Prévisualiser
        </a>
    </div>
    <div class="col-xs-4 text-right">
        <button type="button" data-toggle="modal" data-target="#parcellaire-confirmation-validation" <?php if ($validation->hasErreurs()): ?>disabled="disabled"<?php endif; ?> class="btn btn-default btn-lg btn-upper"><span class="glyphicon glyphicon-check"></span>&nbsp;&nbsp;Valider la déclaration</button>
    </div>
</div>
<?php if (!$validation->hasErreurs()): ?>
    <?php include_partial('parcellaire/popupConfirmationValidation', array('form' => $form,'parcellaire' => $parcellaire)); ?>
<?php endif; ?>