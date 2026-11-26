<?php
use TutorLMS\ContentDrip\Admin;
use TutorLMS\ContentDrip\Plugin;
function tlcd_check($ok,string $name):void {
    if (!$ok) throw new RuntimeException($name);
    echo "PASS: $name\n";
}
$admin=get_user_by('login','admin');
wp_set_current_user($admin->ID);
$student=wp_insert_user(['user_login'=>'tlcd_learner','user_pass'=>wp_generate_password(),'role'=>'subscriber']);
tlcd_check(is_int($student),'student created');
$course=wp_insert_post(['post_type'=>'courses','post_title'=>'Drip fixture','post_status'=>'publish','post_author'=>$admin->ID],true);
$t1=wp_insert_post(['post_type'=>'topics','post_title'=>'Week 1','post_parent'=>$course,'post_status'=>'publish'],true);
$t2=wp_insert_post(['post_type'=>'topics','post_title'=>'Week 2','post_parent'=>$course,'post_status'=>'publish'],true);
$l1=wp_insert_post(['post_type'=>'lesson','post_title'=>'One','post_parent'=>$t1,'post_status'=>'publish'],true);
$l2=wp_insert_post(['post_type'=>'lesson','post_title'=>'Two','post_parent'=>$t2,'post_status'=>'publish'],true);
foreach ([$course,$t1,$t2,$l1,$l2] as $id) tlcd_check(is_int($id)&&$id>0,'fixture created');
$policy=Admin::update($course,['enabled'=>true,'expected_topics'=>4,'expected_activities'=>4,'rules'=>[$t1=>['mode'=>'open'],$t2=>['mode'=>'manual']]]);
tlcd_check(!is_wp_error($policy),'policy saved');
wp_set_current_user($student);
tlcd_check(!Plugin::locked($l1,$student),'first lesson open');
tlcd_check(Plugin::locked($l2,$student),'second lesson locked');
tlcd_check(!apply_filters('tutor_validate_lesson_complete',true,$student,$l2),'lesson complete blocked');
tlcd_check(!apply_filters('tutor_course/single/content/show_permalink',true,$l2),'sidebar locked');
tlcd_check(is_wp_error(apply_filters('tutor_user_can_complete_course',true,$student,$course)),'course completion blocked');
update_user_meta($student,'_tutor_completed_lesson_id_'.$l1,time());
$s=Plugin::stats($course,$student);
tlcd_check($s['completed_percent']===25&&$s['total_count']===4,'planned denominator');
tlcd_check(!Plugin::eligible($course,$student),'missing topics block eligibility');
tlcd_check(is_wp_error(Admin::update($course,['enabled'=>false])),'student cannot edit policy');
wp_set_current_user($admin->ID);
tlcd_check(!Plugin::locked($l2,$admin->ID),'editor preview');
tlcd_check(Plugin::locked($l2,$admin->ID,false),'eligibility ignores editor bypass');

// The management screen must render persisted rules and not mutate the policy.
$_GET['course_id']=$course;
$before=Plugin::config($course);
ob_start();Admin::page();$markup=ob_get_clean();
tlcd_check(str_contains($markup,'id="tlcd-policy"'),'admin policy form rendered');
tlcd_check(str_contains($markup,'id="tlcd-planned-topics"')&&str_contains($markup,'id="tlcd-add-plan"'),'per-topic plan and future control rendered');
tlcd_check(!str_contains($markup,'id="tlcd-bulk"')&&!str_contains($markup,'id="tlcd-apply-schedule"'),'weekly schedule control removed');
tlcd_check(str_contains($markup,'id="tlcd-mode-'.$t1.'"')&&str_contains($markup,'id="tlcd-date-'.$t1.'"')&&str_contains($markup,'id="tlcd-mode-'.$t2.'"')&&str_contains($markup,'id="tlcd-date-'.$t2.'"'),'individual topic scheduling retained');
tlcd_check(str_contains($markup,'data-topic="'.$t1.'"')&&str_contains($markup,'data-topic="'.$t2.'"'),'topic rows rendered');
tlcd_check(Plugin::config($course)===$before,'rendering does not mutate saved policy');
tlcd_check(str_contains($markup,'data-state="open"')&&str_contains($markup,'data-state="locked"'),'persisted release states rendered');

// Legacy totals are retained until explicitly reallocated.
$badPlan=[['topic_id'=>$t1,'title'=>'Week 1','expected_activities'=>1],['topic_id'=>$t2,'title'=>'Week 2','expected_activities'=>1]];
tlcd_check(is_wp_error(Admin::update($course,['enabled'=>true,'plan'=>$badPlan,'rules'=>[]])),'legacy targets cannot silently shrink');
$plan=[['topic_id'=>$t1,'title'=>'Week 1','expected_activities'=>1],['topic_id'=>$t2,'title'=>'Week 2','expected_activities'=>3],
    ['topic_id'=>0,'title'=>'Week 3','expected_activities'=>2],['topic_id'=>0,'title'=>'Week 4','expected_activities'=>4]];
$rules=[$t1=>['mode'=>'open'],$t2=>['mode'=>'manual']];
$policy=Admin::update($course,['enabled'=>true,'plan'=>$plan,'rules'=>$rules]);
tlcd_check(!is_wp_error($policy)&&$policy['expected_activities']===10&&$policy['expected_topics']===4,'per-topic targets derive total of ten');
tlcd_check(Plugin::stats($course,$student)['completed_percent']===10,'per-topic progress does not assume equal weights');
tlcd_check(Plugin::planSummary($course)['missing_activities']===8,'per-topic plan preserves all missing activities');
$dup=$plan;$dup[]=$plan[0];
tlcd_check(is_wp_error(Admin::update($course,['enabled'=>true,'plan'=>$dup,'rules'=>$rules])),'duplicate topic rejected');
$wrong=$plan;$wrong[0]['topic_id']=999999999;
tlcd_check(is_wp_error(Admin::update($course,['enabled'=>true,'plan'=>$wrong,'rules'=>$rules])),'unknown topic rejected');
$policy=Admin::update($course,['enabled'=>true,'expected_topics'=>4,'expected_activities'=>10,'rules'=>$rules]);
tlcd_check(!is_wp_error($policy)&&count($policy['plan'])===4,'legacy API update preserves explicit plan');
tlcd_check(is_wp_error(Admin::update($course,['enabled'=>true,'expected_topics'=>2,'expected_activities'=>2,'rules'=>$rules])),'legacy API cannot replace explicit targets');
ob_start();Admin::page();$markup=ob_get_clean();
tlcd_check(str_contains($markup,'id="tlcd-plan-template"')&&str_contains($markup,'Week 3')&&str_contains($markup,'Week 4'),'future topic targets rendered');
tlcd_check(str_contains($markup,'id="tlcd-planned-activities">10</strong>'),'calculated total rendered');
