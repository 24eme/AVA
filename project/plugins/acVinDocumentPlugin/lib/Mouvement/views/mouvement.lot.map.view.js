function (doc) {
   if (!doc.mouvements_lots) {
     return;
   }
   for(identifiant in doc.mouvements_lots) {
     for(key in doc.mouvements_lots[identifiant]) {
       lot = doc.mouvements_lots[identifiant][key];
       emit([null, null, lot.statut, lot.region, lot.date, lot.origine_document_id], lot);
       emit([null, lot.campagne, lot.statut, lot.region, lot.date, lot.origine_document_id], lot);
       emit([lot.declarant_identifiant, lot.campagne, lot.statut, lot.region, lot.date, lot.origine_document_id], lot);
     }
   }
 }
