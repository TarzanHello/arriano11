# Auto-sync calendario (iCal import)

Il sito importa le disponibilità da Booking (o altro canale iCal) e aggiorna automaticamente il calendario:

- **Ogni volta** che qualcuno invia una richiesta di prenotazione / avvia un pagamento.
- Tramite endpoint protetto per **cron** (consigliato) da chiamare **ogni 5 minuti**.

## Endpoint cron (protetto da token)

URL (esempio):
`https://viaarriano11.it/api/cron_sync.php?token=IL_TUO_TOKEN`

Il token è salvato in `/data/store.json` nella chiave:

`settings.cron_sync_token`

Puoi copiarlo anche dall’area admin → tab **Sync iCal**.

### Esempio comando (Linux)

```bash
curl -fsS "https://viaarriano11.it/api/cron_sync.php?token=IL_TUO_TOKEN" >/dev/null
```

Imposta il cron a ***/5 * * * ***.

## Nota su /data

Se stai aggiornando un sito già in produzione, **non sovrascrivere** la cartella `/data/` se vuoi mantenere prenotazioni e impostazioni.


### Alternativa più discreta (header)

Per evitare di mettere il token nell’URL, puoi inviarlo come header:

```bash
curl -fsS -H "X-Cron-Token: IL_TUO_TOKEN" "https://viaarriano11.it/api/cron_sync.php" >/dev/null
```
