<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use UnitEnum;

class ViewLogs extends Page
{
    protected string $view = 'filament.pages.view-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Logs';

    // How much of the tail of the file to actually read - these files can
    // grow into the tens of MB, and nobody's paging back through months of
    // history here, just checking what just happened.
    private const int MAX_BYTES_TO_READ = 500_000;

    private const int MAX_ENTRIES = 150;

    public string $selectedFile = '';

    public static function canAccess(): bool
    {
        return Auth::guard('admin')->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->selectedFile = $this->availableFiles()->keys()->first() ?? '';
    }

    /**
     * @return \Illuminate\Support\Collection<string, string>
     */
    public function availableFiles()
    {
        if (! File::isDirectory(storage_path('logs'))) {
            return collect();
        }

        return collect(File::files(storage_path('logs')))
            ->filter(fn ($file) => str($file->getFilename())->startsWith('laravel') && $file->getExtension() === 'log')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->mapWithKeys(fn ($file) => [
                $file->getFilename() => $file->getFilename().' ('.number_format($file->getSize() / 1024, 0).' KB)',
            ]);
    }

    /**
     * @return array<int, array{level: string, timestamp: string, summary: string, full: string}>
     */
    public function entries(): array
    {
        if ($this->selectedFile === '') {
            return [];
        }

        $path = storage_path('logs/'.basename($this->selectedFile));

        if (! File::exists($path)) {
            return [];
        }

        $size = filesize($path);
        $seekedIntoMiddle = $size > self::MAX_BYTES_TO_READ;
        $handle = fopen($path, 'r');
        fseek($handle, max(0, $size - self::MAX_BYTES_TO_READ));
        $chunk = fread($handle, self::MAX_BYTES_TO_READ);
        fclose($handle);

        if ($seekedIntoMiddle) {
            // Only drop the first line when we actually seeked into the
            // middle of the file - it's likely a truncated fragment of the
            // previous (unread) entry. On a small file read from byte 0,
            // that first line is a real, complete entry and must stay.
            $chunk = str($chunk)->after("\n")->toString();
        }

        $rawEntries = preg_split('/(?=^\[\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})/m', $chunk, -1, PREG_SPLIT_NO_EMPTY);

        return collect($rawEntries)
            ->reverse()
            ->values()
            ->take(self::MAX_ENTRIES)
            ->map(function (string $entry) {
                $firstLine = str($entry)->before("\n")->trim()->toString();

                preg_match('/^\[(?<timestamp>[^\]]+)]\s+\S+\.(?<level>\w+):/', $firstLine, $matches);

                return [
                    'level' => strtoupper($matches['level'] ?? 'INFO'),
                    'timestamp' => $matches['timestamp'] ?? '',
                    'summary' => str($firstLine)->limit(180)->toString(),
                    'full' => trim($entry),
                ];
            })
            ->all();
    }
}
