<?php

declare(strict_types=1);

namespace App\State;

use App\ApiResource\LaboratoryResource;
use App\Entity\Laboratory;

final class LaboratoryResourceMapper
{
    public static function map(Laboratory $laboratory): LaboratoryResource
    {
        $resource = new LaboratoryResource();
        $resource->id = $laboratory->getId()->toRfc4122();
        $resource->slug = $laboratory->getSlug().'-'.$laboratory->getUid();
        $resource->name = $laboratory->getName();
        $resource->addressRaw = $laboratory->getAddressRaw();
        $resource->cityName = $laboratory->getCity()->getName();
        $resource->citySlug = $laboratory->getCity()->getSlug();
        $resource->lat = $laboratory->getLat() !== null ? (float) $laboratory->getLat() : null;
        $resource->lng = $laboratory->getLng() !== null ? (float) $laboratory->getLng() : null;
        $resource->phone = $laboratory->getPhone();
        $resource->isVerified = $laboratory->isVerified();
        $resource->labTests = array_map(
            static fn ($t) => [
                'name' => $t->getName(),
                'category' => $t->getCategory(),
                'price' => $t->getPrice() !== null ? (float) $t->getPrice() : null,
            ],
            $laboratory->getLabTests()->toArray(),
        );

        return $resource;
    }
}
