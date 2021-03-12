function (doc) {
  if (!doc.mouvements_lots) {
    return;
  }
  for(identifiant in doc.mouvements_lots) {
    for(key in doc.mouvements_lots[identifiant]) {
      mouvement = doc.mouvements_lots[identifiant][key];

      emit([
        mouvement.statut
      ], mouvement);
    }
  }
}
