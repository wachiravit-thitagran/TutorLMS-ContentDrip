<?php
define('ABSPATH',__DIR__);
require __DIR__.'/../src/Policy.php';
use TutorLMS\ContentDrip\Policy;
function check($ok,string $name):void {
    if (!$ok) throw new RuntimeException($name);
    echo "PASS: $name\n";
}
check(Policy::released(['mode'=>'open'],100,null),'open');
check(!Policy::released([],100,null),'unconfigured locked');
check(Policy::released(['mode'=>'manual','manual_open'=>true],100,null),'manual open');
check(!Policy::released(['mode'=>'scheduled','release_at'=>200],199,null),'before schedule');
check(Policy::released(['mode'=>'scheduled','release_at'=>200],200,null),'at schedule');
check(!Policy::released(['mode'=>'scheduled'],100,null),'missing date');
check(!Policy::released(['mode'=>'enrollment','days'=>7],999999,null),'missing enrollment');
check(!Policy::released(['mode'=>'enrollment','days'=>7],100+7*86400-1,100),'before enrollment delay');
check(Policy::released(['mode'=>'enrollment','days'=>7],100+7*86400,100),'enrollment boundary');
check(!Policy::released(['mode'=>'unknown'],100,null),'unknown mode');
for ($w=1;$w<=4;$w++) check(Policy::progress(12,$w*3,4,4,12,$w<4)['completed_percent']===$w*25,'week '.$w.' progress');
check(Policy::progress(6,3,2,4,12,true)['completed_percent']===25,'two topics created, four planned');
check(Policy::progress(6,6,2,4,12,false)['completed_percent']===50,'missing future topics');
check(Policy::progress(6,6,2,4,0,false)['completed_percent']<100,'reserve missing topics');
check(Policy::progress(6,6,2,2,6,true)['completed_percent']<100,'locked empty topic');
check(Policy::progress(201,200,4,4,201,false)['completed_percent']===99,'no rounded 100');
check(Policy::progress(0,0,0,4,12,true)['completed_percent']===0,'empty course');

$p=[['topic_id'=>1,'title'=>'One','expected_activities'=>3],['topic_id'=>2,'title'=>'Two','expected_activities'=>5],['topic_id'=>0,'title'=>'Three','expected_activities'=>2],['topic_id'=>0,'title'=>'Four','expected_activities'=>4]];
$s=Policy::summarizePlan($p,[1=>3,2=>5]);
check($s['expected_activities']===14&&$s['expected_topics']===4,'unequal per-topic plan sums 3+5+2+4');
check(Policy::progress(8,3,2,$s['expected_topics'],$s['expected_activities'],true)['completed_percent']===21,'first topic has unequal progress weight');
$s=Policy::summarizePlan($p,[1=>5,2=>3]);
check($s['expected_activities']===16&&$s['missing_activities']===8,'extra activity in one topic cannot cover another topic deficit');
$s=Policy::summarizePlan($p,[1=>3,2=>5,3=>1]);
check($s['expected_activities']===15&&$s['missing_topics']===2,'new unlinked topic counts separately until explicitly linked');
$p[2]['topic_id']=3;
$s=Policy::summarizePlan($p,[1=>3,2=>5,3=>1]);
check($s['expected_activities']===14&&$s['missing_topics']===1,'explicit binding consumes the reserved future topic once');
$s=Policy::summarizePlan($p,[1=>3,3=>1]);
check($s['expected_activities']===14&&$s['missing_topics']===2,'deleted or unpublished topic retains its reservation');
$s=Policy::summarizePlan([],[1=>0]);
check($s['expected_activities']===1&&$s['actual_activities']===0,'empty topic reserves an activity');
