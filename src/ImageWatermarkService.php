<?php

declare(strict_types=1);

namespace Bot;

use RuntimeException;

class ImageWatermarkService
{
    private const string WATERMARK_TEXT = '@mulletor_bot';
    private const int PADDING = 15;
    private const float FONT_SCALE = 1.5;
    private const int SAMPLE_AREA_SIZE = 50;

    /**
     * Download image from URL and add watermark
     *
     * @param string $imageUrl The URL of the image to watermark
     *
     * @return string The path to the watermarked image file
     * @throws RuntimeException
     */
    public function addWatermark(string $imageUrl): string
    {
        // Download the image
        $imageData = file_get_contents($imageUrl);
        if ($imageData === false) {
            throw new RuntimeException("Failed to download image from URL: {$imageUrl}");
        }

        // Create image resource from the downloaded data
        $image = imagecreatefromstring($imageData);
        if ($image === false) {
            throw new RuntimeException("Failed to create image from data");
        }

        // Get image dimensions
        $width = imagesx($image);
        $height = imagesy($image);

        // Determine text color based on background brightness in bottom-right corner
        $textColor = $this->getTextColor($image, $width, $height);

        // Use built-in GD font scaled up
        // GD font 5 is the largest built-in font (9x15 pixels per character)
        $baseFontSize = 5;
        $baseCharWidth = imagefontwidth($baseFontSize);
        $baseCharHeight = imagefontheight($baseFontSize);

        // Calculate scaled dimensions
        $scaledCharWidth = (int)($baseCharWidth * self::FONT_SCALE);
        $scaledCharHeight = (int)($baseCharHeight * self::FONT_SCALE);
        $scaledTextWidth = $scaledCharWidth * strlen(self::WATERMARK_TEXT);

        // Calculate position (bottom right with padding)
        $x = $width - $scaledTextWidth - self::PADDING;
        $y = $height - $scaledCharHeight - self::PADDING;

        // Create a temporary image for the text with scaling
        $textWidth = $baseCharWidth * strlen(self::WATERMARK_TEXT);
        $textHeight = $baseCharHeight;
        $tempImage = imagecreatetruecolor($textWidth, $textHeight);

        // Make background transparent
        $transparent = imagecolorallocatealpha($tempImage, 0, 0, 0, 127);
        imagefill($tempImage, 0, 0, $transparent);
        imagesavealpha($tempImage, true);

        // Allocate text color on temp image
        $r = ($textColor >> 16) & 0xFF;
        $g = ($textColor >> 8) & 0xFF;
        $b = $textColor & 0xFF;
        $tempTextColor = imagecolorallocate($tempImage, $r, $g, $b);

        // Draw text on temp image
        imagestring($tempImage, $baseFontSize, 0, 0, self::WATERMARK_TEXT, $tempTextColor);

        // Copy and resize to main image with scaling
        imagecopyresampled(
            $image,                    // destination
            $tempImage,                // source
            $x,                        // dest x
            $y,                        // dest y
            0,                         // src x
            0,                         // src y
            $scaledTextWidth,          // dest width
            $scaledCharHeight,         // dest height
            $textWidth,                // src width
            $textHeight                // src height
        );

        imagedestroy($tempImage);

        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'watermarked_') . '.png';
        if (!imagepng($image, $tempFile)) {
            imagedestroy($image);
            throw new RuntimeException("Failed to save watermarked image");
        }

        // Clean up
        imagedestroy($image);

        return $tempFile;
    }

    /**
     * Determine text color based on background brightness
     *
     * @param \GdImage $image  The image resource
     * @param int      $width  Image width
     * @param int      $height Image height
     *
     * @return int The color identifier for text (white or black)
     */
    private function getTextColor(\GdImage $image, int $width, int $height): int
    {
        // Sample area in bottom-right corner
        $sampleSize = min(self::SAMPLE_AREA_SIZE, $width, $height);
        $startX = $width - $sampleSize;
        $startY = $height - $sampleSize;

        $totalBrightness = 0;
        $pixelCount = 0;

        // Sample pixels in the bottom-right area
        for ($x = $startX; $x < $width; $x += 5) {
            for ($y = $startY; $y < $height; $y += 5) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // Calculate perceived brightness (using standard formula)
                $brightness = (0.299 * $r + 0.587 * $g + 0.114 * $b);
                $totalBrightness += $brightness;
                $pixelCount++;
            }
        }

        $avgBrightness = $pixelCount > 0 ? $totalBrightness / $pixelCount : 128;

        // If background is dark (brightness < 128), use white text; otherwise use black
        if ($avgBrightness < 128) {
            return imagecolorallocate($image, 255, 255, 255); // White
        } else {
            return imagecolorallocate($image, 0, 0, 0); // Black
        }
    }
}
