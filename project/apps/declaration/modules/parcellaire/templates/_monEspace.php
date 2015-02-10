<?php if (!count($parcellairesHistory) && !$etablissement->hasFamille(EtablissementClient::FAMILLE_DISTILLATEUR)): ?>
    <?php return; ?>
<?php endif; ?>

<div class="col-xs-4">
        <?php if (!$parcellaire_non_ouverte): ?>
            <div class="block_declaration panel <?php if ($parcellaire && $parcellaire->validation): ?>panel-success<?php else: ?>panel-primary<?php endif; ?>">     
                <div class="panel-heading">
                    <h3>Déclaration de Parcellaire <?php echo ConfigurationClient::getInstance()->getCampagneManager()->getCurrent(); ?></h3>
                </div>
                <div class="panel-body">
                    <?php if ($parcellaire && $parcellaire->validation): ?>
                        <p>
                            <a class="btn btn-lg btn-block btn-primary" href="<?php echo url_for('parcellaire_visualisation', $parcellaire) ?>">Visualiser</a>
                        </p>
                        <?php if($sf_user->isAdmin()): ?>
                        <p>
                            <a class="btn btn-xs btn-warning pull-right" href="<?php echo url_for('parcellaire_devalidation', $parcellaire) ?>"><span class="glyphicon glyphicon-remove-sign"></span>&nbsp;&nbsp;Dévalider la déclaration</a>
                        </p>
                        <?php endif; ?>
                    <?php elseif ($parcellaire):  ?>
                        <p>
                            <a class="btn btn-lg btn-block btn-default" href="<?php echo url_for('parcellaire_edit', $parcellaire) ?>">Continuer</a>
                        </p>
                        <p>
                            <a class="btn btn-xs btn-danger pull-right" href="<?php echo url_for('parcellaire_delete', $parcellaire) ?>"><span class="glyphicon glyphicon-trash"></span>&nbsp;&nbsp;Supprimer le brouillon</a>
                        </p>
                    <?php else:  ?>
                        <p>
                            <a class="btn btn-lg btn-block btn-default" href="<?php echo url_for('parcellaire_create', $etablissement) ?>">Démarrer</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php include_partial('parcellaireNonOuvert', array('date_ouverture_parcellaire' => $date_ouverture_parcellaire)); ?>
        <?php endif; ?>
</div>
