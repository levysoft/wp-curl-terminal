WP Curl Terminal 1.2.1 - Istruzioni in italiano

Il plugin presenta il sito WordPress in forma testuale, con colori ANSI,
quando viene visitato attraverso curl. Il sito tradizionale resta invariato.
Non richiede SSH, Telnet o modifiche al server.

Installazione:
1. In WordPress: Plugin > Aggiungi plugin > Carica plugin.
2. Carica lo ZIP e, in caso di aggiornamento, sostituisci la versione precedente.
3. Apri il collegamento Impostazioni nella lista dei plugin oppure vai a Impostazioni > Curl Terminal.
4. Prova da un terminale, sostituendo example.org con il tuo dominio:

   curl -L https://example.org/
   curl -L https://example.org/tuo-articolo/
   curl -L 'https://example.org/?terminal=plain'
   curl -L -H 'X-Original: true' https://example.org/

Lingue:
* Inglese: lingua originale del plugin.
* Italiano: traduzione inclusa nella cartella languages.
La lingua viene scelta automaticamente in base alle impostazioni di WordPress
(Impostazioni > Generali > Lingua del sito). Gli articoli restano nella lingua
di pubblicazione e non vengono tradotti automaticamente.

Impostazioni e disinstallazione:
Le impostazioni sono conservate nell'opzione wct_settings di WordPress.
Aggiornare o disattivare il plugin non cancella le preferenze. Eliminando il
plugin dalla schermata Plugin di WordPress, uninstall.php rimuove l'opzione.
La sola eliminazione dei file via FTP non esegue la disinstallazione.

Sicurezza e cache:
* Il plugin espone solo i contenuti pubblici e non esegue comandi remoti.
* Non esegue shortcode e blocchi dinamici di terze parti nel terminale.
* In presenza di cache/CDN che intercettano le richieste prima di WordPress,
  potrebbe essere necessario escludere le richieste curl.

Autore: Antonio Troise (Levysoft)
Sito: https://www.levysoft.it/
Ispirazione: https://nytpu.com/gemlog/2026-03-18
