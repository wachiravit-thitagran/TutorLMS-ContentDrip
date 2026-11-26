<?php
declare(strict_types=1);
namespace TutorLMS\ContentDrip;
defined('ABSPATH') || exit;
$t=static fn(string $en,string $th):string=>esc_html(Admin::tr($en,$th));
$attr=static fn(string $en,string $th):string=>esc_attr(Admin::tr($en,$th));
?>
<div class="wrap tlcd-admin" data-now="<?php echo esc_attr(wp_date('Y-m-d\TH:i',$now)); ?>"
     data-open="<?php echo esc_attr($labels['open']); ?>" data-locked="<?php echo esc_attr($labels['locked']); ?>"
     data-scheduled="<?php echo esc_attr($labels['scheduled']); ?>" data-relative="<?php echo esc_attr($labels['relative']); ?>"
     data-inactive="<?php echo esc_attr($labels['inactive']); ?>"
     data-unsaved="<?php echo $attr('You have unsaved changes. Leave this page?','ยังมีการเปลี่ยนแปลงที่ไม่ได้บันทึก ต้องการออกจากหน้านี้หรือไม่'); ?>">
    <div class="tlcd-heading">
        <div><p class="tlcd-kicker">TUTOR LMS / CONTENT DRIP</p>
        <h1><?php echo $t('Topic release','กำหนดการเปิดบทเรียน'); ?></h1>
        <p class="tlcd-muted"><?php echo $t('Manage when learners can access each topic and protect course completion.','จัดการวันเปิดเรียนแต่ละบท และป้องกันการเรียนจบก่อนครบหลักสูตร'); ?></p></div>
        <?php if ($valid): ?>
        <a class="button" href="<?php echo esc_url(get_permalink($course)); ?>" target="_blank" rel="noopener noreferrer"><?php echo $t('View course','ดูหน้าคอร์ส'); ?> <span aria-hidden="true">↗</span></a>
        <?php endif; ?>
    </div>
    <?php if (isset($_GET['saved'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo $t('Release policy saved.','บันทึกกำหนดการเปิดบทเรียนแล้ว'); ?></p></div>
    <?php endif; ?>
    <form class="tlcd-course-picker" method="get" action="<?php echo esc_url(admin_url(Admin::pagePath())); ?>">
        <input type="hidden" name="page" value="<?php echo esc_attr(Admin::pageSlug()); ?>">
        <label for="tlcd-course"><?php echo $t('Course','หลักสูตร'); ?></label>
        <select id="tlcd-course" name="course_id">
            <option value="0"><?php echo $t('Select a course','เลือกหลักสูตร'); ?></option>
            <?php foreach ($courses as $p): ?>
                <option value="<?php echo (int)$p->ID; ?>" <?php selected($course,$p->ID); ?>><?php echo esc_html($p->post_title?:'(Untitled)'); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="button"><?php echo $t('Manage','จัดการ'); ?></button>
    </form>
    <?php if (!$valid): ?>
        <div class="tlcd-panel tlcd-empty"><h2><?php echo $t('Choose a course to get started','เลือกหลักสูตรเพื่อเริ่มจัดการ'); ?></h2><p><?php echo $t('Only courses you can edit are shown.','แสดงเฉพาะหลักสูตรที่คุณมีสิทธิ์แก้ไข'); ?></p></div>
    <?php else: ?>
    <form id="tlcd-policy" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="tlcd_save">
        <input type="hidden" name="course_id" value="<?php echo (int)$course; ?>">
        <?php wp_nonce_field('tlcd_save_'.$course); ?>
        <section class="tlcd-panel tlcd-policy-head" aria-labelledby="tlcd-course-title">
            <div>
                <p class="tlcd-kicker"><?php echo $t('RELEASE POLICY','นโยบายการเปิดเรียน'); ?></p>
                <h2 id="tlcd-course-title"><?php echo esc_html(get_post_field('post_title',$course)); ?></h2>
                <p class="tlcd-muted"><?php echo $t('Unconfigured topics are locked when the policy is enabled.','เมื่อเปิดใช้งาน บทที่ยังไม่ได้กำหนดเงื่อนไขจะถูกล็อกโดยอัตโนมัติ'); ?></p>
            </div>
            <div class="tlcd-enable">
                <label class="tlcd-toggle"><input id="tlcd-enabled" name="policy[enabled]" type="checkbox" value="1" <?php checked($enabled); ?>><span><?php echo $t('Enable Content Drip','เปิดใช้งาน Content Drip'); ?></span></label>
                <p id="tlcd-policy-state" class="tlcd-muted"><?php echo $enabled?$t('Saved policy is enabled.','นโยบายที่บันทึกไว้เปิดใช้งานแล้ว'):$t('Saved policy is disabled.','นโยบายที่บันทึกไว้ยังไม่เปิดใช้งาน'); ?></p>
            </div>
        </section>
        <section class="tlcd-panel" aria-labelledby="tlcd-plan-heading">
            <div class="tlcd-section-head">
                <div><h2 id="tlcd-plan-heading"><?php echo $t('Curriculum plan','แผนหลักสูตร'); ?></h2>
                <p class="tlcd-muted"><?php echo $t('Topics are loaded from Tutor LMS. Set each real topic’s activity target; published activities are counted automatically.','ดึงบทจริงจาก Tutor LMS อัตโนมัติ กำหนดจำนวนกิจกรรมตามแผนแยกแต่ละบทได้'); ?></p></div>
                <a id="tlcd-course-builder" class="button" href="<?php echo esc_url($builderUrl); ?>"><?php echo $t('Edit curriculum in Tutor LMS','จัดการบทเรียนใน Tutor LMS'); ?> <span aria-hidden="true">↗</span></a>
            </div>
            <input type="hidden" name="policy[plan_mode]" value="per_topic">
            <?php if ($legacy): ?>
                <div class="tlcd-legacy-note">
                    <p><?php echo $t('An older policy reserves topics or activities outside the current curriculum. Its protection stays in place until you confirm the switch. Review the real topics and activity targets first; removing a reservation can increase learner progress.','นโยบายเดิมมีบทหรือกิจกรรมสำรองนอกหลักสูตรจริง ระบบยังคงป้องกันตามแผนเดิมจนกว่าจะยืนยัน โปรดตรวจสอบบทและจำนวนกิจกรรมก่อน การลบยอดสำรองอาจทำให้ความคืบหน้าของผู้เรียนเพิ่มขึ้น'); ?></p>
                    <p><strong><?php echo $t('Previous saved totals','ยอดที่บันทึกไว้เดิม'); ?>: <?php echo $legacyTopics; ?> <?php echo $t('topics','บท'); ?> / <?php echo $legacyActivities; ?> <?php echo $t('activities','กิจกรรม'); ?></strong></p>
                    <label class="tlcd-toggle"><input type="checkbox" name="policy[reconcile_legacy]" value="1"><span><?php echo $t('Confirm replacing old reservations with the real Course Builder curriculum','ยืนยันใช้เฉพาะบทจริงจาก Course Builder แทนรายการสำรองเดิม'); ?></span></label>
                </div>
            <?php endif; ?>
            <div class="tlcd-plan-table" id="tlcd-plan-table">
                <div class="tlcd-plan-header" aria-hidden="true"><span><?php echo $t('Topic','บทเรียน'); ?></span><span><?php echo $t('Created','สร้างแล้ว'); ?></span><span><?php echo $t('Planned activities','กิจกรรมตามแผน'); ?></span><span><?php echo $t('Remaining to create','ยังขาด'); ?></span></div>
                <?php
                foreach ($planRows as $index=>$r):
                    $id=(int)$r['topic_id'];$prefix='policy[plan]['.$index.']';
                ?>
                    <div class="tlcd-plan-row" data-plan-row data-kind="actual" data-topic-id="<?php echo $id; ?>" data-actual="<?php echo (int)$r['actual_activities']; ?>">
                        <div><strong><?php echo esc_html($r['title']); ?></strong><p class="tlcd-muted"><?php echo $t('Published topic','บทที่สร้างแล้ว'); ?></p>
                            <input type="hidden" name="<?php echo esc_attr($prefix); ?>[topic_id]" value="<?php echo $id; ?>">
                            <input type="hidden" name="<?php echo esc_attr($prefix); ?>[title]" value="<?php echo esc_attr($r['title']); ?>">
                        </div>
                        <div class="tlcd-plan-number"><span class="tlcd-mobile-label"><?php echo $t('Created','สร้างแล้ว'); ?></span><?php echo (int)$r['actual_activities']; ?></div>
                        <div class="tlcd-field"><label class="screen-reader-text" for="tlcd-plan-target-<?php echo $index; ?>"><?php echo $t('Planned activities','กิจกรรมตามแผน'); ?> — <?php echo esc_html($r['title']); ?></label>
                            <input class="tlcd-plan-target" id="tlcd-plan-target-<?php echo $index; ?>" name="<?php echo esc_attr($prefix); ?>[expected_activities]" type="number" min="<?php echo max(1,(int)$r['actual_activities']); ?>" max="100000" required value="<?php echo (int)$r['expected_activities']; ?>">
                        </div>
                        <div class="tlcd-plan-number"><span class="tlcd-mobile-label"><?php echo $t('Remaining','ยังขาด'); ?></span><span class="tlcd-plan-missing"><?php echo max(0,$r['expected_activities']-$r['actual_activities']); ?></span></div>
                    </div>
                <?php endforeach; ?>
                <div id="tlcd-plan-empty" class="tlcd-empty" <?php if ($planRows) echo 'hidden'; ?>><?php echo $t('No topics yet. Create topics in Tutor LMS Course Builder first.','ยังไม่มีบทเรียน กรุณาสร้างบทใน Course Builder ของ Tutor LMS ก่อน'); ?></div>
            </div>
            <div class="tlcd-plan-totals" aria-live="polite">
                <div><strong id="tlcd-planned-topics"><?php echo $plannedTopics; ?></strong><span><?php echo $t('Total topics','จำนวนบทตามแผน'); ?></span></div>
                <div><strong id="tlcd-planned-activities"><?php echo $plannedActivities; ?></strong><span><?php echo $t('Total activities','กิจกรรมตามแผนรวม'); ?></span></div>
                <div><strong id="tlcd-created-activities"><?php echo $actual; ?></strong><span><?php echo $t('Created activities','สร้างแล้ว'); ?></span></div>
                <div><strong id="tlcd-missing-activities"><?php echo max(0,$plannedActivities-$actual); ?></strong><span><?php echo $t('Remaining to create','กิจกรรมที่ยังขาด'); ?></span></div>
            </div>
            <p class="tlcd-muted tlcd-plan-help"><?php echo $t('Create all intended topics in Course Builder, even empty ones. Content Drip uses their topic IDs, so renaming or adding activities does not unlock a closed topic. New topics are locked by default when the policy is enabled.','สร้างบทจริงทั้งหมดใน Course Builder ล่วงหน้าได้แม้ยังไม่มีเนื้อหา การเปลี่ยนชื่อหรือเพิ่มกิจกรรมในบทที่ล็อกไว้จะไม่ปลดล็อก และบทใหม่จะถูกล็อกอัตโนมัติเมื่อเปิดใช้งาน Content Drip'); ?></p>
        </section>
        <section class="tlcd-panel tlcd-schedule" aria-labelledby="tlcd-schedule-title">
            <div class="tlcd-section-head"><div><h2 id="tlcd-schedule-title"><?php echo $t('Topic release schedule','ตารางเปิดบทเรียน'); ?></h2><p class="tlcd-muted"><?php echo $t('Status preview reflects these settings, not live learner progress.','สถานะด้านล่างเป็นตัวอย่างตามการตั้งค่า ไม่ใช่ความคืบหน้าจริงของผู้เรียน'); ?> · <?php echo esc_html(wp_timezone_string()); ?></p></div>
                <div class="tlcd-summary" aria-live="polite">
                    <span><span id="tlcd-open-count">—</span> <?php echo $t('open','เปิด'); ?></span>
                    <span><span id="tlcd-locked-count">—</span> <?php echo $t('locked / scheduled','ล็อก / รอเปิด'); ?></span>
                    <span><span id="tlcd-relative-count">—</span> <?php echo $t('per learner','ตามผู้เรียน'); ?></span>
                </div>
            </div>
            <div class="tlcd-rows">
                <?php foreach ($topics as $index=>$topic):
                    $id=(int)$topic->ID;
                    $r=$cfg['rules'][$id]??['mode'=>'manual'];
                    $mode=$r['mode']??'manual';
                    $prefix='policy[rules]['.$id.']';
                    $local=empty($r['release_at'])?'':wp_date('Y-m-d\TH:i',(int)$r['release_at']);
                    $state=!$enabled?'inactive':($mode==='enrollment'?'relative':(Policy::released($r,$now,null)?'open':($mode==='scheduled'?'scheduled':'locked')));
                ?>
                <article class="tlcd-row" data-topic="<?php echo $id; ?>">
                    <div class="tlcd-topic">
                        <span class="tlcd-order" aria-label="<?php echo $attr('Topic order','ลำดับบท'); ?>"><?php echo sprintf('%02d',$index+1); ?></span>
                        <div><h3><?php echo esc_html($topic->post_title); ?></h3>
                        <p class="tlcd-muted"><?php echo (int)($counts[$id]??0); ?> <?php echo $t('activities','กิจกรรม'); ?></p>
                        <span class="tlcd-status" data-state="<?php echo esc_attr($state); ?>"><span aria-hidden="true" class="tlcd-dot"></span><span class="tlcd-status-text"><?php echo esc_html($labels[$state]); ?></span></span></div>
                    </div>
                    <div class="tlcd-field">
                        <label for="tlcd-mode-<?php echo $id; ?>"><?php echo $t('Release method','วิธีเปิดเรียน'); ?></label>
                        <select id="tlcd-mode-<?php echo $id; ?>" class="tlcd-mode" name="<?php echo esc_attr($prefix); ?>[mode]">
                            <option value="open" <?php selected($mode,'open'); ?>><?php echo $t('Always open','เปิดทันที'); ?></option>
                            <option value="manual" <?php selected($mode,'manual'); ?>><?php echo $t('Manual release','เปิดด้วยตนเอง'); ?></option>
                            <option value="scheduled" <?php selected($mode,'scheduled'); ?>><?php echo $t('Scheduled date','เปิดตามวันและเวลา'); ?></option>
                            <option value="enrollment" <?php selected($mode,'enrollment'); ?>><?php echo $t('After enrollment','หลังลงทะเบียน'); ?></option>
                        </select>
                    </div>
                    <div class="tlcd-options">
                        <div class="tlcd-option" data-mode="open"><p class="tlcd-muted"><?php echo $t('Available to eligible learners immediately.','ผู้เรียนที่มีสิทธิ์เข้าเรียนได้ทันที'); ?></p></div>
                        <div class="tlcd-option" data-mode="manual">
                            <label class="tlcd-toggle"><input class="tlcd-manual" type="checkbox" name="<?php echo esc_attr($prefix); ?>[manual_open]" value="1" <?php checked(!empty($r['manual_open'])); ?>><span><?php echo $t('Open this topic','เปิดบทนี้'); ?></span></label>
                            <p class="tlcd-muted"><?php echo $t('Takes effect after saving.','มีผลหลังบันทึก'); ?></p>
                        </div>
                        <div class="tlcd-option tlcd-field" data-mode="scheduled">
                            <label for="tlcd-date-<?php echo $id; ?>"><?php echo $t('Release date & time','วันและเวลาเปิด'); ?></label>
                            <input class="tlcd-date" id="tlcd-date-<?php echo $id; ?>" type="datetime-local" name="<?php echo esc_attr($prefix); ?>[local_time]" value="<?php echo esc_attr($local); ?>">
                        </div>
                        <div class="tlcd-option tlcd-field" data-mode="enrollment">
                            <label for="tlcd-days-<?php echo $id; ?>"><?php echo $t('Days after enrollment','จำนวนวันหลังลงทะเบียน'); ?></label>
                            <input class="tlcd-days" id="tlcd-days-<?php echo $id; ?>" type="number" min="0" max="3650" name="<?php echo esc_attr($prefix); ?>[days]" value="<?php echo (int)($r['days']??0); ?>">
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
                <?php if (!$topics): ?><div class="tlcd-empty"><?php echo $t('No published topics. Add topics in the Tutor LMS course builder first.','ยังไม่มีบทเรียน กรุณาเพิ่มบทใน Course Builder ของ Tutor LMS'); ?></div><?php endif; ?>
            </div>
        </section>
        <div class="tlcd-footer">
            <p id="tlcd-dirty" role="status" class="tlcd-muted" data-dirty="<?php echo $attr('Unsaved changes','มีการเปลี่ยนแปลงที่ยังไม่ได้บันทึก'); ?>" data-clean="<?php echo $attr('Changes take effect after saving.','การตั้งค่ามีผลหลังบันทึก'); ?>"><?php echo $t('Changes take effect after saving.','การตั้งค่ามีผลหลังบันทึก'); ?></p>
            <button type="submit" class="button button-primary"><?php echo $t('Save release policy','บันทึกกำหนดการเปิดบทเรียน'); ?></button>
        </div>
    </form>
    <?php endif; ?>
</div>
