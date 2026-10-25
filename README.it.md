# WP Curl Terminal

[English](README.md) | [Italiano](README.it.md)

**Trasforma un sito WordPress in una versione testuale colorata, consultabile direttamente dal Terminale con `curl`.** Il normale sito HTML rimane invariato per chi lo visita dal browser.

WP Curl Terminal riconosce le richieste provenienti dal client `curl` e restituisce una rappresentazione con colori ANSI degli articoli, delle pagine e degli archivi pubblici di WordPress. Funziona su un normale hosting condiviso con PHP: **non richiede SSH, Telnet, processi aggiuntivi o modifiche alla configurazione del server**.

Sviluppato da **Antonio Troise (Levysoft)**. Per provarlo direttamente:

```bash
curl -L https://www.levysoft.it/
```

## Da dove nasce l'idea

L'ispirazione arriva da [*Fancy Curl Sites* di Alex (nytpu)](https://nytpu.com/gemlog/2026-03-18), scoperto attraverso una [discussione su Hacker News](https://news.ycombinator.com/item?id=50093612). Nel suo esperimento Alex ha reso consultabile il proprio sito dal terminale attraverso testo e sequenze ANSI, integrando questo formato nel suo sistema di pubblicazione. Mi sono chiesto se fosse possibile ottenere un risultato simile con un blog WordPress già esistente, senza cambiare hosting né mantenere copie separate degli articoli.

Il primo tentativo convertiva in testo l'intera pagina HTML generata dal tema e finiva per includere menu, widget e collegamenti duplicati: la homepage superava le 500 righe. Il plugin, invece, recupera direttamente da WordPress i dati pubblici necessari e li presenta in forma compatta. Quando pubblico un nuovo articolo, compare automaticamente anche nella versione terminale.

## Funzionalità

- **Rilevamento automatico di `curl`** attraverso l'header `User-Agent`, con possibilità di richiedere esplicitamente output ANSI, testo semplice oppure HTML originale.
- **Logo ASCII dinamico** generato dal nome del sito impostato in WordPress; per nomi lunghi o caratteri non supportati utilizza un'intestazione compatta. Nessun riferimento fisso a Levysoft nel contenuto visualizzato.
- **Homepage essenziale**, con ultimi articoli, date, URL opzionali e suggerimenti per la navigazione.
- **Articoli e pagine leggibili** con titoli, paragrafi, elenchi, citazioni, collegamenti, testo alternativo delle immagini e blocchi di codice che conservano l'indentazione. I paragrafi vengono adattati a circa 76 colonne.
- **Archivi consultabili**: categorie, tag, risultati di ricerca, autori e date, con indicazioni di paginazione quando disponibili.
- **Quattro combinazioni cromatiche**: ciano/giallo, verde, ambra e monocromatico.
- **Impostazioni integrate in WordPress**: attivazione, riconoscimento automatico, colori, numero di articoli in homepage (da 1 a 30), logo ASCII e URL completi. Il collegamento **Impostazioni** compare direttamente nella lista dei plugin installati.
- **Interfaccia in italiano e inglese**, con file `.po`, `.mo` e `.pot`. Gli articoli restano nella lingua in cui sono pubblicati.
- **Lettura dei soli contenuti pubblici**: nessun accesso remoto a comandi di sistema, nessuna pubblicazione del corpo dei post protetti da password e nessuna esecuzione di shortcode o blocchi dinamici di terze parti durante la conversione.
- **Disinstallazione pulita**, anche in multisito: eliminando il plugin dal pannello di WordPress vengono rimosse le opzioni salvate.

## Requisiti

- WordPress **6.0 o successivo**
- PHP **7.4 o successivo**
- Un hosting WordPress con supporto PHP e HTTP/HTTPS
- `curl` per consultare il sito dal terminale

## Installazione

1. Scarica `wp-curl-terminal.zip` dall'[ultima release GitHub](https://github.com/levysoft/wp-curl-terminal/releases/latest). Seleziona **l'allegato ZIP della release**, non l'archivio automatico del codice sorgente generato da GitHub.
2. Nel pannello WordPress apri **Plugin → Aggiungi nuovo plugin → Carica plugin**.
3. Carica il file ZIP, installalo e attivalo. Quando aggiorni una versione esistente, scegli **Sostituisci** senza eliminare prima il plugin, per conservare le impostazioni.
4. Apri **Impostazioni → Curl Terminal** oppure usa il collegamento **Impostazioni** sotto WP Curl Terminal nella lista dei plugin.
5. Dal Terminale prova `curl -L https://tuodominio.it/`.

In alternativa, estrai lo ZIP e carica via FTP la cartella `wp-curl-terminal/` dentro `wp-content/plugins/`, quindi attiva il plugin da WordPress.

## Esempi di utilizzo

```bash
# Homepage in versione terminale
curl -L https://www.levysoft.it/

# Un articolo pubblico
curl -L https://www.levysoft.it/archivio/2026/10/08/asd-ste100-e-eli5-20-modi-per-farsi-spiegare-meglio-le-cose-dagli-llm/

# Ricerca nel blog
curl -L 'https://www.levysoft.it/?s=python'

# Solo testo, senza colori ANSI
curl -L 'https://www.levysoft.it/?terminal=plain'

# Forza i colori ANSI senza usare il rilevamento di curl
curl -L -H 'Accept: text/x-ansi' https://www.levysoft.it/

# Richiedi l'HTML originale
curl -L -H 'X-Original: true' https://www.levysoft.it/

# Oppure dichiara esplicitamente il formato HTML
curl -L -H 'Accept: text/html' https://www.levysoft.it/
```

## Perché usare `-L`?

Per impostazione predefinita, `curl` non segue automaticamente i reindirizzamenti HTTP. Alcuni siti WordPress reindirizzano da `http://` a `https://` oppure tra indirizzi con e senza `www`. Per esempio, richiedendo un articolo di Levysoft con HTTP, Apache restituisce la pagina `301 Moved Permanently` anziché l'articolo formattato con i colori ANSI:

```bash
curl http://www.levysoft.it/dizionario-ai/
```

```html
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html><head>
<title>301 Moved Permanently</title>
</head><body>
<h1>Moved Permanently</h1>
<p>The document has moved <a href="https://www.levysoft.it/dizionario-ai/">here</a>.</p>
<hr>
<address>Apache Server at www.levysoft.it Port 80</address>
</body></html>
```

Con `-L`, `curl` segue il reindirizzamento verso HTTPS e raggiunge WordPress, che può mostrare l'articolo nella versione terminale:

```bash
curl -L http://www.levysoft.it/dizionario-ai/
```

```text
============================================================
  Dizionario dell’Intelligenza Artificiale
============================================================
```

Lo stesso comportamento riguarda la homepage. Se l'indirizzo richiesto restituisce già il contenuto senza reindirizzamenti, come `https://www.levysoft.it/`, `-L` non genera richieste HTTP aggiuntive. Per questo negli esempi del README ho preferito mantenere sempre l'opzione `-L`.

Quando un articolo è lungo, conviene paginare l'output con **`less -R`**, che mantiene i colori ANSI supportati e permette di scorrere il testo in entrambe le direzioni o cercare una parola:

```bash
curl -fsSL https://www.levysoft.it/ | less -R
```

Per uscire basta premere `q`. Se vuoi provare a mantenere il testo sullo schermo dopo la chiusura di `less`, aggiungi `-X` (il risultato dipende dal terminale):

```bash
curl -fsSL https://www.levysoft.it/ | less -RX
```

`more` può mostrare letteralmente le sequenze ANSI. Con `curl -fsSL`, inoltre, `-s` nasconde l'indicatore di avanzamento, `-S` rende visibili gli errori, `-f` fa fallire le richieste con errori HTTP e `-L` segue i reindirizzamenti. Se vuoi leggere il corpo di una risposta 404, utilizza `curl -L` senza `-f`.

## Come funziona

Il plugin riconosce `curl` nelle richieste destinate alle schermate pubbliche supportate, oppure risponde alla richiesta esplicita `?terminal=ansi`, `?terminal=plain` o all'header `Accept: text/x-ansi`. Per recuperare la pagina HTML si possono usare `X-Original: true` o `Accept: text/html`, salvo che sia stato specificato un parametro `terminal` esplicito, che ha precedenza.

La conversione avviene al momento della richiesta, interrogando WordPress. Non vengono generati file statici, create nuove tabelle, avviati processi esterni o richiesti strumenti sul server. I browser normali, la REST API e le richieste WordPress escluse dal plugin mantengono il loro comportamento abituale.

**Attenzione alla cache:** il plugin imposta intestazioni HTTP appropriate, ma CDN, reverse proxy e cache che rispondono *prima* dell'esecuzione di WordPress potrebbero richiedere esclusioni specifiche per le richieste da terminale.

## Lingue

- **Inglese**: lingua originale del plugin.
- **Italiano** (`it_IT`): traduzione inclusa e caricata automaticamente in base alle impostazioni di WordPress.
- La lingua pubblica del sito determina le etichette nel terminale; il pannello amministrativo può utilizzare la lingua del profilo dell'utente.
- Per aggiungere una traduzione si può partire dal file `languages/wp-curl-terminal.pot`.

## Impostazioni e disinstallazione

Le preferenze vengono salvate nell'opzione `wct_settings` di WordPress (normalmente nella tabella `wp_options`, con prefisso variabile). Disattivazione e aggiornamenti conservano le impostazioni. L'eliminazione dalla schermata **Plugin** di WordPress esegue `uninstall.php`, rimuovendo l'opzione, anche nei singoli siti di una rete multisito. **Cancellare soltanto i file via FTP non avvia la procedura di disinstallazione.**

## Limiti e sicurezza

La modalità terminale è pensata per contenuti pubblicati e accessibili senza autenticazione. Impaginazioni particolari, page builder, shortcode e blocchi dinamici potrebbero non comparire come nel browser. Il plugin non apre porte Telnet o SSH e non permette l'esecuzione di comandi sul server. Come per qualsiasi altro plugin WordPress, è opportuno mantenerlo aggiornato e verificare la configurazione della cache del sito.

## Download e codice sorgente

- [Ultima release con ZIP installabile](https://github.com/levysoft/wp-curl-terminal/releases/latest)
- [Download ZIP diretto da Levysoft](https://www.levysoft.it/plugin_levysoft/wp-curl-terminal-v1.2.1.zip)
- [Repository GitHub e segnalazione problemi](https://github.com/levysoft/wp-curl-terminal)
- [Sito Levysoft](https://www.levysoft.it/)

Per installarlo da WordPress utilizza l'allegato `wp-curl-terminal.zip`, che contiene la cartella `wp-curl-terminal/`.

## Licenza e riconoscimenti

Distribuito con licenza **GPL-2.0-or-later**. Vedi [LICENSE](LICENSE).

Autore: **Antonio Troise (Levysoft)**  
Sito del plugin: https://www.levysoft.it/  
Sito dell'autore: https://www.levysoft.it/

Ispirato a [*Fancy Curl Sites* di Alex (nytpu)](https://nytpu.com/gemlog/2026-03-18) e agli esperimenti precedenti citati nel suo articolo.
