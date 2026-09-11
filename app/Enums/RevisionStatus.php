<?php

namespace App\Enums;

enum RevisionStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => '草稿',
            self::PendingReview => '待審核',
            self::Approved => '已接受',
            self::Rejected => '已退回',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::PendingReview => 'text-bg-warning',
            self::Approved => 'text-bg-success',
            self::Rejected => 'text-bg-danger',
        };
    }
}
