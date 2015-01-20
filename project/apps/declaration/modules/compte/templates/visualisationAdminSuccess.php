<?php echo use_helper('Date'); ?>
<div class="page-header">
    <?php if(!$compte->date_archivage): ?>
    <a href="<?php echo url_for('compte_archiver', $compte) ?>" class="btn btn-sm btn-default btn-default-step pull-right"><span class="glyphicon glyphicon-folder-open"></span>&nbsp;&nbsp;Archiver</a>
    <?php endif; ?>
    <h2><?php echo $compte->nom_a_afficher ?> <small><?php echo CompteClient::getInstance()->getCompteTypeLibelle($compte->type_compte); ?></span> - <?php echo $compte->identifiant; ?></small></h2>
</div>

<div class="row col-xs-12">
    <?php if($compte->date_archivage): ?>
    <div class="alert alert-warning">
        Compte archivé le <?php echo format_date($compte->date_archivage, "dd/MM/yyyy", "fr_FR"); ?>
        <small><a href="<?php echo url_for('compte_desarchiver', $compte) ?>" class="text-danger">(annuler l'archivage)</a></small>
    </div>
    <?php endif; ?>
    <div class="row">
        <div class="col-xs-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3>Identité</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <label class="col-xs-6">Nom / Raison Sociale </label>    
                        <div class="col-xs-6">
                            <?php echo $compte->nom_a_afficher; ?>
                        </div>                
                    </div>
                    <?php if ($compte->cvi): ?>
                        <div class="row">
                            <label class="col-xs-6">CVI </label>    
                            <div class="col-xs-6">
                                <?php echo $compte->cvi; ?>
                            </div>                
                        </div>
                    <?php endif; ?>
                    <?php if ($compte->siret): ?>
                        <div class="row">
                            <label class="col-xs-6">SIRET / SIREN</label>    
                            <div class="col-xs-6">
                                <?php echo $compte->siret; ?>
                            </div>                
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xs-6">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3>Coordonnées</h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <div class="row">
                            <label class="col-xs-6">Adresse</label>    
                            <div class="col-xs-6">
                                <?php echo $compte->adresse; ?>
                            </div>                
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="row">
                            <label class="col-xs-6">Code postal</label>    
                            <div class="col-xs-6">
                                <?php echo $compte->code_postal; ?>
                            </div>                
                        </div>
                    </div>                             
                    <div class="form-group">
                        <div class="row">
                            <label class="col-xs-6">Commune</label>    
                            <div class="col-xs-6">
                                <?php echo $compte->commune; ?>
                            </div>                
                        </div>
                    </div>
                    <?php if ($compte->telephone_bureau): ?>
                        <div class="form-group">
                            <div class="row">
                                <label class="col-xs-6">Téléphone bureau</label>    
                                <div class="col-xs-6">
                                    <?php echo $compte->telephone_bureau; ?>
                                </div>                
                            </div>                        
                        </div>
                    <?php endif; ?>
                    <?php if ($compte->telephone_mobile): ?>
                        <div class="form-group">
                            <div class="row">
                                <label class="col-xs-6">Téléphone mobile</label>    
                                <div class="col-xs-6">
                                    <?php echo $compte->telephone_mobile; ?>
                                </div>                
                            </div>     
                        </div>
                    <?php endif; ?>
                    <?php if ($compte->telephone_prive): ?>
                        <div class="form-group">
                            <div class="row">
                                <label class="col-xs-6">Téléphone privé</label>    
                                <div class="col-xs-6">
                                    <?php echo $compte->telephone_prive; ?>
                                </div>                
                            </div>     
                        </div>
                    <?php endif; ?>
                    <?php if ($compte->fax): ?>
                        <div class="form-group">
                            <div class="row">
                                <label class="col-xs-6">Fax</label>    
                                <div class="col-xs-6">
                                    <?php echo $compte->fax; ?>
                                </div>                
                            </div>   
                        </div>
                    <?php endif; ?>
                    <?php if ($compte->email): ?>
                        <div class="form-group"> 
                            <div class="row">
                                <label class="col-xs-6">Email</label>    
                                <div class="col-xs-6">
                                    <a href="mailto:<?php echo $compte->email; ?>"><?php echo $compte->email; ?></a>
                                </div>                
                            </div>     
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xs-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3>Informations complémentaire</h3>
                </div>
                <div class="panel-body">

                    <div class="form-group">
                        <label class="col-xs-3">Type de compte :</label> 
                        <label class="col-xs-9 "><?php echo CompteClient::getInstance()->getCompteTypeLibelle($compte->getTypeCompte()); ?></label>
                    </div>
                    <br/>  
                    <?php if ($compte->hasAttributs()): ?>
                        <div class="form-group">
                            <label class="col-xs-3">Attributs :</label> 
                            <div>                                                   
                                <?php foreach ($compte->getInfosAttributs() as $attribut_code => $attribut_libelle): ?>
                                    <span class="label label-success"><?php echo $attribut_libelle ?></span>
                                <?php endforeach; ?>                           
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($compte->hasProduits()): ?>
                        <div class="form-group">
                            <label class="col-xs-3">Produits :</label> 
                            <div>                         
                                <?php foreach ($compte->getInfosProduits() as $produit_code => $produit_libelle): ?>
                                    <span class="label label-info"><?php echo $produit_libelle ?></span>
                                <?php endforeach; ?>

                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($compte->hasSyndicats()): ?>
                        <div class="form-group">
                            <label class="col-xs-3">Syndicats :</label> 
                            <div>                         
                                <?php foreach ($compte->getInfosSyndicats() as $syndicat_code => $syndicat_libelle): ?>
                                    <span class="label label-danger" style="margin: 2px;"><?php echo $syndicat_libelle ?></span>
                                <?php endforeach; ?>

                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($compte->hasManuels()): ?>
                        <div class="form-group">
                            <label class="col-xs-3">Mots clés :</label> 
                            <div>           
                                <?php foreach ($compte->getInfosManuels() as $tag_manuel_code => $tag_manuel): ?>
                                    <span class="label label-default" style="margin: 2px;"><?php echo $tag_manuel ?></span>
                                <?php endforeach; ?>                               
                            </div>
                        </div>
                    <?php endif; ?>  
                </div>
            </div>
        </div>
    </div>
    <?php if ($compte->isTypeCompte(CompteClient::TYPE_COMPTE_ETABLISSEMENT) && count($compte->chais)): ?>
        <div class="row">
            <div class="col-xs-12">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3>Chais</h3>
                    </div>
                    <div class="panel-body">
                        <div class="form-group">
                            <div class="row"> 
                                <?php foreach ($compte->chais as $key => $chai) : ?>
                                    <div class="col-xs-4 text-center">
                                        <strong>Chai N° <?php echo $key + 1; ?></strong>                                    
                                    </div>  
                                    <div class="col-xs-6 text-center">
                                        <?php echo $chai->adresse . ' ' . $chai->code_postal . ' ' . $chai->commune; ?> 
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>  
    <?php endif; ?>

    <div class="row row-margin row-button">
        <div class="col-xs-4">
            <a href="<?php echo url_for("compte_recherche") ?>" class="btn btn-primary btn-lg btn-upper"><span class="eleganticon arrow_carrot-left"></span>&nbsp;&nbsp;Retour à la recherche</a>
        </div>
        <div class="col-xs-4 text-center">
            <a class="btn btn-lg btn-warning" href="<?php echo url_for('compte_modification_admin', $compte) ?>">Modifier</a>
        </div>
        <?php if ($compte->isTypeCompte(CompteClient::TYPE_COMPTE_ETABLISSEMENT)): ?>
            <div class="col-xs-4 text-right">               

                <a class="btn btn-default btn-lg btn-upper" href="<?php echo url_for('compte_redirect_espace_etablissement', $compte); ?>">Espace etablissement&nbsp;&nbsp;<span class="eleganticon arrow_carrot-right"></span></a>
            </div>
        <?php endif; ?>
    </div>
</div>