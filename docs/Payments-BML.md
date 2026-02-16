# BML Connect Payment Gateway (Redirect Method)

Production-grade integration for Bank of Maldives (BML) Connect using the **Redirect Method**. The **webhook is the PRIMARY** method for capturing payment responses; the redirect return is not authoritative and only shows a "processing" state until the webhook confirms.

**Official reference:** [BML Connect – Redirect Method](https://bankofmaldives.stoplight.io/docs/bml-connect/uhpp94va2rhax-redirect-method). This implementation follows the **v2** API: `POST/GET .../v2/transactions`, request field `localId`, response `id` and `state`, redirect query params `transactionId`, `state`, `signature`. We also call **Get Transaction** on return (when `transactionId` is present) so the UI can show the result without waiting for the webhook.

## Flow

1. User selects course and proceeds to checkout.
2. Checkout page shows fee, currency (MVR), merchant outlet country, and policy links. User **must** accept Terms, Refund Policy, and Privacy Policy (checkbox).
3. On "Pay", our server creates a `Payment` record and calls BML **Create Transaction (v2)** with `amount` (laari), `currency`, `localId`, `redirectUrl`; optional `webhook`, `provider`, `paymentPortalExperience`. User is redirected to the returned `url` (or `shortUrl`).
4. After payment, BML redirects to our return URL with `?transactionId=...&state=...&signature=...`. We save the query, optionally call **Get Transaction** to update status immediately, and show "Payment processing" with polling.
5. **BML webhook** (primary) notifies us of final status. We update `Payment` and finalize enrollment.
6. Return page polling sees `confirmed` and shows success.

## Environment variables

Set in `.env`:

| Variable | Description |
|----------|-------------|
| `BML_BASE_URL` | API base URL (sandbox: `https://api.uat.merchants.bankofmaldives.com.mv/public`) |
| `BML_APP_ID` | Application / client ID from BML |
| `BML_API_KEY` | API key / client secret from BML |
| `BML_MERCHANT_ID` | Optional merchant ID |
| `BML_WEBHOOK_SECRET` | Secret for webhook signature verification (HMAC) |
| `BML_WEBHOOK_URL` | Full URL BML will call (e.g. `https://yourdomain.com/webhooks/bml`) |
| `BML_RETURN_URL` | Optional; if not set, return URL is built per payment |
| `BML_PROVIDER` | Optional: `card`, `bmlpay`, etc.; omit to let user choose on BML page |
| `BML_DEFAULT_CURRENCY` | Default `MVR` |
| `BML_ENVIRONMENT` | `sandbox` or `production` |
| `BML_WEBHOOK_SIGNATURE_HEADER` | Header name for signature (default `X-BML-Signature`) |
| `BML_WEBHOOK_IP_ALLOWLIST` | Optional comma-separated IPs for webhook |
| `BML_EXTERNAL_TERMS_URL` | Optional; if set, sent as `paymentPortalExperience.externalWebsiteTermsUrl` (include BML T&C link) |
| `BML_EXTERNAL_TERMS_ACCEPTED` | Set to `true` if user accepted T&C on your site (default `true`) |
| `BML_SKIP_PROVIDER_SELECTION` | Set to `true` to skip provider selection when using a fixed `BML_PROVIDER` |
| `LOG_PAYMENTS_DAYS` | Days to keep payment log files (default 90) |

## Step-by-step: Testing (sandbox) setup

Follow these steps to run the **testing** (sandbox) payment gateway end-to-end.

### 1. Get BML sandbox access

- Contact Bank of Maldives (or use their developer signup) to get access to **BML Connect UAT/sandbox**.
- In the **BML Merchant Dashboard (UAT)** you will get:
  - **App ID** (or Client ID)
  - **API Key** (or Client Secret)
  - Optionally: **Merchant ID**, **Webhook signing secret**

### 2. Expose your app to the internet (for webhooks)

BML must be able to send webhooks to your app. On your **local machine** that usually means a tunnel:

- **Option A – ngrok**  
  - Install [ngrok](https://ngrok.com/), then run:  
    `ngrok http 8000`  
    (use your app’s port if different, e.g. `ngrok http 80`).
  - Copy the **HTTPS** URL (e.g. `https://abc123.ngrok.io`). You’ll use this as your “public” base URL.

- **Option B – Deploy to a staging server**  
  - Deploy the app to a server with a public URL (e.g. `https://staging.akuru.mv`). Use that as the base URL below.

### 3. Configure `.env` for testing

In your project root, copy from example and set at least:

```env
# App must be reachable at this URL (ngrok URL or staging URL)
APP_URL=https://YOUR-NGROK-OR-STAGING-URL

# BML UAT (testing)
BML_BASE_URL=https://api.uat.merchants.bankofmaldives.com.mv/public
BML_APP_ID=your_sandbox_app_id_from_bml
BML_API_KEY=your_sandbox_api_key_from_bml
BML_ENVIRONMENT=sandbox

# Webhook: BML will POST to this URL (must be HTTPS and reachable from the internet)
BML_WEBHOOK_URL=https://YOUR-NGROK-OR-STAGING-URL/webhooks/bml

# Optional: leave empty to use per-payment return URL
BML_RETURN_URL=

# Optional: set if BML gives you a webhook signing secret
BML_WEBHOOK_SECRET=
```

- Replace `YOUR-NGROK-OR-STAGING-URL` with your actual base (e.g. `https://abc123.ngrok.io` or `https://staging.akuru.mv`).
- If BML does **not** provide a webhook secret in UAT, leave `BML_WEBHOOK_SECRET` empty; the app will still accept webhooks (signature check is skipped when secret is empty).

### 4. Configure BML UAT portal

In the **BML Connect UAT / Merchant Dashboard**:

1. **Webhooks**
   - Set **Webhook URL** to:  
     `https://YOUR-NGROK-OR-STAGING-URL/webhooks/bml`
   - If they show a **Webhook secret**, copy it into `.env` as `BML_WEBHOOK_SECRET`.

2. **Redirect / allowed URLs** (if the portal has this)
   - Add your base domain so BML can redirect users back (e.g. `abc123.ngrok.io` or `staging.akuru.mv`).

### 5. Run the app and test a payment

1. Start the app (and keep ngrok running if you use it):
   ```bash
   php artisan serve
   # In another terminal, if using ngrok:
   ngrok http 8000
   ```

2. Open your app in the browser (using the **same** base URL as in `APP_URL` and `BML_WEBHOOK_URL`, so cookies and redirects work):
   - e.g. `https://abc123.ngrok.io` (if using ngrok).

3. Go to a **course that has a fee** and start checkout:
   - URL will look like: `https://YOUR-URL/checkout/course/{course-slug}`

4. Accept the terms checkbox and click **Pay**.
   - You should be redirected to BML’s **UAT payment page**.

5. On BML’s test page, use their **test card / test wallet** (see BML UAT docs) and complete the payment.

6. You should be redirected back to your app to a “Payment processing” page; when the webhook is received (or Get Transaction runs), the page will show success and the enrollment will be confirmed.

### 6. Verify webhook and logs

- **Webhook:** Check `storage/logs/payments-YYYY-MM-DD.log` for lines like `BML createTransaction`, `BML webhook`, `BML getTransactionStatus`. If the webhook is missing, confirm `BML_WEBHOOK_URL` in the BML portal matches your app and that the app is reachable from the internet (e.g. ngrok not stopped).
- **Manual reconciliation:** If a payment stays “pending”, run:
  ```bash
  php artisan payments:reconcile
  ```

### 7. Optional: run reconciliation on a schedule

For testing you can run the scheduler so pending payments are reconciled periodically:

```bash
php artisan schedule:work
```

For production, use cron: `* * * * * php /path/to/artisan schedule:run`.

---

## Sandbox vs production

- **Sandbox**: Use UAT base URL and sandbox credentials. Configure webhook URL in BML UAT portal to `https://your-ngrok-or-domain.com/webhooks/bml`.
- **Production**: Set `BML_BASE_URL` and credentials per BML production docs. Configure the same webhook URL in BML production portal.

## BML portal configuration

1. **Webhook URL**: Set to `https://yourdomain.com/webhooks/bml` (must be HTTPS in production). This is the **primary** way we confirm payments.
2. **Redirect URL**: BML may allow list of redirect domains; ensure your domain is allowed if required.
3. **Signature secret**: If BML provides a webhook signing secret, set it as `BML_WEBHOOK_SECRET`. We verify `HMAC-SHA256(body, secret)` against the header (default `X-BML-Signature`).

## Status mapping (BML v2 `state`)

| BML state (e.g. CONFIRMED, FAILED) | Our `payments.status` |
|------------------------------------|------------------------|
| CONFIRMED, completed, success, approved, paid | `confirmed` |
| FAILED, declined, rejected | `failed` |
| CANCELLED, voided | `cancelled` |
| REFUNDED | `refunded` |
| expired, timeout | `expired` |
| INITIATED, QR_CODE_GENERATED, REFUND_REQUESTED, AUTHORIZED, etc. | `pending` |

## Logging

- All gateway requests/responses and webhook handling are logged to the `payments` channel: `storage/logs/payments-YYYY-MM-DD.log`.
- Logs include correlation id (`local_id`) where applicable.

## Reconciliation

- **Command**: `php artisan payments:reconcile`  
  Fetches status from BML (**Get Transaction** by `bml_transaction_id`) for payments in `pending` older than 5 minutes and not updated in the last 2 minutes. Updates payment and enrollment when state is CONFIRMED/FAILED/etc.

- **Scheduler**: Runs every 10 minutes (see `routes/console.php`). Ensure cron runs `schedule:run`.

## Troubleshooting

1. **Webhook not received**: Check BML portal webhook URL and that your server is reachable (no firewall blocking POST to `/webhooks/bml`). Check `storage/logs/payments-*.log` for incoming requests (we log signature failures).
2. **Payment stuck pending**: Run `php artisan payments:reconcile` to pull status from BML. Check logs for webhook payload and status mapping.
3. **Signature verification fails**: Ensure `BML_WEBHOOK_SECRET` matches BML portal and that we use the same body encoding (we use raw request content or `request->all()` JSON). If BML docs specify a different header or algorithm, set `BML_WEBHOOK_SIGNATURE_HEADER` and `BML_WEBHOOK_HMAC_ALGO`.
4. **Amount mismatch**: We send amount in **laari** (integer). MVR 10.00 = 1000 laari. Check `amount_laar` on `Payment` and BML payload.

## Checklist for go-live

- [ ] Set production `BML_BASE_URL` and credentials in `.env`.
- [ ] Configure webhook URL in BML production portal to `https://yourdomain.com/webhooks/bml`.
- [ ] Set `BML_WEBHOOK_SECRET` if BML provides one.
- [ ] Ensure cron runs `php artisan schedule:run` for reconciliation.
- [ ] Confirm policy pages (terms, privacy, refunds) and checkout checkbox are in place.
- [ ] Test end-to-end: checkout → pay on BML (sandbox) → webhook received → enrollment confirmed.

## BML portal configuration (what you must set)

| Item | Where | Value |
|------|--------|--------|
| Webhook URL | BML merchant portal → Webhooks | `https://yourdomain.com/webhooks/bml` (HTTPS required in production) |
| Webhook secret | Same / API settings | Copy to `.env` as `BML_WEBHOOK_SECRET` |
| Redirect URL allowlist | If BML requires allowed redirect domains | Add your domain (e.g. `yourdomain.com`) |
| App ID / API Key | BML portal | Set `BML_APP_ID`, `BML_API_KEY` in `.env` |
