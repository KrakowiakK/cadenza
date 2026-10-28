<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Media;

/**
 * When and where a photo was taken, and how it is rotated, from its EXIF data.
 */
final class Exif
{
    /**
     * @return array{takenAt: ?\DateTimeImmutable, latitude: ?float, longitude: ?float, orientation: int}|null
     */
    public static function read(string $jpeg): ?array
    {
        if (!\function_exists('exif_read_data')) {
            return null;
        }
        $data = @exif_read_data('data://image/jpeg;base64,'.base64_encode($jpeg));
        if (false === $data) {
            return null;
        }

        $takenAt = null;
        foreach (['DateTimeOriginal', 'DateTimeDigitized', 'DateTime'] as $field) {
            if (isset($data[$field]) && false !== $date = \DateTimeImmutable::createFromFormat('Y:m:d H:i:s', (string) $data[$field])) {
                $takenAt = $date;
                break;
            }
        }

        return [
            'takenAt' => $takenAt,
            'latitude' => self::coordinate($data['GPSLatitude'] ?? null, $data['GPSLatitudeRef'] ?? 'N'),
            'longitude' => self::coordinate($data['GPSLongitude'] ?? null, $data['GPSLongitudeRef'] ?? 'E'),
            'orientation' => (int) ($data['Orientation'] ?? 1),
        ];
    }

    private static function coordinate(mixed $parts, string $ref): ?float
    {
        if (!\is_array($parts) || 3 !== \count($parts)) {
            return null;
        }
        $values = array_map(static function (mixed $part): float {
            $pieces = explode('/', (string) $part);

            return isset($pieces[1]) && 0.0 !== (float) $pieces[1] ? (float) $pieces[0] / (float) $pieces[1] : (float) $pieces[0];
        }, array_values($parts));
        $value = $values[0] + $values[1] / 60 + $values[2] / 3600;

        return round(\in_array($ref, ['S', 'W'], true) ? -$value : $value, 6);
    }
}
