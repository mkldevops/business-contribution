<?php

namespace git;

use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\io;
use function quality\analyze;
use function test\all as testAll;
use function Castor\run;

#[AsTask(description: 'git commit and push')]
function message(): string
{
    $message = capture("git branch --show-current | sed -E 's/^([0-9]+)-([^-]+)-(.+)/\\2: \#\\1 \\3/' | sed 's/-/ /g'");

    io()->info('Message: '.$message);

    if (empty($message)) {
        return io()->ask('Enter commit message');
    }

    return $message;
}

#[AsTask(description: 'git commit and push')]
function commit(?string $message = null, bool $noRebase = false, bool $noCheck = false): void
{
    io()->title('Committing and pushing');

    if (!$noCheck) {
        analyze();
        testAll();
    }

    autoCommit($message);
    if (!$noRebase) {
        rebase();
    }

    push();
}

#[AsTask(description: 'git auto commit')]
function autoCommit(?string $message = null): void
{
    run('git add .');
    $message ??= message();

    run(sprintf('git commit -m "%s"', $message));
}

#[AsTask(description: 'git commit and push')]
function rebase(): void
{
    run('git pull --rebase');
    run('git pull --rebase origin develop');
}

#[AsTask(description: 'git commit and push')]
function clean(bool $dryRun = false): void
{
    run('git fetch --prune');
    $command = "git branch --v --all | grep 'gone]' | awk '{print $1}' | sed 's/\*//g'";
    $branches = capture($command);

    if (empty($branches)) {
        io()->success('No branches to delete');

        return;
    }

    io()->info('Branches to delete: '.$branches);
    if ($dryRun) {
        return;
    }

    run(sprintf('%s |  xargs git branch -D', $command));
}

#[AsTask(description: 'git commit and push')]
function push(): void
{
    $currentBranch = capture('git rev-parse --abbrev-ref HEAD');

    if (empty($currentBranch)) {
        io()->error('Error while getting current branch');

        return;
    }

    run('git push origin '.$currentBranch.' --force-with-lease --force-if-includes');
}
