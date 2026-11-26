<?php
declare(strict_types=1);
namespace TutorLMS\ContentDrip;
defined('ABSPATH') || exit;

final class Admin {
    public static function register(): void {
        add_action('admin_menu',[self::class,'menu']);
        add_action('admin_post_tlcd_save',[self::class,'save']);
    }
    public static function menu(): void {
        add_management_page('Tutor LMS Content Drip','Content Drip','edit_posts','tlcd',[self::class,'page']);
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
        echo '<div class="wrap"><h1>Tutor LMS Content Drip</h1>';
        if (isset($_GET['saved'])) echo '<div class="notice notice-success"><p>Policy saved.</p></div>';
        echo '<form method="get" action="'.esc_url(admin_url('tools.php')).'"><input type="hidden" name="page" value="tlcd"><label for="tlcd-course">Course</label> <select id="tlcd-course" name="course_id"><option value="0">—</option>';
        foreach ($courses as $p) if (current_user_can('edit_post',$p->ID))
            printf('<option value="%d" %s>%s</option>',$p->ID,selected($course,$p->ID,false),esc_html($p->post_title));
        echo '</select> ';submit_button('Manage','secondary','',false);echo '</form>';
        if (!$course||get_post_type($course)!=='courses'||!current_user_can('edit_post',$course)) {echo '</div>';return;}
        $cfg=Plugin::config($course);$topics=Plugin::topics($course);$actual=count(tutor_utils()->get_course_contents_by_id($course)?:[]);
        echo '<hr><h2>'.esc_html(get_post_field('post_title',$course)).'</h2><p>New topics are locked by default. Review existing completions before enabling. All planned activities remain in progress.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tlcd_save"><input type="hidden" name="course_id" value="'.esc_attr((string)$course).'">';
        wp_nonce_field('tlcd_save_'.$course);
        echo '<p><label><input type="checkbox" name="policy[enabled]" value="1" '.checked(!empty($cfg['enabled']),true,false).'> Enable Content Drip</label></p>';
        printf('<p><label>Planned topics <input type="number" min="0" max="1000" name="policy[expected_topics]" value="%d"></label> (%d published)</p>',(int)($cfg['expected_topics']??count($topics)),count($topics));
        printf('<p><label>Planned activities <input type="number" min="0" max="100000" name="policy[expected_activities]" value="%d"></label> (%d published)</p>',(int)($cfg['expected_activities']??$actual),$actual);
        echo '<p>Site timezone: '.esc_html(wp_timezone_string()).'.</p><table class="widefat striped"><thead><tr><th>Topic</th><th>Release mode</th><th>Manual open</th><th>Release date/time</th><th>Days after enrollment</th></tr></thead><tbody>';
        foreach ($topics as $t) {
            $id=(int)$t->ID;$r=$cfg['rules'][$id]??['mode'=>'manual'];$prefix='policy[rules]['.$id.']';
            printf('<tr><td><strong>%s</strong></td><td><select aria-label="Release mode" name="%s[mode]">',esc_html($t->post_title),esc_attr($prefix));
            foreach (['open'=>'Always open','manual'=>'Manual','scheduled'=>'Scheduled','enrollment'=>'After enrollment'] as $v=>$label)
                printf('<option value="%s" %s>%s</option>',esc_attr($v),selected($r['mode'],$v,false),esc_html($label));
            $local=empty($r['release_at'])?'':wp_date('Y-m-d\TH:i',(int)$r['release_at']);
            printf('</select></td><td><input aria-label="Manual open" type="checkbox" name="%s[manual_open]" value="1" %s></td><td><input aria-label="Release date" type="datetime-local" name="%s[local_time]" value="%s"></td><td><input aria-label="Days" type="number" min="0" max="3650" name="%s[days]" value="%d"></td></tr>',
                esc_attr($prefix),checked(!empty($r['manual_open']),true,false),esc_attr($prefix),esc_attr($local),esc_attr($prefix),(int)($r['days']??0));
        }
        echo '</tbody></table>';submit_button('Save release policy');echo '</form></div>';
    }
    public static function save(): void {
        $course=isset($_POST['course_id'])&&is_scalar($_POST['course_id'])?absint($_POST['course_id']):0;
        check_admin_referer('tlcd_save_'.$course);
        $result=self::update($course,isset($_POST['policy'])&&is_array($_POST['policy'])?wp_unslash($_POST['policy']):[]);
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()),'',['response'=>400,'back_link'=>true]);
        wp_safe_redirect(add_query_arg(['page'=>'tlcd','course_id'=>$course,'saved'=>1],admin_url('tools.php')));exit;
    }
}
