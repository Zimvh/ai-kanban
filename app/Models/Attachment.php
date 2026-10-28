<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

/**
 * A photo on a task. The image itself lives base64-encoded in the `data` column.
 */
#[Fillable(['task_id', 'user_id', 'filename', 'mime_type', 'size', 'data'])]
#[Hidden(['data'])]
class Attachment extends Model
{
    /** Longest side, in pixels, that photos are shrunk to before saving. */
    public const MAX_SIDE = 1600;

    /** Columns to load when listing photos, so the image data is only read when a photo is opened. */
    public const LIST_COLUMNS = ['id', 'task_id', 'user_id', 'filename', 'mime_type', 'size', 'created_at'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Shrink an uploaded photo to a JPEG no larger than MAX_SIDE and attach it to the task.
     * If the image can't be decoded, the original file is stored unchanged.
     */
    public static function storePhoto(Task $task, UploadedFile $photo, ?User $user = null): self
    {
        $bytes = static::shrink($photo->getRealPath()) ?? $photo->get();
        $resized = str_starts_with($bytes, "\xFF\xD8");

        return $task->attachments()->create([
            'user_id' => $user?->id,
            'filename' => $resized
                ? pathinfo($photo->getClientOriginalName() ?: 'photo', PATHINFO_FILENAME).'.jpg'
                : ($photo->getClientOriginalName() ?: 'photo'),
            'mime_type' => $resized ? 'image/jpeg' : ($photo->getMimeType() ?? 'application/octet-stream'),
            'size' => strlen($bytes),
            'data' => base64_encode($bytes),
        ]);
    }

    public function contents(): string
    {
        return base64_decode($this->data);
    }

    private static function shrink(string $path): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));
        if ($image === false) {
            return null;
        }

        $image = static::applyCameraRotation($image, $path);

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        if ($scale < 1) {
            $image = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
        }

        ob_start();
        imagejpeg($image, null, 80);

        return ob_get_clean();
    }

    /** iPhone photos are stored sideways with an EXIF "Orientation" flag; turn them upright. */
    private static function applyCameraRotation(\GdImage $image, string $path): \GdImage
    {
        $orientation = function_exists('exif_read_data') ? (@exif_read_data($path)['Orientation'] ?? 1) : 1;

        return match ((int) $orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
