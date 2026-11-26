<?php
declare(strict_types=1);
namespace TutorLMS\ContentDrip;
defined('ABSPATH') || exit;

final class Abilities {
    public static function register(): void {
        add_action('wp_abilities_api_categories_init',static function () {
            if (function_exists('wp_register_ability_category')) wp_register_ability_category('tutorlms-content-drip',['label'=>'Tutor LMS Content Drip','description'=>'Manage topic release policies.']);
        });
        add_action('wp_abilities_api_init',[self::class,'init']);
    }
    public static function permitted(array $input): bool {
        $course=(int)($input['course_id']??0);
        return get_post_type($course)==='courses'&&current_user_can('edit_post',$course);
    }
    public static function init(): void {
        if (!function_exists('wp_register_ability')) return;
        $schema=['type'=>'object','properties'=>['course_id'=>['type'=>'integer','minimum'=>1]],'required'=>['course_id'],'additionalProperties'=>false];
        wp_register_ability('tutorlms-content-drip/get-policy',[
            'label'=>'Get Content Drip Policy','description'=>'Read the curriculum and release policy.','category'=>'tutorlms-content-drip','input_schema'=>$schema,
            'output_schema'=>['type'=>'object','additionalProperties'=>true],'permission_callback'=>[self::class,'permitted'],
            'execute_callback'=>static function(array $input):array {
                $id=(int)$input['course_id'];$curriculum=Plugin::curriculum($id);
                return ['course_id'=>$id,'policy'=>Plugin::config($id),'topics'=>array_map(static fn($p)=>['id'=>(int)$p->ID,'title'=>$p->post_title,'actual_activities'=>$curriculum['counts'][(int)$p->ID]],$curriculum['topics']),'plan_summary'=>Plugin::planSummary($id)];
            },
            'meta'=>['show_in_rest'=>true,'mcp'=>['public'=>true,'type'=>'tool'],'annotations'=>['readonly'=>true,'destructive'=>false,'idempotent'=>true]],
        ]);
        $schema['properties']['policy']=['type'=>'object','properties'=>[
            'enabled'=>['type'=>'boolean'],'expected_topics'=>['type'=>'integer','minimum'=>0,'maximum'=>1000],
            'expected_activities'=>['type'=>'integer','minimum'=>0,'maximum'=>100000],
            'plan'=>['type'=>'array','maxItems'=>1000,'description'=>'Explicit per-topic activity targets. topic_id 0 reserves a future topic; link it to the published topic ID once created. Existing topics not included are counted automatically. Totals are derived.','items'=>[
                'type'=>'object','properties'=>['topic_id'=>['type'=>'integer','minimum'=>0],'title'=>['type'=>'string'],'expected_activities'=>['type'=>'integer','minimum'=>1,'maximum'=>100000]],
                'required'=>['topic_id','title','expected_activities'],'additionalProperties'=>false]],
            'rules'=>['type'=>'object','additionalProperties'=>['type'=>'object','properties'=>[
                'mode'=>['type'=>'string','enum'=>Policy::MODES],'manual_open'=>['type'=>'boolean'],
                'local_time'=>['type'=>'string','description'=>'WordPress local time YYYY-MM-DDTHH:MM'],'days'=>['type'=>'integer','minimum'=>0,'maximum'=>3650],
            ],'required'=>['mode'],'additionalProperties'=>false]],
        ],'required'=>['enabled','rules'],'additionalProperties'=>false];
        $schema['required'][]='policy';
        wp_register_ability('tutorlms-content-drip/update-policy',[
            'label'=>'Update Content Drip Policy','description'=>'Replace release rules and optionally the per-topic plan. Omitted release rules are locked; omitting plan preserves a saved per-topic plan. Legacy aggregate counts remain supported.','category'=>'tutorlms-content-drip',
            'input_schema'=>$schema,'output_schema'=>['type'=>'object','additionalProperties'=>true],'permission_callback'=>[self::class,'permitted'],
            'execute_callback'=>static fn(array $input)=>Admin::update((int)$input['course_id'],$input['policy']),
            'meta'=>['show_in_rest'=>true,'mcp'=>['public'=>true,'type'=>'tool'],'annotations'=>['readonly'=>false,'destructive'=>false,'idempotent'=>true]],
        ]);
    }
}
