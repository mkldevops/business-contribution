<?php

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Context;

use function app\configEnv;
use function Castor\import;
use function Castor\io;
use function Castor\load_dot_env;
use function Castor\log;
use function docker\isContainerRunning;
use function git\commit;
use function symfony\console;
use function docker\exec as dockerExec;
use function docker\up as dockerUp;
use function app\migrationProcess;

import(__DIR__);

#[AsContext()]
function myContext(): Context
{
    log('Loading context');

    return new Context(load_dot_env());
}

#[AsTask(description: 'Install project')]
function install(?string $token = null): void
{
    io()->title('Installing project');

    configEnv($token);
    sync(dropDatabase: false, fixture: true);
    dockerUp(build: true);

    io()->success('Project installed');
}

#[AsTask(description: 'Install project')]
function gitCommit(?string $message = null, bool $noRebase = false): void
{
    commit($message, $noRebase);
}

#[AsTask(description: 'Install project')]
function sync(bool $dropDatabase = true, bool $fixture = false): void
{
    io()->title('Syncing project');
    if (!isContainerRunning()) {
        dockerUp(restart: true);
    }

    $progress = io()->createProgressBar(5);
    dockerExec('composer install', silent: true);
    $progress->advance();
    dockerExec('npm install', silent: true);
    $progress->advance();
    dockerExec('npm run dev', silent: true);
    $progress->advance();

    if ($dropDatabase) {
        console('doctrine:database:drop --force --if-exists', silent: true);
    }

    console('doctrine:database:create --if-not-exists', silent: true);

    $progress->advance();
    console('doctrine:migrations:migrate --no-interaction', silent: true);
    $progress->advance();

    if ($fixture) {
        migrationProcess(silent: true);
    }

    $progress->finish();
}

#[AsTask(description: 'Reset BDD with fixtures')]
function bddFullReset(): void
{
    if (!isContainerRunning()) {
        dockerUp(restart: true);
    }

    console('d:d:d --force --if-exists');
    console('d:d:c --if-not-exists');
    console('d:m:m -n');
    console('d:f:l -n');
    console('c:c');
}
