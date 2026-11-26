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
    /**
     * A target is reserved per topic, never pooled across topics.
     * Missing/deleted topics retain their own reservation until explicitly linked or removed.
     * Unplanned published topics are included automatically.
     */
    public static function summarizePlan(array $plan, array $actualCounts): array {
        $byId=[];$pending=[];
        foreach ($plan as $row) {
            $id=(int)($row['topic_id']??0);
            $target=max(1,(int)($row['expected_activities']??1));
            $entry=['topic_id'=>$id,'title'=>(string)($row['title']??''),'expected_activities'=>$target];
            if ($id && array_key_exists($id,$actualCounts)) $byId[$id]=$entry;
            else $pending[]=$entry;
        }
        $rows=[];$actual=0;$total=0;
        foreach ($actualCounts as $id=>$count) {
            $count=max(0,(int)$count);
            $row=$byId[$id]??['topic_id'=>(int)$id,'title'=>'','expected_activities'=>max(1,$count)];
            $row['expected_activities']=max($count,$row['expected_activities'],1);
            $row['actual_activities']=$count;
            $row['published']=true;
            $rows[]=$row;$actual+=$count;$total+=$row['expected_activities'];
        }
        foreach ($pending as $row) {
            $row['actual_activities']=0;$row['published']=false;
            $rows[]=$row;$total+=$row['expected_activities'];
        }
        return ['rows'=>$rows,'expected_topics'=>count($rows),'expected_activities'=>$total,
            'actual_activities'=>$actual,'missing_activities'=>max(0,$total-$actual),'missing_topics'=>count($pending)];
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
