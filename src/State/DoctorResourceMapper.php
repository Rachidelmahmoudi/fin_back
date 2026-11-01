<?php

declare(strict_types=1);

namespace App\State;

use App\ApiResource\DoctorResource;
use App\Entity\Doctor;

final class DoctorResourceMapper
{
    public static function map(Doctor $doctor): DoctorResource
    {
        $resource = new DoctorResource();
        $resource->id = $doctor->getId()->toRfc4122();
        $resource->slug = $doctor->getSlug().'-'.$doctor->getUid();
        $resource->name = $doctor->getName();
        $resource->addressRaw = $doctor->getAddressRaw();
        $resource->cityName = $doctor->getCity()->getName();
        $resource->citySlug = $doctor->getCity()->getSlug();
        $resource->lat = $doctor->getLat() !== null ? (float) $doctor->getLat() : null;
        $resource->lng = $doctor->getLng() !== null ? (float) $doctor->getLng() : null;
        $resource->phone = $doctor->getPhone();
        $resource->consultationPrice = $doctor->getConsultationPrice() !== null ? (float) $doctor->getConsultationPrice() : null;
        $resource->bio = $doctor->getBio();
        $resource->isVerified = $doctor->isVerified();
        $resource->specialties = array_map(
            static fn ($s) => $s->getName(),
            $doctor->getSpecialties()->toArray(),
        );

        return $resource;
    }
}
