<?php
declare(strict_types=1);
namespace TutorLMS\ContentDrip;
defined('ABSPATH') || exit;

final class Policy {
    public const MODES = ['open','manual','scheduled','enrollment'];
    public static function released(array $rule, int $now, ?int $enrolledAt): bool {
        return match ($rule['mode'] ?? 'manual') {
            'open' => true,
            'manual' => !empty($rule['manual_open']),
            'scheduled' => (int)($rule['release_at'] ?? 0) > 0 && $now >= (int)$rule['release_at'],
            'enrollment' => $enrolledAt !== null && $enrolledAt > 0 && $now >= $enrolledAt + max(0,(int)($rule['days'] ?? 0))*86400,
            default => false,
        };
    }
    /** Reserve planned activities, including at least one for each missing topic. */
    public static function progress(int $actual, int $completed, int $actualTopics, int $expectedTopics, int $expectedActivities, bool $locked): array {
        $total = max($actual+max(0,$expectedTopics-$actualTopics),$expectedActivities,$actual);
        $completed = max(0,min($completed,$actual,$total));
        // In particular, a locked empty topic cannot allow auto-completion.
        if ($locked && $total>0 && $completed >= $total) $total++;
        return ['completed_percent'=>$total>0?(int)floor(100*$completed/$total):0,
            'completed_count'=>$completed,'total_count'=>$total];
    }
}
