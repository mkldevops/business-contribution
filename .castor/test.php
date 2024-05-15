<?php

namespace test;

use Castor\Attribute\AsTask;
use function Castor\io;
use function docker\exec as dockerExec;
use function symfony\console;

#[AsTask(description: 'Execute tests')]
function phpunit(string $filter = null): void
{
    dockerExec(
        command: 'php bin/phpunit '.($filter !== null && $filter !== '' && $filter !== '0' ? '--filter '.$filter : ''),
        env: 'test'
    );
}

#[AsTask(description: 'Execute tests')]
function all(string $filter = null): void
{
    // check if vendor is installed
    if (!file_exists('vendor/autoload.php')) {
        io()->section('Installing composer dependencies');
        dockerExec('composer install', env: 'test', silent: true);
    }

    // check node_modules
    if (file_exists('package.json') && !file_exists('node_modules')) {
        io()->section('Installing npm dependencies');
        dockerExec('npm install', env: 'test', silent: true);
        dockerExec('npm run build', env: 'test', silent: true);
    }

    io()->section('Running tests');
    console('cache:clear', env: 'test', silent: true);
    console('doctrine:schema:drop --force --full-database', env: 'test', silent: true);
    console('doctrine:schema:update --force', env: 'test', silent: true);
    console('doctrine:fixtures:load --no-interaction', env: 'test', silent: true);
    phpunit($filter);
}
