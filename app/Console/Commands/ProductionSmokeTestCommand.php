<?php

namespace App\Console\Commands;

use App\Core\Models\MonitoredSource;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductionSmokeTestCommand extends Command
{
    protected $signature = 'contextual-console:smoke-test';

    protected $description = 'Quickly verify production configuration is sufficient to run Contextual Console.';

    public function handle(): int
    {
        $failed = false;

        $appEnv = (string) config('app.env', '');
        $this->ok(sprintf('APP_ENV=%s', $appEnv !== '' ? $appEnv : '(missing)'));

        $failed = $this->checkRequired($failed, 'APP_URL is set', trim((string) config('app.url', '')) !== '');

        $failed = $this->checkThrowable($failed, 'Database connection', function (): bool {
            DB::connection()->getPdo();

            return true;
        });

        $failed = $this->checkThrowable($failed, 'Migrations table accessible', function (): bool {
            if (! Schema::hasTable('migrations')) {
                return false;
            }

            DB::table('migrations')->limit(1)->get();

            return true;
        });

        $failed = $this->checkRequired($failed, 'Admin user exists', User::query()->exists());
        $failed = $this->checkRequired($failed, 'Monitored source exists', MonitoredSource::query()->exists());
        $failed = $this->checkRequired(
            $failed,
            'Monitored source endpoint_url exists',
            MonitoredSource::query()->whereNotNull('endpoint_url')->where('endpoint_url', '!=', '')->exists()
        );

        $failed = $this->checkRequired(
            $failed,
            'Daily summary recipient is set',
            trim((string) config('contextual_console.daily_summary_to', '')) !== ''
        );

        $failed = $this->checkRequired(
            $failed,
            'Mail from address is set',
            trim((string) config('mail.from.address', '')) !== ''
        );

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function checkRequired(bool $alreadyFailed, string $label, bool $passes): bool
    {
        if ($passes) {
            $this->ok($label);

            return $alreadyFailed;
        }

        $this->failLine($label);

        return true;
    }

    /**
     * @param callable(): bool $fn
     */
    private function checkThrowable(bool $alreadyFailed, string $label, callable $fn): bool
    {
        try {
            $passes = (bool) $fn();
        } catch (Throwable) {
            $passes = false;
        }

        return $this->checkRequired($alreadyFailed, $label, $passes);
    }

    private function ok(string $label): void
    {
        $this->line("[OK] {$label}");
    }

    private function failLine(string $label): void
    {
        $this->line("[FAIL] {$label}");
    }
}
