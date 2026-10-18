ARUBA / PHP — Via Arriano 11

1) Caricamento su Aruba
- Carica TUTTA la cartella del sito (tutti i file e le cartelle) nella root del tuo hosting Aruba.
  Esempio: /public_html/ (o /htdocs/ in base al tuo piano).
- Il file di ingresso e' index.php.

2) Permessi (fondamentale)
Il sito salva impostazioni, prenotazioni e regole del calendario in:
  /data/store.json

- Assicurati che la cartella /data sia scrivibile da PHP.
  Su hosting condivisi puo' servire CHMOD 775 o 777.
- Se necessario rendi scrivibile anche /data/store.json.

Se i permessi non sono corretti:
- le richieste/prenotazioni dal calendario non verranno salvate
- l'area admin non riuscira' a salvare impostazioni

3) Area Admin (protetta da login)
- URL: /admin/login.php

Primo accesso:
- Al primo avvio il sito genera username/password admin e li salva in:
    /data/INITIAL_ADMIN_CREDENTIALS.txt
  (non accessibile via web; leggilo via FTP).
- Dopo il primo login cambia subito username/password in admin e poi ELIMINA quel file.

Reset password:
- Puoi farlo da /admin (tab “Impostazioni”) oppure modificando manualmente /data/store.json
  (chiave admin_pass_hash) via FTP.

4) Calendario prenotazioni (pubblico)
- URL: /booking.php

Legenda:
- Disponibile
- Occupato
- Chiuso

Flusso:
- L'ospite seleziona check-in e check-out, vede il totale e inserisce i dati.
- Se Stripe e' configurato, viene reindirizzato al pagamento sicuro per confermare.
- Se Stripe NON e' configurato, viene inviata una richiesta che puoi gestire in admin.

5) Prezzi e regole (admin)
In admin -> tab “Calendario”:
- puoi selezionare uno o piu' giorni (anche con SHIFT per un intervallo)
- impostare prezzo per notte
- chiudere / aprire il giorno (forzatura)
- impostare la durata minima del soggiorno

In admin -> tab “Impostazioni”:
- Prezzo base (usato quando non imposti un prezzo specifico giorno per giorno)
- Pulizie, valuta e durata minima predefinita

6) Sync con Booking.com (iCal)
ATTENZIONE: iCal sincronizza le DATE (disponibilita'), NON i prezzi.

6.1 Import (Booking -> questo sito)
- In Booking: cerca la funzione "Import calendar" e copia il link iCal
- In admin: tab “Impostazioni” -> incolla il link in “Booking iCal URL (import)”
- Vai su tab “Sync iCal” -> “Esegui sync adesso”

Da quel momento il sito importerà automaticamente le prenotazioni di Booking
(e le mostrerà come “Occupato”).

6.2 Export (questo sito -> Booking)
Il link export include un token privato.
Vai in Admin -> tab “Sync iCal” e copia l'URL export mostrato li.

7) Pagamenti con Stripe (opzionale ma consigliato)
In admin -> tab “Impostazioni” -> sezione “Pagamento (Stripe)” inserisci:
- Stripe Secret Key (sk_live_...) 
- Webhook Secret (whsec_...)
- Abilita "Richiedi pagamento per confermare"
- Caparra (%): percentuale del totale da richiedere subito (es. 30%)

Webhook (consigliato):
- Endpoint da configurare in Stripe:
  https://TUODOMINIO.it/api/stripe_webhook.php
- Eventi: "checkout.session.completed" (sufficiente)

Il webhook serve per confermare la prenotazione anche se l'utente chiude la pagina
prima di tornare al sito.

8) Note tecniche
- Nessun npm, nessun database: solo PHP + JSON
- Sicurezza: la cartella /data e' protetta da .htaccess

Buon lavoro!


Sicurezza / hardening (nota):
- /data/store.json viene scritto in modo atomico e viene mantenuto un backup /data/store.json.bak
  (utile per recupero se succede un imprevisto).
- Host header allowlist e URL canonica: controlla /lib/config.php (allowed_hosts / canonical_host).
