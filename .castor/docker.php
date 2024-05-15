<?php

namespace docker;

use Castor\Attribute\AsTask;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Process\Process;

use function Castor\cache;
use function Castor\capture;
use function Castor\io;
use function Castor\log;
use function Castor\run;

function compose(string $command, array $environment = [], bool $silent = false): Process
{
    log(message: 'Executing docker command : docker compose '.$command);

    return run(
        command: sprintf('docker compose %s', $command),
        environment: $environment,
        quiet: $silent
    );
}

#[AsTask(description: 'Install project')]
function sh(): void
{
    if (!isContainerRunning()) {
        io()->error('Container is not running');

        $restart = io()->ask('Do you want to start the container?', 'yes');
        if ('yes' !== $restart) {
            return;
        }

        up();
    }

    exec('zsh');
}

function isContainerRunning(): bool
{
    return cache('docker-is-running', static function (CacheItemInterface $item) : bool {
        $item->expiresAfter(20);
        return (bool) capture('docker compose ps | grep php && echo 1 || echo 0');
    });
}

#[AsTask(description: 'Execute docker exec command')]
function exec(string $command, string $service = 'php', string $env = 'dev', bool $silent = false): Process
{
    if (!isContainerRunning()) {
        return run($command, environment: ['APP_ENV' => $env]);
    }

    return compose(
        command : sprintf(
            'exec %s %s %s',
            $env !== '' && $env !== '0' ? '-e APP_ENV='.$env : '',
            $service,
            $command
        ),
        silent: $silent
    );
}

#[AsTask(description: 'Install project')]
function up(bool $restart = false, bool $build = false, bool $removeVolumes = false): void
{
    if ($restart) {
        io()->title('Restarting project');
        compose('down --remove-orphans '.($removeVolumes ? '--volumes' : ''));
    }

    io()->title('Starting project');

    $up = compose(
        command: sprintf(
            'up -d --wait %s',
            $build ? '--build' : ''
        ),
        environment: ['SERVER_NAME' => 'app.localhost']
    );

    if (!$up->isSuccessful()) {
        compose('logs -f');
    }
}

#[AsTask(description: 'Execute docker push command')]
function push(string $target, ?string $tag = null): Process
{
    $login = run('docker login --username $DOCKER_USERNAME --password $DOCKERHUB_TOKEN');
    if (!$login->isSuccessful()) {
        io()->error('Login failed');

        return $login;
    }

    // docker build with target
    $build = run('docker build --target '.$target.' -t $DOCKER_IMAGE_NAME:'.$tag.' .');

    if (!$build->isSuccessful()) {
        io()->error('Build failed');

        return $build;
    }

    $result = run('docker push $DOCKER_IMAGE_NAME:'.$tag);

    if ($result->isSuccessful()) {
        io()->success('Push executed successfully');
    }

    return $result;
}
