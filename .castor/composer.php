<?php

namespace composer;

use function docker\exec;
use Castor\Attribute\AsTask;

#[AsTask(description: 'Execute composer command')]
function composer(string $cmd): void
{
    exec('symfony composer '.$cmd);
}

#[AsTask(description: 'Execute composer install')]
function install(): void
{
    composer('install --no-interaction --prefer-dist --optimize-autoloader');
}

#[AsTask(description: 'Execute composer update')]
function update(): void
{
    composer('update --with-dependencies');
}

#[AsTask(description: 'Execute composer require')]
function req(string $package): void
{
    composer('require '.$package);
}
