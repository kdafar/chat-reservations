<?php

namespace App\Wa\Services\Media;

use Illuminate\Support\Facades\Log;

class VideoCompressionService
{
    /**
     * Compress a video using FFMpeg to meet file size limits.
     *
     * @param  string  $inputPath  Absolute path to source file
     * @param  string|null  $outputPath  Optional destination path
     * @param  int  $sizeLimitMB  Target size limit in MB (default 15MB)
     * @return string Path to the compressed file
     */
    public function compress(string $inputPath, ?string $outputPath = null, int $sizeLimitMB = 15): string
    {
        Log::info('[MediaCompressor] Compress Video called', [
            'input' => $inputPath,
            'limit_mb' => $sizeLimitMB,
        ]);

        $ffmpegPath = $this->detectFFmpeg();

        if (! $outputPath) {
            $extension = pathinfo($inputPath, PATHINFO_EXTENSION) ?: 'mp4';
            $outputPath = sys_get_temp_dir().'/video_compressed_'.uniqid().'.'.$extension;
        }

        // FIX: Force dimensions to be divisible by 2
        $scaleFilter = "scale=trunc(iw*min(1\,min(1280/iw\,720/ih))/2)*2:-2";

        // FIX: Added '-r 30' to force 30 FPS. 60 FPS causes compatibility issues with WA Baseline profile.
        // Using Baseline 3.1 ensures broad compatibility with 720p mobile playback.
        $cmd = sprintf(
            '%s -y -i %s -vf "%s" -c:v libx264 -profile:v baseline -level 3.1 -pix_fmt yuv420p -movflags +faststart -r 30 -crf 28 -preset fast -c:a aac -ar 44100 -ac 2 -b:a 96k -fs %dM %s 2>&1',
            escapeshellarg($ffmpegPath),
            escapeshellarg($inputPath),
            $scaleFilter,
            $sizeLimitMB,
            escapeshellarg($outputPath)
        );

        $this->runCommand($cmd, $outputPath);

        return $outputPath;
    }

    /**
     * Compress/Resize an image using FFMpeg to meet WhatsApp 5MB limit.
     * Converts to JPEG, max 1920px width/height.
     *
     * @param  int  $quality  JPEG Quality (2-31, where 2 is best, 31 is worst). 10 is standard web.
     */
    public function compressImage(string $inputPath, ?string $outputPath = null, int $quality = 10): string
    {
        Log::info('[MediaCompressor] Compress Image called', [
            'input' => $inputPath,
            'quality' => $quality,
        ]);

        $ffmpegPath = $this->detectFFmpeg();

        if (! $outputPath) {
            $outputPath = sys_get_temp_dir().'/img_compressed_'.uniqid().'.jpg';
        }

        // Image Command:
        // -vf scale: Ensure max dimension is 1920px, keep aspect ratio, do NOT upscale (min(1...))
        // -q:v : Quality for MJPEG/JPEG.
        $cmd = sprintf(
            '%s -y -i %s -vf "scale=iw*min(1\,min(1920/iw\,1920/ih)):-1" -q:v %d %s 2>&1',
            escapeshellarg($ffmpegPath),
            escapeshellarg($inputPath),
            $quality,
            escapeshellarg($outputPath)
        );

        $this->runCommand($cmd, $outputPath);

        return $outputPath;
    }

    private function runCommand(string $cmd, string $outputPath): void
    {
        Log::info("[MediaCompressor] Running command: $cmd");

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::error('[MediaCompressor] FFMpeg failed', ['output' => $output]);
            throw new \RuntimeException('FFmpeg compression failed.');
        }

        if (! file_exists($outputPath) || filesize($outputPath) === 0) {
            Log::error("[MediaCompressor] Output file empty or missing: $outputPath");
            throw new \RuntimeException('FFmpeg created empty file.');
        }

        Log::info("[MediaCompressor] Success. Output: $outputPath (".filesize($outputPath).' bytes)');

        // Log metadata for debugging
        $info = $this->getMediaInfo($outputPath);
        Log::info('[MediaCompressor] File Metadata:', $info);
    }

    /**
     * Run a quick probe on the output file to get its streams/profile.
     * Useful for Admin views or debugging.
     */
    public function getMediaInfo(string $path): array
    {
        try {
            $ffmpeg = $this->detectFFmpeg();
            // -i command prints metadata to stderr
            $cmd = sprintf('%s -hide_banner -i %s 2>&1', escapeshellarg($ffmpeg), escapeshellarg($path));

            exec($cmd, $output);

            // Filter to just the relevant Stream/Input lines to keep logs readable
            return array_values(array_filter($output, function ($line) {
                return str_contains($line, 'Stream #') ||
                       str_contains($line, 'Input #') ||
                       str_contains($line, 'Duration:') ||
                       str_contains($line, 'Profile');
            }));

        } catch (\Throwable $e) {
            Log::warning('[MediaCompressor] Failed to get metadata: '.$e->getMessage());

            return ['Error: '.$e->getMessage()];
        }
    }

    private function detectFFmpeg(): string
    {
        $paths = ['/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/snap/bin/ffmpeg'];

        foreach ($paths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        $check = shell_exec('which ffmpeg');
        $check = trim($check ?? '');

        if (! empty($check)) {
            return $check;
        }

        throw new \RuntimeException('FFmpeg executable not found on server.');
    }
}
