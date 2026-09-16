<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment(['local', 'testing'])) {
    fwrite(STDERR, "Local Stripe forwarding is available only in local/testing.\n");
    exit(1);
}

$apiKey = (string) config('services.stripe.secret');
if (! str_starts_with($apiKey, 'sk_test_') && ! str_starts_with($apiKey, 'rk_test_')) {
    fwrite(STDERR, "Configure a Stripe sandbox secret key in your local .env first.\n");
    exit(1);
}

$binary = getenv('STRIPE_CLI_PATH') ?: storage_path('app/tools/stripe');
if (! is_executable($binary)) {
    fwrite(STDERR, "Install the Stripe CLI at storage/app/tools/stripe or set STRIPE_CLI_PATH.\n");
    exit(1);
}

// Keep the API key out of command arguments and terminal output.
$environment = array_merge(getenv(), ['STRIPE_API_KEY' => $apiKey]);
$command = [$binary, 'listen', '--events', 'checkout.session.completed', '--forward-to', 'http://127.0.0.1:8000/stripe/webhook'];
$process = proc_open([...$command, '--print-secret'], [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, base_path(), $environment);
if (! is_resource($process)) {
    fwrite(STDERR, "Unable to start Stripe CLI.\n");
    exit(1);
}
$output = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$status = proc_close($process);
if ($status !== 0 || ! preg_match('/whsec_[A-Za-z0-9]+/', $output, $matches)) {
    fwrite(STDERR, "Unable to obtain the local webhook signing secret. Check your Stripe connection and CLI permissions.\n");
    exit(1);
}

$envPath = base_path('.env');
$contents = file_get_contents($envPath);
$setting = 'STRIPE_WEBHOOK_SECRET='.$matches[0];
$contents = preg_match('/^STRIPE_WEBHOOK_SECRET=.*$/m', $contents)
    ? preg_replace_callback('/^STRIPE_WEBHOOK_SECRET=.*$/m', fn () => $setting, $contents)
    : rtrim($contents)."\n".$setting."\n";
if (file_put_contents($envPath, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to update the local .env webhook secret.\n");
    exit(1);
}
$app->make(Kernel::class)->call('config:clear');
echo "Local webhook secret configured; forwarding checkout events to port 8000.\n";

$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, base_path(), $environment);
if (! is_resource($process)) {
    exit(1);
}
while (($line = fgets($pipes[1])) !== false) {
    echo preg_replace('/whsec_[A-Za-z0-9]+/', '[local signing secret configured]', $line);
}
fclose($pipes[1]);
exit(proc_close($process));
