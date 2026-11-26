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
