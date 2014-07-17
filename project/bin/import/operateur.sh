#!/bin/bash

. bin/config.inc

WORKDIR=$TMPDIR/import_operateurs
DATADIR=data/import/extravitis/operateur
mkdir $WORKDIR 2> /dev/null

#---CVI---
cat $DATADIR/EVV.csv | iconv -f iso88591 -t utf8 | tr -d "\r" | cut -d ";" -f 1,3,4,5,6,7,9,10,11,19 | sort -t ";" -k 1,1 | sed 's/ ;/;/g' | grep -E ";6[0-9]{7,12};"  > $WORKDIR/evv.csv

cat $DATADIR/PPM_EVV_MFV.csv | iconv -f iso88591 -t utf8 | tr -d "\r" | cut -d ";" -f 3,4 | sort | uniq | sort -t ";" -k 2,2  > $WORKDIR/id_evv.csv

join -t ";" -1 2 -2 1 $WORKDIR/id_evv.csv $WORKDIR/evv.csv | sort -t ";" -k 2,2 | cut -d ";" -f 1,2,3 | sort -t ";" -k 2,2  > $WORKDIR/id_evv_cvi.csv

cat $WORKDIR/id_evv_cvi.csv | sort -t ";" -k 1,1 > $WORKDIR/id_evv_cvi.sort_evv.csv
cat $WORKDIR/evv.csv | cut -d ";" -f 2,3,4,5,6,7,8,9,10 | sed -r 's/^([0-9]+);/\1;1.CVI ;/' > $WORKDIR/cvi.csv

#---COMMUNICATION---
cat $DATADIR/COMMUNICATION.csv | iconv -f iso88591 -t utf8 | tr -d "\r" | cut -d ";" -f 1,3,4,8,9,10,11,12,13,14 | sort -t ";" -k 2,2 > $WORKDIR/communication.csv

join -t ";" -1 2 -2 2 $WORKDIR/id_evv_cvi.csv $WORKDIR/communication.csv | cut -d ";" -f 3,7,8,9,10,11,12 | sed -r 's/^([0-9]+);/\1;3.COMM;;;;;;;;;;/' > $WORKDIR/communication_cvi.csv

#---COORDONNEES---

cat $DATADIR/COORDONNEES.csv | iconv -f iso88591 -t utf8 | tr -d "\r" | cut -d ";" -f 1,3,4,5,6,7,8,10,11,12,13 | sort -t ";" -k 2,2 > $WORKDIR/coordonnees.csv

join -t ";" -1 2 -2 2 $WORKDIR/id_evv_cvi.csv $WORKDIR/coordonnees.csv | cut -d ";" -f 3,7,8,9,10,11,12,13 | sed -r 's/^([0-9]+);/\1;3.COOR;;/' > $WORKDIR/coordonnees_cvi.csv

#---ATTRIBUT---
cat $DATADIR/PPM_ATTRIBUTS.csv | iconv -f iso88591 -t utf8 | tr -d "\r" | sort -t ";" -k 3,3 > $WORKDIR/attributs.csv

cat $DATADIR/PPM_ATTRIBUT_REF.csv | iconv -f iso88591 -t utf8 | tr -d "\r" | sort -t ";" -k 1,1 > $WORKDIR/attributs_ref.csv

join -t ";" -1 3 -2 1 $WORKDIR/attributs.csv $WORKDIR/attributs_ref.csv | sort -t ";" -k 3,3 > $WORKDIR/attributs.join.csv

join -t ";" -1 2 -2 3 $WORKDIR/id_evv_cvi.csv $WORKDIR/attributs.join.csv | cut -d ";" -f 3,7 | sed -r 's/^([0-9]+);/\1;5.ATTR;;;;;;;;;/' > $WORKDIR/attributs_cvi.csv

#---CHAI---
cat $DATADIR/CHAI.csv | iconv -f iso88591 -t utf8 | tr -d "\r" | cut -d ";" -f 1,4,5,6,7,8,10,11,12,21,22,23 | sort -t ";" -k 1,1 > $WORKDIR/chai.csv
cat $DATADIR/EVV_CHAI.csv | iconv -f iso88591 -t utf8 | tr -d "\r" | cut -d ";" -f 1,2 | sort -t ";" -k 1,1 > $WORKDIR/evv_chai.csv

join -t ";" -1 1 -2 1 $WORKDIR/id_evv_cvi.sort_evv.csv $WORKDIR/evv_chai.csv | cut -d ";" -f 3,4 | sort -t ";" -k 2,2 > $WORKDIR/id_chai_cvi.csv
join -t ";" -1 2 -2 1 $WORKDIR/id_chai_cvi.csv $WORKDIR/chai.csv | cut -d ";" -f 2,3,4,5,6,7,8,9,10,11,12,13 | sed -r 's/^([0-9]+);/\1;2.CHAI;/' | sed -r 's/(;[0-9-]+;+[0-9-]+;[0-9-]+)$/;\1/' | sed -r 's/0;([0-9-]+;[0-9-]+)$/,CHAI_DE_VINIFICATION-1;\1/' | sed -r 's/-1;0;([0-9-]+)$/,CENTRE_DE_CONDITIONNEMENT-1;-1;\1/' | sed -r 's/-1;-1;0$/,LIEU_DE_STOCKAGE/' | sed 's/-1;-1;-1$//' | sed 's/;,/;/' | sed -r 's/^([0-9]+;2.CHAI);([a-Z0-9]*);(.+)$/\1;\3;\2/' > $WORKDIR/chai_cvi.csv

#---FINAL---
echo "#cvi;type ligne;raison sociale;adresse 1;adresse 2;adresse 3;commune;code postal;canton;actif;attributs;type;tel;fax;portable;email;web" > $WORKDIR/operateurs.csv
cat $WORKDIR/cvi.csv $WORKDIR/chai_cvi.csv $WORKDIR/communication_cvi.csv $WORKDIR/coordonnees_cvi.csv $WORKDIR/attributs_cvi.csv | sort | sed -r 's/[ ]+/ /g' | sed -r 's/\t/ /g' >> $WORKDIR/operateurs.csv



