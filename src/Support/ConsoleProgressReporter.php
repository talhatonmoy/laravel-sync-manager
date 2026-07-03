<?php

namespace DeployCar\LaravelSyncManager\Support;

use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

class ConsoleProgressReporter
{
    protected ProgressBar $bar;

    public function __construct(
        protected Command $command,
        protected string $label
    ) {
        $this->bar = new ProgressBar($this->command->getOutput(), 100);
        $this->bar->setFormat('%message% [%bar%] %percent:3s%%');
        $this->bar->setMessage($this->label.' queued');
        $this->bar->start();
    }

    public function callback(): callable
    {
        return function (int $percent, string $stage, string $message): void {
            $this->bar->setMessage($this->label.' · '.$message);
            $this->bar->setProgress(max(0, min(100, $percent)));
        };
    }

    public function finish(): void
    {
        $this->bar->setProgress(100);
        $this->bar->finish();
        $this->command->newLine(2);
    }
}
