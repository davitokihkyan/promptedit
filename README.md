# PromptEdit

Generate images with the [Pixelforge](https://pixelforge.promptedit.dev/docs) API. Users see the price before they generate, are charged Pixelforge's exact cost plus 25%, and pay nothing for failed generations.

Built with Laravel, Vue and Inertia.

## Requirements

- PHP 8.4 or newer
- Composer
- Node.js 22 or newer
- MySQL 8

## Install

```bash
git clone https://github.com/davitokihkyan/promptedit.git
cd promptedit
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Open `.env` and fill in:

- `DB_*`: your MySQL connection
- `PIXELFORGE_API_KEY`: an API key from the Pixelforge dashboard
- `PIXELFORGE_WEBHOOK_SECRET`: that key's signing secret (`whsec_…`)

Then create the tables and build the frontend:

```bash
php artisan migrate
npm run build
```

## Run

The app needs three processes:

```bash
php artisan serve          # the website
php artisan queue:work     # sends generations to Pixelforge
php artisan schedule:work  # catches up on missed webhooks
```

In production, serve the `public/` folder with nginx or Apache, and run the queue worker and scheduler as background services.

## Webhooks

In the Pixelforge dashboard, set your API key's webhook URL to:

```
https://your-domain/webhooks/pixelforge
```

The URL must be public. Without webhooks, generations still finish, about two minutes later.

## Admin report

Register an account, then make it an admin to see the spend report:

```bash
php artisan app:make-admin you@example.com
```

## Tests

```bash
php artisan test
```
