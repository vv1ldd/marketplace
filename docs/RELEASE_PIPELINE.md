# Meanly Release Pipeline

Launch-critical local checks:

```bash
php artisan meanly:seo-readiness
php artisan meanly:launch-readiness --quick --run-tests
```

Post-deployment Gate 2 checks:

```bash
php artisan meanly:deployment-readiness --domain=https://meanly.com
php artisan meanly:launch-readiness --quick --domain=https://meanly.com
```

Admin Passkey enrollment pill:

```bash
php artisan meanly:admin-passkey-pill admin@meanly.com --domain=https://meanly.com
```

`demand_gaps=0` is a warning, not a first-day launch blocker. Demand intelligence is expected to fill after traffic starts producing search logs.
