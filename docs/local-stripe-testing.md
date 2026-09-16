# Local Stripe deposit testing

Use a Stripe sandbox dedicated to local development. A webhook pointing at the deployed website updates that website's database, not your local database.

Install the official Linux Stripe CLI binary at `storage/app/tools/stripe` (ignored by Git), or set `STRIPE_CLI_PATH` to your installed binary. Other operating systems need their matching binary.

Set `APP_ENV=local` and sandbox `STRIPE_KEY` and `STRIPE_SECRET` in your local `.env`. Do not commit credentials.

Start the application with `composer run dev`. In a second terminal, run:

```bash
composer run dev:stripe
```

The helper obtains the CLI listener signing secret, updates only the local `STRIPE_WEBHOOK_SECRET`, clears configuration cache, and forwards `checkout.session.completed` to `http://127.0.0.1:8000/stripe/webhook`. It requires a test key and refuses production environments. It does not modify the deployed application's configuration or registered Stripe webhook destinations.

Keep both processes running. Complete a new checkout through the local wallet page and look for a `200` webhook response in the listener. Refresh the wallet to verify the balance and deposit history. Checkout success alone does not confirm wallet credit. This listener handles future events; previously paid sessions need separate verified reconciliation.

If forwarding returns `400`, check signing-secret configuration. For `422`, inspect wallet/session metadata and payment amounts. For `500`, inspect application logs or error incidents.

Reference: [Stripe local webhook testing](https://docs.stripe.com/webhooks#test-locally-without-a-registered-url).
