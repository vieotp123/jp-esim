# jpesim systemd units

Snapshot of the systemd timer units running in production, kept under version
control so they can be reapplied to a new host or audited via PR review.

## Units

| Unit | Schedule | What it does |
|---|---|---|
| `jpesim-ctv-fulfillment-poll.timer` | every 2 min | Sync QR/ICCID for paid CTV orders missing them |
| `jpesim-email-retry.timer` | every 15 min | Retry sending eSIM delivery emails (`scripts/email_queue_retry.php`) |
| `jpesim-provider-alert.timer` | every 10 min | Email alert if provider error rate breaches threshold (`scripts/provider_error_alert.php`) — no-op until `ALERT_EMAIL` set in db_config.php |
| `jpesim-db-backup.timer` | daily 04:00 | mysqldump → gzip into `/home/levanrin2404/db_backups/`, 7-day retention |

## Install on a new host

```bash
sudo cp systemd/jpesim-*.service systemd/jpesim-*.timer /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now jpesim-ctv-fulfillment-poll.timer
sudo systemctl enable --now jpesim-email-retry.timer
sudo systemctl enable --now jpesim-provider-alert.timer
sudo systemctl enable --now jpesim-db-backup.timer
```

Logs go to `/var/log/jpesim/*.log` (rotated weekly via `/etc/logrotate.d/jpesim`).
