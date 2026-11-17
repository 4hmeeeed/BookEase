<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\SystemTestEmail;
use App\Services\SystemCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * GET /admin/system and its maintenance buttons (owner only; the buttons
 * also ask for the password again). Stands in for SSH on InfinityFree.
 */
class SystemController extends Controller
{
    public function index(Request $request, SystemCheck $system): View
    {
        return view('admin.system', ['checks' => $system->report($request)]);
    }

    public function testEmail(Request $request): RedirectResponse
    {
        $owner = $request->user();

        try {
            $owner->notify(new SystemTestEmail);
        } catch (Throwable $e) {
            Log::warning('System page test email failed', ['error' => $e->getMessage()]);

            return back()->with('error', 'The test email could not be sent: '.Str::limit($e->getMessage(), 300));
        }

        return back()->with('status', "Test email sent to {$owner->email}. If it hasn’t arrived in a few minutes, check the spam folder and the MAIL_ settings in .env.");
    }

    public function migrate(): RedirectResponse
    {
        return $this->runCommand('migrate', ['--force' => true], 'Database updates');
    }

    public function clearCaches(): RedirectResponse
    {
        return $this->runCommand('optimize:clear', [], 'Clear caches');
    }

    public function cleanup(): RedirectResponse
    {
        return $this->runCommand('notifications:prune-read', [], 'Clean-up');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function runCommand(string $command, array $parameters, string $label): RedirectResponse
    {
        try {
            $exitCode = Artisan::call($command, $parameters);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', "{$label} failed: ".Str::limit($e->getMessage(), 300));
        }

        $output = trim((string) preg_replace('/\s+/', ' ', Artisan::output()));

        return back()->with($exitCode === 0 ? 'status' : 'error', "{$label}: ".($output !== '' ? Str::limit($output, 300) : 'done.'));
    }
}
