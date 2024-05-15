<?php

namespace quality;

use function Castor\log;
use Castor\Attribute\AsTask;
use Symfony\Component\Process\Process;
use function Castor\io;
use function docker\exec as dockerExec;
use function symfony\console;

#[AsTask(description: 'Execute phpstan')]
function phpstan(bool $silent = false): Process
{
    log('Executing phpstan');
    return dockerExec(command:  './vendor/bin/phpstan analyse --no-progress', silent: $silent);
}

#[AsTask(description: 'Execute cs-fixer')]
function csFix(bool $dryRun = false, bool $silent = false): Process
{
    log('Executing cs-fixer');
    return dockerExec(
        command: './vendor/bin/php-cs-fixer fix --allow-risky=yes '.($dryRun ? ' --dry-run' : ''),
        silent: $silent
    );
}

#[AsTask(description: 'Execute rector')]
function rector(bool $dryRun = false, bool $silent = false): Process
{
    log('Executing rector');
    return dockerExec(
        command:  './vendor/bin/rector '.($dryRun ? ' --dry-run' : ''),
        silent: $silent
    );
}

#[AsTask(description: 'Execute commands to fix code')]
function fixCode(bool $dryRun = false): void
{
    if($dryRun) {
        csFix(dryRun: true);
        rector(dryRun: true);
        return;
    }

    $retry = 0;
    while (true) {
        io()->info('Fixing code with cs-fixer and rector');
        ++$retry;
        $rector = rector(silent: true);
        $csFix = csFix(silent: true);

        if ($csFix->isSuccessful() && $rector->isSuccessful()) {
            break;
        }

        if($retry > 3) {
            rector(true);
            csFix(true);
            break;
        }
    }
}

#[AsTask(description: 'Execute quality analysis')]
function analyze(bool $dryRun = false): void
{
    io()->section('Analyzing code');
    lint(silent: true);
    $stan = phpstan();
    if (!$stan->isSuccessful()) {
        io()->error('Phpstan failed');
        $stan->getErrorOutput();
        return;
    }

    fixCode(dryRun: $dryRun);
}

#[AsTask(description: 'Execute lint symfony commands')]
function lint(bool $silent = false): void
{
    console(cmd: 'lint:container', silent: $silent);
    console(cmd: 'lint:yaml --parse-tags config/', silent: $silent);
    console(cmd: 'lint:twig templates/', silent: $silent);
    console(cmd: 'doctrine:schema:validate --skip-sync', silent: $silent);
}
