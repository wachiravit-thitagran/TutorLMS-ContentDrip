<?php
declare(strict_types=1);
namespace TutorLMS\ContentDrip;
use Tutor\Models\EnrollmentModel;
use Tutor\Models\QuizModel;
defined('ABSPATH') || exit;

final class Plugin {
    public const META = '_tlcd_policy';
    public const DOMAIN = 'tutor-lms-content-drip';
    public const TYPES = ['lesson','tutor_quiz','tutor_assignments'];

    public static function register(): void {
        add_filter('tutor_course_content_access',[self::class,'access'],PHP_INT_MAX,2);
        add_filter('tutor_course/single/content/show_permalink',[self::class,'permalink'],PHP_INT_MAX,2);
        add_filter('tutor_course/contents/lesson/title',[self::class,'lockedTitle'],20,2);
        add_filter('the_title',[self::class,'topicTitle'],20,2);
        add_filter('tutor_validate_lesson_complete',[self::class,'lessonComplete'],PHP_INT_MAX,3);
        add_action('tutor_quiz/start/before',[self::class,'quizStart'],1,2);
        add_action('tutor_quiz_before_finish',[self::class,'quizFinish'],1,3);
        add_action('template_redirect',[self::class,'guardPage'],0);
        foreach (['tutor_render_lesson_content','tutor_render_quiz_content','tutor_quiz_timeout','tutor_quiz_abandon','tutor_quiz_check_answer','tutor_load_lesson_comments','tutor_single_course_lesson_load_more','tutor_create_lesson_comment','tutor_delete_lesson_comment','tutor_update_lesson_comment','tutor_reply_lesson_comment'] as $action) {
            add_action('wp_ajax_'.$action,[self::class,'guardAjax'],0);
            add_action('wp_ajax_nopriv_'.$action,[self::class,'guardAjax'],0);
        }
        add_filter('tutor_course_completed_percent',[self::class,'progressFilter'],PHP_INT_MAX,4);
        add_filter('tutor_user_can_complete_course',[self::class,'completionFilter'],PHP_INT_MAX,3);
        add_filter('is_completed_course',[self::class,'completedFilter'],PHP_INT_MAX,3);
        add_action('tutor_course_complete_before',[self::class,'beforeComplete'],0,1);
        add_filter('rest_endpoints',[self::class,'restEndpoints'],100);
        foreach (array_merge(['topics'],self::TYPES) as $type)
            add_filter('rest_prepare_'.$type,[self::class,'redactRest'],PHP_INT_MAX,3);
        add_action('template_redirect',[self::class,'noCache'],1);
        add_filter('tlcd_course_completion_allowed',static fn($ok,$course,$user)=>$ok && self::eligible((int)$course,(int)$user),10,3);
    }
    public static function config(int $course): array {
        $value=get_post_meta($course,self::META,true);
        return is_array($value)?$value:[];
    }
    public static function enabled(int $course): bool {
        return !empty(self::config($course)['enabled']);
    }
    public static function topics(int $course): array {
        return get_posts(['post_type'=>'topics','post_parent'=>$course,'post_status'=>'publish',
            'numberposts'=>-1,'orderby'=>['menu_order'=>'ASC','ID'=>'ASC'],'suppress_filters'=>true]);
    }
    public static function topicCourse(int $topic): int {
        if (!$topic) return 0;
        $post=get_post($topic);
        return $post && $post->post_type==='topics' && get_post_type($post->post_parent)==='courses'?(int)$post->post_parent:0;
    }
    public static function itemTopic(int $item): int {
        if (!$item) return 0;
        $post=get_post($item);
        if (!$post) return 0;
        if ($post->post_type==='topics') return self::topicCourse($item)?$item:0;
        return in_array($post->post_type,self::TYPES,true)&&self::topicCourse((int)$post->post_parent)?(int)$post->post_parent:0;
    }
    public static function editor(int $course,int $user): bool {
        return $user>0 && user_can($user,'edit_post',$course);
    }
    public static function enrollmentTime(int $course,int $user): ?int {
        if (!$user) return null;
        $e=EnrollmentModel::is_enrolled($course,$user);
        if (!$e||empty($e->post_date_gmt)) return null;
        $at=strtotime($e->post_date_gmt.' UTC');
        return $at!==false&&$at>0?$at:null;
    }
    public static function topicOpen(int $topic,int $user,?int $now=null): bool {
        $course=self::topicCourse($topic);
        if (!$course||!self::enabled($course)) return true;
        $rule=self::config($course)['rules'][$topic]??['mode'=>'manual'];
        if (!is_array($rule)) return false;
        $enrolled=($rule['mode']??'')==='enrollment'?self::enrollmentTime($course,$user):null;
        return Policy::released($rule,$now??time(),$enrolled);
    }
    public static function locked(int $item,int $user,bool $editorBypass=true): bool {
        $topic=self::itemTopic($item);
        if (!$topic) return false;
        if ($editorBypass&&self::editor(self::topicCourse($topic),$user)) return false;
        return !self::topicOpen($topic,$user);
    }
    public static function message(): string {
        return __('This topic has not been released yet.',self::DOMAIN);
    }
    public static function denied(): \WP_Error {
        return new \WP_Error('tlcd_locked',self::message(),['status'=>403]);
    }
    public static function access($ok,array $args): bool {
        return (bool)$ok&&!self::locked((int)($args['current_post_id']??0),get_current_user_id());
    }
    public static function permalink($ok,$item) {
        return self::locked((int)$item,get_current_user_id())?false:$ok;
    }
    public static function lockedTitle($title,$item) {
        return self::locked((int)$item,get_current_user_id())?esc_html(get_post_field('post_title',(int)$item)).' — '.esc_html__('Locked',self::DOMAIN):$title;
    }
    public static function topicTitle($title,$id) {
        if (is_admin()||get_post_type((int)$id)!=='topics'||!self::locked((int)$id,get_current_user_id())) return $title;
        $rule=self::config(self::topicCourse((int)$id))['rules'][(int)$id]??[];
        $label=__('Locked',self::DOMAIN);
        if (($rule['mode']??'')==='scheduled'&&!empty($rule['release_at']))
            $label=sprintf(__('Opens %s',self::DOMAIN),wp_date(get_option('date_format').' '.get_option('time_format'),(int)$rule['release_at']));
        return $title.' — '.$label;
    }
    public static function lessonComplete($ok,$user,$item): bool {
        return (bool)$ok&&!self::locked((int)$item,(int)$user,false);
    }
    private static function denyPage(): void {
        nocache_headers();
        wp_die(esc_html(self::message()),esc_html__('Content locked',self::DOMAIN),['response'=>403,'back_link'=>true]);
    }
    public static function quizStart($quiz,$user): void {
        if (self::locked((int)$quiz,(int)$user,false)) self::denyPage();
    }
    public static function quizFinish($attempt,$quiz,$user): void {self::quizStart($quiz,$user);}
    private static function inputId(string $key): int {
        return isset($_POST[$key])&&is_scalar($_POST[$key])?absint($_POST[$key]):0;
    }
    private static function attemptQuiz(int $id,int $user): int {
        if (!$id||!$user) return 0;
        $a=tutor_utils()->get_attempt($id);
        return is_object($a)&&(int)$a->user_id===$user?(int)$a->quiz_id:0;
    }
    private static function inputItems(int $user): array {
        return [self::inputId('lesson_id'),self::inputId('quiz_id'),self::inputId('post_id'),self::attemptQuiz(self::inputId('attempt_id'),$user)];
    }
    public static function guardPage(): void {
        $user=get_current_user_id();
        if (is_singular(array_merge(['topics'],self::TYPES))&&self::locked((int)get_queried_object_id(),$user)) self::denyPage();
        $a=isset($_POST['tutor_action'])&&is_string($_POST['tutor_action'])?sanitize_key(wp_unslash($_POST['tutor_action'])):'';
        if (in_array($a,['tutor_start_quiz','tutor_finish_quiz_attempt','tutor_answering_quiz_question','tutor_complete_lesson'],true))
            foreach (self::inputItems($user) as $id) if ($id&&self::locked($id,$user,false)) self::denyPage();
    }
    public static function guardAjax(): void {
        $user=get_current_user_id();
        foreach (self::inputItems($user) as $id) if ($id&&self::locked($id,$user)) {
            nocache_headers();
            wp_send_json_error(['message'=>self::message(),'code'=>'tlcd_locked'],403);
        }
    }
    public static function noCache(): void {
        $id=(int)get_queried_object_id();
        if (!$id) return;
        $course=get_post_type($id)==='courses'?$id:self::topicCourse(self::itemTopic($id));
        if ($course&&self::enabled($course)) {
            if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE',true);
            nocache_headers();
        }
    }
    /** Preserve native Tutor activity semantics; count only released published items. */
    public static function stats(int $course,int $user): array {
        $cfg=self::config($course);
        $topics=self::topics($course);
        $items=tutor_utils()->get_course_contents_by_id($course)?:[];
        $locked=[];$published=[];
        foreach ($topics as $topic) {
            $published[(int)$topic->ID]=true;
            if (!self::topicOpen((int)$topic->ID,$user)) $locked[(int)$topic->ID]=true;
        }
        $nonempty=[];$actual=0;$completed=0;$quizzes=[];
        foreach ($items as $item) {
            $topic=(int)$item->post_parent;
            if (!isset($published[$topic])) continue;
            $actual++;$nonempty[$topic]=true;
            if (isset($locked[$topic])||!$user) continue;
            if ($item->post_type==='lesson'&&tutor_utils()->is_completed_lesson($item->ID,$user)) $completed++;
            elseif ($item->post_type==='tutor_quiz') $quizzes[]=(int)$item->ID;
            elseif ($item->post_type==='tutor_assignments'&&tutor_utils()->is_assignment_submitted($item->ID,$user)) $completed++;
        }
        if ($quizzes) {
            global $wpdb;
            $marks=implode(',',array_fill(0,count($quizzes),'%d'));
            $args=array_merge([$user,QuizModel::ATTEMPT_STARTED],$quizzes);
            $completed+=(int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT quiz_id) FROM {$wpdb->prefix}tutor_quiz_attempts WHERE user_id=%d AND attempt_status!=%s AND quiz_id IN ($marks)",...$args));
        }
        return Policy::progress($actual,$completed,count($nonempty),max(count($topics),(int)($cfg['expected_topics']??0)),(int)($cfg['expected_activities']??0),!empty($locked));
    }
    public static function progressFilter($result,$course,$user,$getStats) {
        if (!self::enabled((int)$course)) return $result;
        $s=self::stats((int)$course,(int)$user);
        return $getStats?$s:$s['completed_percent'];
    }
    public static function eligible(int $course,int $user): bool {
        if (!self::enabled($course)) return true;
        if (!$user) return false;
        $s=self::stats($course,$user);
        return $s['total_count']>0&&$s['completed_count']===$s['total_count'];
    }
    public static function completionFilter($ok,$user,$course) {
        if (is_wp_error($ok)||!$ok) return $ok;
        return self::eligible((int)$course,(int)$user)?$ok:new \WP_Error('tlcd_incomplete',__('Complete every planned topic before completing this course.',self::DOMAIN));
    }
    public static function completedFilter($completed,$course,$user) {
        return $completed&&!self::eligible((int)$course,(int)$user)?false:$completed;
    }
    /** Core before hook has no target learner ID, so use only for current learner requests. */
    public static function beforeComplete($course): void {
        $user=get_current_user_id();
        if ($user&&!self::editor((int)$course,$user)&&!self::eligible((int)$course,$user)) self::denyPage();
    }
    private static function anyLocked(int $course,int $user): bool {
        if (!self::enabled($course)||self::editor($course,$user)) return false;
        foreach (self::topics($course) as $topic) if (!self::topicOpen((int)$topic->ID,$user)) return true;
        return false;
    }
    /** Known Tutor v4.2.1 content endpoints: guard after original permission checks. */
    public static function restEndpoints(array $routes): array {
        foreach ($routes as $route=>&$handlers) {
            if (!preg_match('~^/tutor/v1/(?:lessons|quizzes|quiz-question-answer|quiz-attempt-details|topics|course-contents)(?:/|$)~',$route)) continue;
            foreach ($handlers as &$handler) {
                if (!is_array($handler)||!isset($handler['callback'])||!is_callable($handler['callback'])) continue;
                $original=$handler['callback'];
                $handler['callback']=static function ($request) use ($original) {
                    $path=$request->get_route();$user=get_current_user_id();
                    $id=absint($request->get_param('id'));$topic=absint($request->get_param('topic_id'));
                    if (preg_match('~/(?:topics|course-contents)(?:/|$)~',$path)) {
                        if (self::anyLocked($id?:absint($request->get_param('course_id')),$user)) return self::denied();
                    } elseif (($topic&&self::locked($topic,$user))||($id&&self::locked($id,$user))) return self::denied();
                    return call_user_func($original,$request);
                };
            }
            unset($handler);
        }
        unset($handlers);
        return $routes;
    }
    public static function redactRest($response,$post,$request) {
        if (is_object($post)&&self::locked((int)$post->ID,get_current_user_id())&&$response instanceof \WP_REST_Response)
            $response->set_data(['id'=>(int)$post->ID,'title'=>['rendered'=>esc_html($post->post_title)],'locked'=>true]);
        return $response;
    }
}
