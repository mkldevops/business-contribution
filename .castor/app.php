<?php

namespace app;

use Symfony\Component\Process\Process;
use Castor\Attribute\AsTask;
use function Castor\io;
use function Castor\log;
use function Castor\load_dot_env;
use function Castor\parallel;
use function symfony\console;

#[AsTask(description: 'Configure environment variables')]
function configEnv(?string $token): void
{
    $env = load_dot_env();

    touch('.env.local');
    file_put_contents('.env.local', "APP_ENV=dev\n");
    file_put_contents('.env.local', sprintf("APP_SECRET=%s\n", md5(time())), \FILE_APPEND);

    if (null === $token) {
        $token = io()->ask('Do you have a OTAREE_API_TOKEN?', 'no');
    } elseif (isset($env['OTAREE_API_TOKEN'])) {
        $token = $env['OTAREE_API_TOKEN'];
    }

    if ('no' !== $token) {
        file_put_contents('.env.local', sprintf("OTAREE_API_TOKEN'=%s\n", $token), \FILE_APPEND);
    }
}

#[AsTask(description: 'Install project')]
function migrationProcess(bool $silent = false): void
{
    log('Migrating project');
    console('doctrine:fixtures:load --no-interaction --group=main', silent: $silent);

    [, , $product] = parallel(
        static fn (): Process => console('app:users:import', silent: $silent),
        static fn (): Process => console('app:users:documents:fetch', silent: $silent),
        static fn (): Process => console('app:products:fetch', silent: $silent),
    );

    if (!$product->isSuccessful()) {
        io()->error('Error while fetching products');

        return;
    }

    console('app:products:documents:fetch', silent: $silent);
    console('doctrine:fixtures:load --no-interaction --group=campaign --append', silent: $silent);
    console('app:leads:fetch', silent: $silent);
    console('app:leads:assign', silent: $silent);
}
