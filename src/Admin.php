<?php
declare(strict_types=1);
namespace TutorLMS\ContentDrip;
defined('ABSPATH') || exit;

final class Admin {
    private static array $screens = [];
    public static function register(): void {
        add_action('admin_menu',[self::class,'menu'],90);
        add_action('admin_enqueue_scripts',[self::class,'assets']);
        add_action('admin_post_tlcd_save',[self::class,'save']);
    }
    public static function tr(string $en,string $th): string {
        return str_starts_with(get_user_locale(),'th') ? $th : $en;
    }
    public static function menu(): void {
        $title=self::tr('Content Drip','กำหนดการเปิดบทเรียน');
        self::$screens[]=add_submenu_page('tutor',$title,$title,'edit_posts','tlcd-content-drip',[self::class,'page']);
        // Retain the old Tools URL for existing links and course editors.
        self::$screens[]=add_management_page($title,$title,'edit_posts','tlcd',[self::class,'page']);
    }
    public static function assets(string $hook): void {
        if (!in_array($hook,self::$screens,true)) return;
        wp_enqueue_style('tlcd-admin',plugins_url('assets/admin.css',__FILE__),[],'0.1.2');
        wp_enqueue_script('tlcd-admin',plugins_url('assets/admin.js',__FILE__),[],'0.1.2',true);
    }
    public static function pageSlug(): string {
        return current_user_can('manage_tutor_instructor') ? 'tlcd-content-drip' : 'tlcd';
    }
    public static function pagePath(): string {
        return current_user_can('manage_tutor_instructor') ? 'admin.php' : 'tools.php';
    }
    public static function update(int $course,array $input) {
        if (get_post_type($course)!=='courses'||!current_user_can('edit_post',$course))
            return new \WP_Error('tlcd_forbidden','You cannot manage this course.');
        $topics=Plugin::topics($course);
        $et=filter_var($input['expected_topics']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000]]);
        $ea=filter_var($input['expected_activities']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>100000]]);
        if ($et===false||$ea===false) return new \WP_Error('tlcd_invalid_plan','Invalid curriculum plan.');
        $cfg=['enabled'=>!empty($input['enabled']),'expected_topics'=>$et,'expected_activities'=>$ea,'rules'=>[]];
        if ($cfg['enabled']&&!$et&&!$ea) return new \WP_Error('tlcd_invalid_plan','Set a planned topic or activity count.');
        foreach ($topics as $topic) {
            $id=(int)$topic->ID;$raw=$input['rules'][$id]??['mode'=>'manual'];
            if (!is_array($raw)||!in_array($raw['mode']??'',Policy::MODES,true)) return new \WP_Error('tlcd_invalid_rule','Invalid release mode.');
            $rule=['mode'=>$raw['mode'],'manual_open'=>!empty($raw['manual_open']),'release_at'=>0,'days'=>0];
            if ($rule['mode']==='scheduled') {
                $local=$raw['local_time']??'';
                $date=is_string($local)?\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$local,wp_timezone()):false;
                if (!$date||$date->format('Y-m-d\TH:i')!==$local) return new \WP_Error('tlcd_invalid_date','Invalid release date for topic '.$id);
                $rule['release_at']=$date->getTimestamp();
            }
            if ($rule['mode']==='enrollment') {
                $days=filter_var($raw['days']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>3650]]);
                if ($days===false) return new \WP_Error('tlcd_invalid_days','Invalid enrollment delay.');
                $rule['days']=$days;
            }
            $cfg['rules'][$id]=$rule;
        }
        if (Plugin::config($course)!==$cfg&&update_post_meta($course,Plugin::META,$cfg)===false) return new \WP_Error('tlcd_save_failed','Could not save policy.');
        do_action('tlcd_policy_updated',$course,$cfg);
        clean_post_cache($course);
        return $cfg;
    }
    public static function page(): void {
        if (!current_user_can('edit_posts')) wp_die('Forbidden','',['response'=>403]);
        $course=isset($_GET['course_id'])&&is_scalar($_GET['course_id'])?absint($_GET['course_id']):0;
        $courses=get_posts(['post_type'=>'courses','post_status'=>['publish','draft','private','pending'],'numberposts'=>-1,'orderby'=>'title','order'=>'ASC']);
        $courses=array_values(array_filter($courses,static fn($p)=>current_user_can('edit_post',$p->ID)));
        if (!$course && count($courses)===1) $course=(int)$courses[0]->ID;
        $valid=$course && get_post_type($course)==='courses' && current_user_can('edit_post',$course);
        $cfg=$valid?Plugin::config($course):[];
        $topics=$valid?Plugin::topics($course):[];
        $items=$valid?(tutor_utils()->get_course_contents_by_id($course)?:[]):[];
        $counts=array_fill_keys(array_map(static fn($t)=>(int)$t->ID,$topics),0);
        foreach ($items as $item) {
            $parent=(int)$item->post_parent;
            if (isset($counts[$parent])) $counts[$parent]++;
        }
        $actual=array_sum($counts);
        $plannedTopics=(int)($cfg['expected_topics']??count($topics));
        $plannedActivities=(int)($cfg['expected_activities']??$actual);
        $enabled=!empty($cfg['enabled']);
        $now=time();
        $labels=[
            'open'=>self::tr('Open','เปิดแล้ว'),
            'locked'=>self::tr('Locked','ยังไม่เปิด'),
            'scheduled'=>self::tr('Scheduled','รอตามกำหนด'),
            'relative'=>self::tr('Per learner','ตามวันลงทะเบียน'),
            'inactive'=>self::tr('Not enforced','ยังไม่บังคับใช้'),
        ];
        require __DIR__.'/views/admin.php';
    }
    public static function save(): void {
        $course=isset($_POST['course_id'])&&is_scalar($_POST['course_id'])?absint($_POST['course_id']):0;
        check_admin_referer('tlcd_save_'.$course);
        $result=self::update($course,isset($_POST['policy'])&&is_array($_POST['policy'])?wp_unslash($_POST['policy']):[]);
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()),'',['response'=>400,'back_link'=>true]);
        wp_safe_redirect(add_query_arg(['page'=>self::pageSlug(),'course_id'=>$course,'saved'=>1],admin_url(self::pagePath())));exit;
    }
}
