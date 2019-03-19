<?php use_helper('Compte');
$types_liaisons = EtablissementClient::getTypesLiaisons();
?>
<ol class="breadcrumb">
    <li><a href="<?php echo url_for('societe') ?>">Contacts</a></li>
    <li><a href="<?php echo url_for('societe_visualisation', array('identifiant' => $societe->identifiant)); ?>"><span class="<?php echo comptePictoCssClass($societe->getRawValue()) ?>"></span> <?php echo $societe->raison_sociale; ?> (<?php echo $societe->identifiant ?>)</a></li>
    <li class="active"><a href="<?php echo url_for('etablissement_visualisation', array('identifiant' => $etablissement->identifiant)); ?>"><span class="<?php echo comptePictoCssClass($etablissement->getRawValue()) ?>"></span> <?php echo $etablissement->nom; ?></a></li>
</ol>

<div class="row">
    <div class="col-xs-8">
        <div class="panel panel-default" style="margin-bottom: 0; border-bottom: 0;">
            <div class="panel-heading" style="border-bottom: 0;">
                <h4><span class="<?php echo comptePictoCssClass($societe->getRawValue()) ?>"></span> Societe n° <?php echo $societe->identifiant; ?></h4>
            </div>
        </div>
        <div class="panel panel-default">
            <div class="panel-heading">
                <div class="row">
                    <div class="col-xs-9">
                        <h4><span class="<?php echo comptePictoCssClass($etablissement->getRawValue()) ?>"></span> Établissement n° <?php echo $etablissement->getNumeroCourt(); ?></h4>
                    </div>
                    <div class="col-xs-3 text-muted text-right">
                        <div class="btn-group">
                            <a class="btn dropdown-toggle " data-toggle="dropdown" href="#">Modifier <span class="caret"></span></a>
                            <ul class="dropdown-menu text-left">
                                <li<?php echo ($societe->isSuspendu() || $etablissement->isSuspendu()) ? ' class="disabled"' : ''; ?>><a href="<?php echo ($societe->isSuspendu() || $etablissement->isSuspendu()) ? 'javascript:void(0)' : url_for('etablissement_modification', $etablissement); ?>">Editer</a></li>
                                <li<?php echo ($societe->isSuspendu() || $etablissement->isSuspendu())? ' class="disabled"' : ''; ?>><a href="<?php echo ($societe->isSuspendu() || $etablissement->isSuspendu())? 'javascript:void(0)' : url_for('etablissement_switch_statut', array('identifiant' => $etablissement->identifiant)); ?>">Archiver</a></li>
                                <li<?php echo ($societe->isSuspendu() || $etablissement->isActif())? ' class="disabled"' : ''; ?>><a href="<?php echo ($societe->isSuspendu() || $etablissement->isActif())? 'javascript:void(0)' : url_for('etablissement_switch_statut', array('identifiant' => $etablissement->identifiant)); ?>">Activer</a></li>
                                <li><a href="<?php echo url_for('compte_switch_en_alerte', array('identifiant' => $etablissement->getMasterCompte()->identifiant)); ?>"><?php echo ($etablissement->getMasterCompte()->exist('en_alerte') && $etablissement->getMasterCompte()->en_alerte)? 'Retirer alerte' : 'Mettre en alerte' ?></a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="panel-body panel-primary-bordered-right">
                <h2>
                	<?php echo $etablissement->nom; ?>
                    <?php if ($etablissement->isSuspendu()): ?>
					    <span class="label label-default pull-right" style="padding-top: 0;"><small style="font-weight: inherit; color: inherit;"><?php echo $etablissement->getStatutLibelle(); ?></small></span>
					<?php endif; ?>
                    <?php if ($etablissement->getMasterCompte()->exist('en_alerte') && $etablissement->getMasterCompte()->en_alerte): ?><span class="pull-right">⛔</span><?php endif; ?>
                </h2>
                <hr/>
                <div class="row">
                    <div class="col-xs-5">
                        <div class="row">
                            <?php if ($etablissement->famille) : ?>
                                <div style="margin-bottom: 5px;" class="col-xs-4 text-muted">Famille :</div>
                                <div style="margin-bottom: 5px;" class="col-xs-8"><?php if(isset(EtablissementFamilles::$familles[$etablissement->famille])):?><?php echo EtablissementFamilles::$familles[$etablissement->famille]; ?><?php else: ?><?php echo $etablissement->famille ?><?php endif; ?></div>
                            <?php endif; ?>
                            <?php if ($etablissement->recette_locale && $etablissement->recette_locale->nom) : ?>
                                <div style="font-weight: bold; margin-bottom: 5px;" class="col-xs-4 text-muted">Recette locale :</div>
                                <div style="margin-bottom: 5px;" class="col-xs-8"><a href="<?php echo url_for('societe_visualisation', SocieteClient::getInstance()->find($etablissement->recette_locale->id_douane)); ?>">
                                <?php echo $etablissement->recette_locale->nom; ?></a></div>
                            <?php endif; ?>
                                <?php if ($etablissement->identifiant): ?>
                                    <div style="margin-bottom: 5px;" class="col-xs-4 text-muted">Identifiant&nbsp;:</div>
                                    <div style="margin-bottom: 5px;" class="col-xs-8"><?php echo $etablissement->identifiant; ?></div>
                                <?php endif; ?>
                                <?php if ($etablissement->cvi): ?>
                                    <div style="margin-bottom: 5px;" class="col-xs-4 text-muted">CVI :</div>
                                    <div style="margin-bottom: 5px;" class="col-xs-8"><?php echo $etablissement->cvi; ?></div>
                                <?php endif; ?>
                                <?php if ($etablissement->ppm): ?>
                                    <div style="margin-bottom: 5px;" class="col-xs-4 text-muted">PPM :</div>
                                    <div style="margin-bottom: 5px;" class="col-xs-8"><?php echo $etablissement->ppm; ?></div>
                                <?php endif; ?>
                                <?php if ($etablissement->no_accises): ?>
                                    <div style="margin-bottom: 5px;" class="col-xs-4 text-muted">N°&nbsp;d'accise&nbsp;:&nbsp;</div>
                                    <div style="margin-bottom: 5px;" class="col-xs-8"><?php echo $etablissement->no_accises; ?></div>
                                <?php endif; ?>
                                <?php if ($etablissement->siret): ?>
                                    <div style="margin-bottom: 5px;" class="col-xs-4 text-muted">SIRET :</div>
                                    <div style="margin-bottom: 5px;" class="col-xs-8"><?php echo formatSIRET($etablissement->siret); ?></div>
                                <?php endif; ?>
                                <?php if ($etablissement->carte_pro && $etablissement->isCourtier()) : ?>
                                    <div style="margin-bottom: 5px;" class="col-xs-4 text-muted">Carte professionnelle : </div>
                                    <div style="margin-bottom: 5px;" class="col-xs-8"><?php echo $etablissement->carte_pro; ?></div>
                                <?php endif; ?>
                                <?php if ($etablissement->region): ?>
                                <div style="margin-bottom: 5px;" class="col-xs-4 text-muted">Région : </div>
                                <div style="margin-bottom: 5px;" class="col-xs-8"><?php echo $etablissement->region; ?></div>
                                <?php endif; ?>
                                <?php if ($etablissement->exist('crd_regime') && $etablissement->crd_regime): ?>
                                    <div style="margin-bottom: 5px;" class="col-xs-4 text-muted">Régime CRD : </div>
                                    <div style="margin-bottom: 5px;" class="col-xs-8"><?php echo $etablissement->crd_regime; ?></div>
                                <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-xs-7" style="border-left: 1px solid #eee">
                        <?php include_partial('compte/visualisationAdresse', array('compte' => $etablissement->getMasterCompte())); ?>
                    </div>
                </div>
                <hr />
                <h5 style="margin-bottom: 15px; margin-top: 15px;" class="text-muted"><strong>Télédéclaration</strong></h5>
                <?php include_partial('compte/visualisationLogin', array('compte' => $etablissement->getMasterCompte())); ?>
                <hr />
                <?php if ($etablissement->commentaire) : ?>
                <h5 class="text-muted" style="margin-bottom: 15px; margin-top: 0px;"><strong>Commentaire</strong></h5>
                <p><?php echo nl2br(html_entity_decode($etablissement->commentaire)); ?></p>
                <hr />
                <?php endif; ?>
                <h5 style="margin-bottom: 15px; margin-top: 15px;" class="text-muted"><strong>Informations complémentaires</strong></h5>
                <?php include_partial('compte/visualisationTags', array('compte' => $etablissement->getMasterCompte())); ?>
                <hr />
                <h5 class="text-muted" style="margin-bottom: 15px; margin-top: 0px;"><strong>Chais</strong></h5>
                <?php if(($etablissement->exist('chais')  && count($etablissement->chais)) || $etablissement->hasLiaisonsChai()): ?>
                <table class="table table-condensed table-bordered table-striped">
                    <thead>
                        <tr>
                            <th class="col-xs-5">Adresse</th>
                            <th class="col-xs-4">Attributs</th>
                            <th class="col-xs-2">Infos</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if($etablissement->exist('chais')):
                            foreach($etablissement->chais as $num => $chai): ?>
                                <tr>
                                    <td><strong><?php echo Anonymization::hideIfNeeded($chai->nom); ?></strong><br /><?php echo Anonymization::hideIfNeeded($chai->adresse); ?><br />
                                    <?php echo $chai->code_postal ?> <?php echo $chai->commune ?></td>
                                    <td><?php echo implode("<br />", array_values($chai->getRawValue()->attributs->toArray(true, false))) ?></td>
                                    <td><?php if($chai->partage): ?>Partagé<br /><?php endif; ?><?php if($chai->archive): ?>Archivé<?php endif; ?></td>
                                    <td class="text-center"><a href="<?php echo url_for("etablissement_edition_chai", array('identifiant' => $etablissement->identifiant, 'num' => $num)); ?>" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-pencil"></span></a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <?php foreach($etablissement->liaisons_operateurs as $liaison): ?>
                            <?php if($chai = $liaison->getChai()): ?>
                            <tr>
                                <td><strong><?php echo Anonymization::hideIfNeeded($chai->nom); ?></strong> (<a href="<?php echo url_for('etablissement_visualisation', $chai->getDocument()) ?>"><?php echo $chai->getDocument()->nom ?></a>)<br /><?php echo Anonymization::hideIfNeeded($chai->adresse); ?><br />
                                <?php echo $chai->code_postal ?> <?php echo $chai->commune ?></td>
                                <td>
                                    <?php foreach ($liaison->attributs_chai as $attribut_chai): ?>
                                        <?php echo EtablissementClient::$chaisAttributsLibelles[$attribut_chai]; ?><br />
                                    <?php endforeach; ?>
                                </td>
                                <td></td>
                                <td></td>
                            </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="text-muted">Aucun chai</p>
                <?php endif; ?>
                <div class="text-right">
                  <a href="<?php echo url_for("etablissement_ajout_chai", array('identifiant' => $etablissement->identifiant)); ?>" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-plus"></span>&nbsp;Ajouter un chai</a>
                </div>
                <hr />
                <h5 class="text-muted" style="margin-bottom: 15px; margin-top: 0px;"><strong>Relations</strong></h5>
                <?php if($etablissement->exist('liaisons_operateurs')  && count($etablissement->liaisons_operateurs)): ?>
                <table class="table table-condensed table-bordered table-striped">
                    <thead>
                        <tr>
                            <th class="col-xs-3">Relation</th>
                            <th class="col-xs-5">Nom</th>
                            <th class="col-xs-4">Numéro CVI/PPM</th>
                            <th class="col-xs-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($etablissement->liaisons_operateurs as $liaison): ?>
                            <tr>
                                <td><?php echo $liaison->getTypeLiaisonLibelle() ?></td>
                                <td><a href="<?php echo url_for('etablissement_visualisation', array('identifiant' => str_replace("ETABLISSEMENT-", "", $liaison->id_etablissement))) ?>"><?php echo Anonymization::hideIfNeeded($liaison->libelle_etablissement); ?></a></td>
                                <td><?php echo 'ID : '.str_replace('ETABLISSEMENT-','',$liaison->id_etablissement); echo ($liaison->cvi)? '<br/>CVI : '.$liaison->cvi : ''; ?><?php echo ($liaison->cvi && $liaison->ppm)? "<br/>" : ""; echo ($liaison->ppm)? 'PPM : '.$liaison->ppm : ''; ?></td>
                                <td class="text-center"><a onclick="return confirm('Étes vous sûr de vouloir supprimer la relations ?')" href="<?php echo url_for("etablissement_suppression_relation", array('identifiant' => $etablissement->identifiant, 'key' => $liaison->getKey())); ?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-trash"></span></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p class="text-muted">Aucune relation</p>
                <?php endif; ?>
                <div class="text-right">
                  <a href="<?php echo url_for("etablissement_ajout_relation", array('identifiant' => $etablissement->identifiant)); ?>" class="btn btn-default btn-sm"><span class="glyphicon glyphicon-plus"></span>&nbsp;Ajouter une relation</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xs-4">
        <?php include_component('societe', 'sidebar', array('societe' => $societe, 'activeObject' => $etablissement)); ?>
    </div>
</div>
