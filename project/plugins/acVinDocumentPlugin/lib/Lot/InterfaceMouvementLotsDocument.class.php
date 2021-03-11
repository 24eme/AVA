<?php

interface InterfaceMouvementLotsDocument
{
    public function clearMouvementsLots();
    public function addMouvementLot($mouvement);
    public function generateMouvementsLots();
}
