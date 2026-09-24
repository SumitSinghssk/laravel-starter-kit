<?php

namespace App\Services\Transfer;

class TransferRegistry
{
    private const TYPES = [
        Types\BlogTransfer::class,
        Types\BlogCategoryTransfer::class,
        Types\PageTransfer::class,
        Types\TestimonialTransfer::class,
        Types\RedirectTransfer::class,
        Types\SeoTransfer::class,
        Types\UserTransfer::class,
        Types\EnquiryTransfer::class,
        Types\NotFoundTransfer::class,
    ];

    public function all(): array
    {
        $types = [];

        foreach (self::TYPES as $class) {
            $type = app($class);
            $types[$type->key()] = $type;
        }

        return $types;
    }

    public function find(string $key): TransferType
    {
        return $this->all()[$key] ?? abort(404);
    }
}
