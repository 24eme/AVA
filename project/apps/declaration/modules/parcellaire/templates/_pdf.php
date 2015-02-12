<?php use_helper("Date"); ?>
<?php use_helper('Parcellaire') ?>
<?php use_helper('TemplatingPDF') ?>
<?php use_helper('Float') ?>
<style>
<?php echo styleParcellaire(); ?>
</style>

<span class="h3Alt">&nbsp;Exploitation&nbsp;</span><br/>
<table class="tableAlt"><tr><td>
            <table border="0">
                <tr>
                    <td style="width: 420px;">&nbsp;Nom : <i><?php echo $parcellaire->declarant->raison_sociale ?></i></td>

                    <td><?php if ($parcellaire->declarant->cvi): ?>N° CVI : <i><?php echo $parcellaire->declarant->cvi ?></i><?php else: ?>&nbsp;<?php endif; ?></td>
                </tr>
                <tr>
                    <td>&nbsp;Adresse : <i><?php echo $parcellaire->declarant->adresse ?></i></td>
                    <td>N° SIRET : <i><?php echo $parcellaire->declarant->siret ?></i></td>
                </tr>
                <tr>
                    <td>&nbsp;Commune : <i><?php echo $parcellaire->declarant->code_postal ?>, <?php echo $parcellaire->declarant->commune ?></i></td>
                    <td></td>
                </tr>
                <tr>
                    <td>&nbsp;Tel / Fax : <i><?php echo $parcellaire->declarant->telephone ?> / <?php echo $parcellaire->declarant->fax ?></i></td>
                    <td></td>
                </tr>
                <tr>
                    <td>&nbsp;Email : <i><?php echo $parcellaire->declarant->email ?></i></td>
                    <td></td>
                </tr>
            </table>
        </td></tr></table>
<br /> 
<?php if (count($parcellesByLieu->acheteurs)): ?>
    <div><span class="h3">&nbsp;Acheteurs&nbsp;</span></div>
    <table class="table" border="1" cellspacing=2 cellpadding=0 style="text-align: right;">   
        <tr>
            <th class="td" style="border-top: none; text-align: left; width: 637px; vertical-align: middle;"><?php echo tdStart() ?>
                <?php
                $list_acheteur = '';
                foreach ($parcellesByLieu->acheteurs as $cvi => $acheteur) :
                    ?>
                    <?php $list_acheteur.= '&nbsp;' . $acheteur->nom . ','; ?>
                <?php
                endforeach;
                $list_acheteur = substr($list_acheteur, 0, strlen($list_acheteur) - 1);
                ?>
    <?php echo $list_acheteur; ?> 
            </th>
        </tr>
    </table>
    <br />
    <br />
<?php endif; ?>
<div><span class="h3">&nbsp;<?php echo $parcellesByLieu->appellation_libelle; ?>&nbsp;-&nbsp;<?php echo $parcellesByLieu->lieu_libelle; ?>&nbsp;</span></div>

<table class="table" border="1" cellspacing=0 cellpadding=0 style="text-align: right;">
    <tr>
        <th class="th" style="text-align: center; width: 170px;">&nbsp;Commune</th>        
        <th class="th" style="text-align: center; width: 74px;">Section</th>       
        <th class="th" style="text-align: center; width: 74px;">Numéro</th>
        <th class="th" style="text-align: center; width: 190px;">Cépage</th>
        <th class="th" style="text-align: center; width: 130px;">Surface</th>
    </tr>
<?php foreach ($parcellesByLieu->parcelles as $detailHash => $parcelle): ?>
        <tr>
            <td class="td" style="text-align:left;"><?php echo tdStart() ?>&nbsp;<?php echo $parcelle->parcelle->commune ?>&nbsp;</td>        
            <td class="td" style="text-align:right;"><?php echo tdStart() ?>&nbsp;<?php echo $parcelle->parcelle->section ?>&nbsp;</td>       
            <td class="td" style="text-align:right;"><?php echo tdStart() ?>&nbsp;<?php echo $parcelle->parcelle->numero_parcelle ?>&nbsp;</td>
            <td class="td" style="text-align:right;"><?php echo tdStart() ?>&nbsp;<?php echo $parcelle->cepage_libelle ?>&nbsp;</td>
            <td class="td" style="text-align:right;"><?php echo tdStart() ?>&nbsp;<?php echo $parcelle->parcelle->superficie ?>&nbsp;<small>ares</small>&nbsp;&nbsp;&nbsp;</td>
        </tr>
<?php endforeach; ?>
</table>

<br />
<br />