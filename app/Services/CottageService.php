<?php

namespace App\Services;

use App\Models\Cottage;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CottageService
{
    private const DISK = 'public';

    private const DIRECTORY = 'cottages';

    /**
     * @param  array<string, mixed>  $data  validated CottageRequest data
     */
    public function create(array $data, ?UploadedFile $image = null): Cottage
    {
        return DB::transaction(function () use ($data, $image) {
            $cottage = new Cottage($this->attributes($data));

            if ($image) {
                $cottage->image_path = $this->storeImage($image);
            }

            $cottage->save();

            return $cottage;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Cottage $cottage, array $data, ?UploadedFile $image = null, bool $removeImage = false): Cottage
    {
        $oldImage = $cottage->image_path;

        $cottage->fill($this->attributes($data));

        if ($image) {
            $cottage->image_path = $this->storeImage($image);
        } elseif ($removeImage) {
            $cottage->image_path = null;
        }

        $cottage->save();

        // Only delete the old file once the new record is safely saved.
        if ($oldImage && $oldImage !== $cottage->image_path) {
            Storage::disk(self::DISK)->delete($oldImage);
        }

        return $cottage;
    }

    public function setListed(Cottage $cottage, bool $listed): Cottage
    {
        $cottage->is_active = $listed;
        $cottage->save();

        return $cottage;
    }

    /**
     * Permanently remove a unit. Units with bookings must be delisted instead;
     * the bookings foreign key (step 4) restricts deletion at the database level.
     */
    public function delete(Cottage $cottage): void
    {
        $image = $cottage->image_path;

        $cottage->delete();

        if ($image) {
            Storage::disk(self::DISK)->delete($image);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => trim($data['name']),
            'type' => $data['type'],
            'capacity' => (int) $data['capacity'],
            'price_per_night' => Money::normalize($data['price_per_night']),
            'description' => isset($data['description']) ? trim($data['description']) : null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function storeImage(UploadedFile $image): string
    {
        return $image->store(self::DIRECTORY, self::DISK);
    }
}
