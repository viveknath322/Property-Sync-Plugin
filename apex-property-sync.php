<?php
/*
Plugin Name: Apex Homes Property Sync 
Description: Apex Homes Property Sync
Version: 2.2
Author: Vivek Nath
*/

if (!defined('ABSPATH')) exit;

/* ==========================================================================
   1. CSV DOWNLOAD HANDLER (SECURED)
   ========================================================================== */
add_action('admin_post_apex_download_log', 'apex_handle_csv_download');

function apex_handle_csv_download() {
    // SECURITY: Check permissions
    if (!current_user_can('manage_options')) wp_die('Unauthorized access');
    
    // SECURITY: Check Nonce
    check_admin_referer('apex_download_log_action', 'nonce');

    $log_index = isset($_GET['log_id']) ? intval($_GET['log_id']) : 0;
    $history = get_option('apex_sync_history', []);

    if (!isset($history[$log_index])) wp_die("Log not found.");

    $log = $history[$log_index];
    $filename = 'sync_report_' . date('Y-m-d_H-i', $log['start_time']) . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Reference No', 'Status', 'Property Title', 'Time Processed']);

    if (!empty($log['details'])) {
        foreach ($log['details'] as $row) {
            fputcsv($output, [
                sanitize_text_field($row['ref']), 
                sanitize_text_field($row['status']), 
                sanitize_text_field($row['title']), 
                sanitize_text_field($row['time'])
            ]);
        }
    }

    fclose($output);
    exit;
}

/* ==========================================================================
   2. DASHBOARD & UI
   ========================================================================== */
add_action('admin_menu', 'apex_sync_menu');
function apex_sync_menu() {
    add_menu_page('Apex Sync', 'Apex Sync', 'manage_options', 'apex-sync', 'apex_sync_dashboard_html', 'dashicons-update', 30);
}

function apex_sync_dashboard_html() {
    $total_live = wp_count_posts('property')->publish;
    $history = get_option('apex_sync_history', []);
    
    // Check for Lock
    $is_locked = get_transient('apex_sync_lock');
    ?>
    <style>
        .apex-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: auto; padding: 20px; }
        .apex-card { background: #fff; padding: 25px; border-radius: 8px; border: 1px solid #d1d5db; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .apex-btn { background: #2563eb; color: #fff; border: none; padding: 10px 18px; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; font-size: 13px; }
        .apex-btn:hover { background: #1d4ed8; color: #fff; }
        .apex-btn:disabled { background: #9ca3af; cursor: not-allowed; }
        .apex-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        .apex-table th, .apex-table td { text-align: left; padding: 12px; border-bottom: 1px solid #e5e7eb; }
        .apex-table th { background: #f9fafb; font-weight: 600; color: #374151; }
        .console { background: #111827; color: #34d399; font-family: monospace; padding: 15px; height: 200px; overflow-y: auto; border-radius: 6px; margin-top: 15px; font-size: 12px; }
        .progress-bar { width: 100%; height: 16px; background: #e5e7eb; border-radius: 8px; overflow: hidden; margin-top:15px; }
        .progress-fill { height: 100%; width: 0%; background: #059669; transition: width 0.2s ease; }
        .notice-locked { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; padding: 10px; border-radius: 6px; margin-bottom: 15px; }
    </style>

    <div class="apex-wrap">
        <div class="apex-card">
            <h1>Apex Sync Dashboard</h1>
            <p>Secure Sync v2.2 - Daily Auto-Sync at 2:00 AM</p>
            <?php if($is_locked): ?>
                <div class="notice-locked">🔒 <strong>Sync in Progress:</strong> A background sync is currently running. Manual start is disabled.</div>
            <?php endif; ?>
        </div>

        <div class="apex-card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h2 style="margin:0 0 5px 0;">Sync Operations</h2>
                    <p style="margin:0; color:#6b7280; font-size:13px;">Current Live Properties: <b><?php echo $total_live; ?></b></p>
                </div>
                <div style="display:flex; gap:10px;">
                    <button id="start-btn" class="apex-btn" <?php echo $is_locked ? 'disabled' : ''; ?>>Start Sync</button>
                </div>
            </div>
            
            <div id="progress-container" style="display:none;">
                <div class="progress-bar"><div id="p-fill" class="progress-fill"></div></div>
                <div style="margin-top: 5px; font-size: 12px; color: #666;" id="status-text">Processing...</div>
            </div>
            
            <div class="console" id="console"><div>> System Ready.</div></div>
        </div>

        <div class="apex-card">
            <h2>Sync History Logs</h2>
            <table class="apex-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Duration</th>
                        <th>New</th>
                        <th>Updated</th>
                        <th>Unchanged</th>
                        <th>Deleted</th>
                        <th>Total</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history) || !is_array($history)) : ?>
                        <tr><td colspan="8" style="text-align:center; color:#999;">No logs found yet. Run a sync to generate data.</td></tr>
                    <?php else : 
                        $history = array_reverse($history, true);
                        foreach ($history as $key => $log) : 
                            $start_time = isset($log['start_time']) ? date('Y-m-d H:i', $log['start_time']) : 'Unknown';
                            $duration   = isset($log['duration']) ? round($log['duration'] / 60, 2) . ' mins' : '—';
                            $added      = isset($log['stats']['added']) ? $log['stats']['added'] : 0;
                            $updated    = isset($log['stats']['updated']) ? $log['stats']['updated'] : 0;
                            $unchanged  = isset($log['stats']['unchanged']) ? $log['stats']['unchanged'] : 0;
                            $deleted    = isset($log['stats']['deleted']) ? $log['stats']['deleted'] : 0;
                            $total_feed = isset($log['total_feed']) ? $log['total_feed'] : 0;
                            
                            // Generate CSV Nonce
                            $csv_url = wp_nonce_url(
                                admin_url('admin-post.php?action=apex_download_log&log_id=' . $key),
                                'apex_download_log_action',
                                'nonce'
                            );
                    ?>
                        <tr>
                            <td><?php echo esc_html($start_time); ?></td>
                            <td><?php echo esc_html($duration); ?></td>
                            <td style="color:green; font-weight:bold;"><?php echo esc_html($added); ?></td>
                            <td style="color:blue; font-weight:bold;"><?php echo esc_html($updated); ?></td>
                            <td style="color:gray; font-weight:bold;"><?php echo esc_html($unchanged); ?></td>
                            <td style="color:red; font-weight:bold;"><?php echo esc_html($deleted); ?></td>
                            <td><?php echo esc_html($total_feed); ?></td>
                            <td><a href="<?php echo esc_url($csv_url); ?>" class="apex-btn" style="padding: 4px 10px; font-size: 11px;">CSV</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        let total = 0;
        let processed = 0;
        // SECURITY: Pass Nonce to JS
        const securityNonce = '<?php echo wp_create_nonce("apex_sync_secure_nonce"); ?>';

        function log(msg) {
            let time = new Date().toLocaleTimeString();
            $('#console').append(`<div>[${time}] ${msg}</div>`);
            $('#console').scrollTop($('#console')[0].scrollHeight);
        }

        $('#start-btn').click(function() {
            if(!confirm("Start Sync?")) return;
            $(this).prop('disabled', true).text('Working...');
            $('#progress-container').show();
            $('#console').html('');
            
            log("📡 Initializing... Downloading XML.");
            
            $.post(ajaxurl, { 
                action: 'apex_queue_init',
                security: securityNonce 
            }, function(res) {
                if (!res.success) {
                    log("❌ INIT FAILED: " + (res.data || 'Unknown error'));
                    $('#start-btn').prop('disabled', false).text('Start Sync');
                    return;
                }
                total = res.data.total;
                processed = 0;
                log(`✅ Found ${total} properties. Starting Smart Sync...`);
                processNext(); 
            }).fail(function() {
                log("❌ Connection Error: Could not reach server.");
                $('#start-btn').prop('disabled', false).text('Start Sync');
            });
        });

        function processNext() {
            if (processed >= total) { runCleanup(); return; }

            $.post(ajaxurl, { 
                action: 'apex_turbo_step',
                security: securityNonce 
            }, function(res) {
                if (!res.success) {
                    if(res.data && res.data.status === 'done_all') { 
                        runCleanup(); 
                    } else {
                        log("⚠️ Error: " + (res.data || 'Unknown'));
                        processed++;
                        updateUI();
                        processNext();
                    }
                    return;
                }
                
                if (res.data.status === 'prop_done') {
                    log(res.data.msg);
                    processed++;
                    updateUI();
                    processNext(); 
                } 
                else if (res.data.status === 'done_all') {
                    runCleanup();
                }
                else {
                    processNext(); // Image batch
                }
            }).fail(function() {
                log("⚠️ Network timeout, retrying...");
                setTimeout(processNext, 2000);
            });
        }

        function updateUI() {
            let pct = Math.floor((processed / total) * 100);
            $('#p-fill').css('width', pct + '%');
            $('#status-text').text(`Processed ${processed} of ${total}`);
        }

        function runCleanup() {
            log("🧹 Finalizing & Creating Report...");
            $.post(ajaxurl, { 
                action: 'apex_queue_cleanup',
                security: securityNonce 
            }, function(res) {
                log("🏁 COMPLETED! Refresh page to see new log.");
                $('#status-text').text('Completed.');
                setTimeout(() => location.reload(), 2000);
            });
        }
    });
    </script>
    <?php
}

/* ==========================================================================
   3. SERVER-SIDE LOGIC (SECURED)
   ========================================================================== */

function apex_get_xml_path() {
    return wp_upload_dir()['basedir'] . '/apex_feed.xml';
}

// === AJAX HANDLERS (Now with CSRF Protection) ===

add_action('wp_ajax_apex_queue_init', function() {
    check_ajax_referer('apex_sync_secure_nonce', 'security'); // SECURITY CHECK
    
    // LOCK CHECK
    if (get_transient('apex_sync_lock')) {
        wp_send_json_error("Sync already in progress.");
    }
    set_transient('apex_sync_lock', true, 3600); // 1 Hour Lock

    @set_time_limit(300); 
    $url = 'https://apexhomesdxb.com/BayutProperties.xml';
    $file_path = apex_get_xml_path();

    // Fix: SSL Verify TRUE (Security Requirement)
    wp_remote_get('https://apexhomesdxb.com/generate-bayut-xml.php', [
        'timeout' => 60, 
        'blocking' => true,
        'sslverify' => true
    ]);

    // Secure Download
    $fp = fopen($file_path, 'w+');
    $ch = curl_init(str_replace(" ","%20",$url));
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_FILE, $fp); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // FIXED: Security Requirement
    curl_exec($ch);
    
    if (curl_errno($ch)) {
        wp_send_json_error('cURL Error: ' . curl_error($ch));
        curl_close($ch);
        fclose($fp);
        delete_transient('apex_sync_lock');
        exit;
    }
    
    curl_close($ch);
    fclose($fp);

    // Validate XML Size
    if (filesize($file_path) < 100) {
        delete_transient('apex_sync_lock');
        wp_send_json_error("Downloaded XML is empty or corrupt.");
    }

    $xml = simplexml_load_file($file_path, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) {
        delete_transient('apex_sync_lock');
        wp_send_json_error("Invalid XML structure.");
    }

    $queue = [];
    foreach ($xml->Property as $p) {
        $queue[] = sanitize_text_field((string)$p->Property_Ref_No); // Sanitize
    }

    update_option('apex_sync_queue', $queue); 
    update_option('apex_prop_pointer', 0);   
    update_option('apex_img_pointer', -1);   
    update_option('apex_active_refs', $queue); 
    
    // Init Log
    $current_log = [
        'start_time' => time(),
        'details' => [], 
        'stats' => ['added' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0]
    ];
    update_option('apex_current_run_log', $current_log);

    // Free memory
    unset($xml);
    gc_collect_cycles(); // Memory Management

    wp_send_json_success(['total' => count($queue)]);
});

add_action('wp_ajax_apex_turbo_step', function() {
    check_ajax_referer('apex_sync_secure_nonce', 'security'); // SECURITY CHECK
    $res = apex_perform_sync_step();
    if($res['success']) wp_send_json_success($res['data']);
    else wp_send_json_error($res['data']);
});

add_action('wp_ajax_apex_queue_cleanup', function() {
    check_ajax_referer('apex_sync_secure_nonce', 'security'); // SECURITY CHECK
    apex_finalize_sync_log();
    if (wp_doing_ajax()) wp_send_json_success();
});

add_action('wp_ajax_apex_clean_orphans', function() {
    check_ajax_referer('apex_sync_secure_nonce', 'security'); // SECURITY CHECK
    global $wpdb;
    $attachments = $wpdb->get_results("SELECT p.ID, p.post_parent FROM $wpdb->posts p INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id WHERE p.post_type = 'attachment' AND pm.meta_key = '_source_url' LIMIT 500");
    $deleted = 0;
    foreach ($attachments as $att) {
        if ($att->post_parent == 0 || !get_post($att->post_parent)) {
            wp_delete_attachment($att->ID, true);
            $deleted++;
        }
    }
    wp_send_json_success("Safe Cleanup Complete! Deleted $deleted orphan images.");
});

// --- CORE LOGIC (Shared) ---
function apex_perform_sync_step() {
    @ini_set('memory_limit', '512M');
    @set_time_limit(60); 

    $queue = get_option('apex_sync_queue', []);
    $prop_ptr = (int)get_option('apex_prop_pointer', 0);
    $img_ptr  = (int)get_option('apex_img_pointer', -1);

    if (!isset($queue[$prop_ptr])) return ['success' => true, 'data' => ['status' => 'done_all']]; 

    $target_ref = $queue[$prop_ptr];
    $xml = simplexml_load_file(apex_get_xml_path(), 'SimpleXMLElement', LIBXML_NOCDATA);
    $result = $xml->xpath("//Property[Property_Ref_No='$target_ref']");
    
    if (!$result) {
        update_option('apex_prop_pointer', $prop_ptr + 1);
        update_option('apex_img_pointer', -1);
        unset($xml); gc_collect_cycles();
        return ['success' => true, 'data' => ['status' => 'prop_done', 'msg' => "⚠️ Skipped $target_ref"]];
    }

    $prop = $result[0];
    $current_log = get_option('apex_current_run_log');

    // Gather Images
    $all_images = [];
    if (isset($prop->Images->Image)) { foreach ($prop->Images->Image as $u) $all_images[] = esc_url_raw((string)$u); }
    $images_str = implode(',', $all_images);

    // === PHASE A: DATA ===
    if ($img_ptr === -1) {
        // Prepare Date from XML (Update Logic)
        $raw_date = (string)$prop->Last_Updated;
        $xml_date = date('Y-m-d H:i:s', strtotime($raw_date));
        $xml_date_gmt = get_gmt_from_date($xml_date);

        // Include Date in Hash so updates trigger even if only date changed
        $raw_data = (string)$prop->Property_Title . (string)$prop->Price . (string)$prop->Property_Description . $images_str . $raw_date;
        $new_hash = md5($raw_data);
        
        $post_id = 0;
        $existing = get_posts(['post_type' => 'property', 'meta_key' => 'property_ref_no', 'meta_value' => $target_ref, 'posts_per_page' => 1, 'fields' => 'ids']);

        if ($existing) {
            $post_id = $existing[0];
            $old_hash = get_post_meta($post_id, '_apex_sync_hash', true);

            if ($new_hash === $old_hash) {
                $current_log['stats']['unchanged']++;
                update_option('apex_current_run_log', $current_log);
                update_option('apex_prop_pointer', $prop_ptr + 1);
                unset($xml); gc_collect_cycles();
                return ['success' => true, 'data' => ['status' => 'prop_done', 'msg' => "⏭️ Skipped (Unchanged): $target_ref"]];
            }

            // Update Post & Force Date
            wp_update_post([
                'ID' => $post_id, 
                'post_title' => sanitize_text_field((string)$prop->Property_Title),
                'post_date' => $xml_date,
                'post_date_gmt' => $xml_date_gmt,
                'post_modified' => $xml_date,
                'post_modified_gmt' => $xml_date_gmt
            ]);
            $current_log['stats']['updated']++;
            $msg = "🔄 Updated: $target_ref";
        } else {
            // Insert Post & Force Date
            $post_id = wp_insert_post([
                'post_title' => sanitize_text_field((string)$prop->Property_Title), 
                'post_type' => 'property', 
                'post_status' => 'publish',
                'post_date' => $xml_date,
                'post_date_gmt' => $xml_date_gmt,
                'post_modified' => $xml_date,
                'post_modified_gmt' => $xml_date_gmt
            ]);
            $current_log['stats']['added']++;
            $msg = "🆕 New: $target_ref";
        }

        update_post_meta($post_id, '_apex_sync_hash', $new_hash);
        $current_log['details'][] = ['ref' => $target_ref, 'status' => 'Processed', 'title' => sanitize_text_field((string)$prop->Property_Title), 'time' => date('H:i:s')];
        update_option('apex_current_run_log', $current_log);

        // Fields (Sanitized)
        update_field('property_ref_no', $target_ref, $post_id);
        update_field('property_price', (float)$prop->Price, $post_id);
        update_field('property_price_formatted', 'AED ' . number_format((float)$prop->Price), $post_id);
        update_field('property_bedrooms', sanitize_text_field((string)$prop->Bedrooms), $post_id);
        update_field('property_bathrooms', intval($prop->Bathrooms), $post_id);
        update_field('property_size', intval($prop->Property_Size), $post_id);
        update_field('property_furnished', sanitize_text_field((string)$prop->Furnished), $post_id);
        update_field('property_off_plan', sanitize_text_field((string)$prop->Off_Plan), $post_id);
        update_field('property_permit', sanitize_text_field((string)$prop->Permit_Number), $post_id);
        update_field('property_description', wp_kses_post(nl2br((string)$prop->Property_Description)), $post_id);

        wp_set_object_terms($post_id, sanitize_text_field((string)$prop->Property_Type), 'property_type', false);
        wp_set_object_terms($post_id, sanitize_text_field((string)$prop->Property_purpose), 'property_purpose', false);

        $features = [];
        if (isset($prop->Features->Feature)) { foreach ($prop->Features->Feature as $f) { $features[] = sanitize_text_field((string)$f); } }
        if(!empty($features)) wp_set_object_terms($post_id, array_unique($features), 'property_feature', false);

        $levels = array_filter([(string)$prop->City, (string)$prop->Locality, (string)$prop->Sub_Locality, (string)$prop->Tower_Name]);
        $parent_id = 0; $term_ids = [];
        foreach ($levels as $name) {
            $name = sanitize_text_field($name);
            $term = term_exists($name, 'property_location', $parent_id);
            if (!$term) $term = wp_insert_term($name, 'property_location', ['parent' => $parent_id]);
            if (!is_wp_error($term)) { $parent_id = $term['term_id']; $term_ids[] = (int)$parent_id; }
        }
        if(!empty($term_ids)) wp_set_object_terms($post_id, array_unique($term_ids), 'property_location', false);
        
        $agent_photo = esc_url_raw(trim((string)$prop->Listing_Agent_Photo));
        $aid = apex_sideload_image_optimized($agent_photo, $post_id, 'agent');
        update_field('agent_info', ['agent_name' => sanitize_text_field((string)$prop->Listing_Agent), 'agent_photo' => $aid, 'agent_phone' => sanitize_text_field((string)$prop->Listing_Agent_Phone), 'agent_email' => sanitize_email((string)$prop->Listing_Agent_Email)], $post_id);

        update_option('apex_img_pointer', 0);
        unset($xml); gc_collect_cycles();
        return ['success' => true, 'data' => ['status' => 'image_batch', 'msg' => $msg]];
    }

    // === PHASE B: IMAGES ===
    $post_id = get_posts(['post_type' => 'property', 'meta_key' => 'property_ref_no', 'meta_value' => $target_ref, 'posts_per_page' => 1, 'fields' => 'ids'])[0];

    $batch_size = 5;
    $processed_in_batch = 0;
    
    for ($i = 0; $i < $batch_size; $i++) {
        $current_index = $img_ptr + $i;
        
        if ($current_index >= count($all_images) || $current_index >= 20) {
            $attachments = get_children(['post_parent' => $post_id, 'post_type' => 'attachment', 'orderby' => 'ID', 'order' => 'ASC', 'fields' => 'ids']);
            $gallery_ids = [];
            foreach($attachments as $att_id) { 
                $title = get_the_title($att_id);
                if (strpos($title, 'agent') === false) $gallery_ids[] = $att_id; 
            }
            if(!empty($gallery_ids)) {
                set_post_thumbnail($post_id, $gallery_ids[0]);
                update_field('property_gallery', $gallery_ids, $post_id);
            }
            
            update_option('apex_prop_pointer', $prop_ptr + 1);
            update_option('apex_img_pointer', -1);
            unset($xml); gc_collect_cycles();
            return ['success' => true, 'data' => ['status' => 'prop_done', 'msg' => "✅ Finished: $target_ref"]];
        }
        apex_sideload_image_optimized($all_images[$current_index], $post_id, 'prop');
        $processed_in_batch++;
    }

    update_option('apex_img_pointer', $img_ptr + $processed_in_batch);
    unset($xml); gc_collect_cycles();
    return ['success' => true, 'data' => ['status' => 'image_batch', 'msg' => "⬇️ Processed images..."]];
}

function apex_finalize_sync_log() {
    @ini_set('memory_limit', '512M');
    @set_time_limit(300);

    $active_refs = get_option('apex_active_refs', []);
    $current_log = get_option('apex_current_run_log');

    // FIX 6: Efficient Cleanup (IDs only)
    $all_ids = get_posts([
        'post_type' => 'property',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);

    foreach ($all_ids as $pid) {
        $ref = get_post_meta($pid, 'property_ref_no', true);
        if (!in_array($ref, $active_refs)) {
            $current_log['stats']['deleted']++;
            $current_log['details'][] = ['ref' => $ref, 'status' => 'Deleted', 'title' => get_the_title($pid), 'time' => date('H:i:s')];
            $attachments = get_children(['post_parent' => $pid, 'post_type' => 'attachment', 'fields' => 'ids']);
            if($attachments) { 
                foreach($attachments as $att_id) { wp_delete_attachment($att_id, true); } 
            }
            wp_delete_post($pid, true);
        }
    }

    $current_log['end_time'] = time();
    $current_log['duration'] = $current_log['end_time'] - $current_log['start_time'];
    $current_log['total_feed'] = count($active_refs);
    
    $history = get_option('apex_sync_history', []);
    $history[] = $current_log;
    if (count($history) > 10) array_shift($history); 
    update_option('apex_sync_history', $history);
    delete_transient('apex_sync_lock'); // Unlock

    if (wp_doing_ajax()) wp_send_json_success();
}

function apex_sideload_image_optimized($url, $post_id, $prefix = '') {
    if (empty($url)) return false;
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_source_url' AND meta_value = %s LIMIT 1", $url));
    if ($exists) return $exists;
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $tmp = download_url($url);
    if (is_wp_error($tmp)) return false;
    $filename = sanitize_file_name($prefix . '-' . basename(strtok($url, '?')));
    if (!pathinfo($filename, PATHINFO_EXTENSION)) $filename .= '.jpg';
    $file_array = ['name' => $filename, 'tmp_name' => $tmp];
    $id = media_handle_sideload($file_array, $post_id);
    if (!is_wp_error($id)) { update_post_meta($id, '_source_url', $url); return $id; } 
    else { @unlink($tmp); return false; }
}

// FIX 1: SQL INJECTION & Search Filter
add_filter('posts_search', function($search, $wp_query) {
    global $wpdb;
    if (!is_admin() || !$wp_query->is_main_query() || $wp_query->get('post_type') !== 'property') return $search;
    $s = $wp_query->get('s');
    if (!empty($s)) {
        // SECURE SQL Construction
        $like = '%' . $wpdb->esc_like($s) . '%';
        $search = $wpdb->prepare(" AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR EXISTS (SELECT * FROM {$wpdb->postmeta} WHERE post_id = {$wpdb->posts}.ID AND meta_key = 'property_ref_no' AND meta_value LIKE %s))", $like, $like, $like);
    }
    return $search;
}, 10, 2);

// ADMIN COLUMNS
add_filter('manage_property_posts_columns', function($columns) {
    $new = [];
    foreach($columns as $key => $title) {
        $new[$key] = $title;
        if ($key === 'title') $new['ref_no'] = 'Ref No';
    }
    return $new;
});

add_action('manage_property_posts_custom_column', function($column, $post_id) {
    if ($column === 'ref_no') {
        $ref = get_post_meta($post_id, 'property_ref_no', true);
        echo $ref ? '<span style="font-weight:600; color:#2563eb;">' . esc_html($ref) . '</span>' : '—';
    }
}, 10, 2);

add_filter('manage_edit-property_sortable_columns', function($columns) {
    $columns['ref_no'] = 'property_ref_no';
    return $columns;
});

add_action('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'property') return;
    if ($query->get('orderby') === 'property_ref_no') {
        $query->set('meta_key', 'property_ref_no');
        $query->set('orderby', 'meta_value');
    }
});

// AUTO PILOT (2:00 AM)
register_activation_hook(__FILE__, 'apex_activate_cron');
function apex_activate_cron() {
    wp_clear_scheduled_hook('apex_daily_auto_sync');
    $time = strtotime('tomorrow 02:00');
    wp_schedule_event($time, 'daily', 'apex_daily_auto_sync');
}

add_action('apex_daily_auto_sync', function() {
    // Check Lock
    if (get_transient('apex_sync_lock')) return; 
    set_transient('apex_sync_lock', true, 3600);

    wp_remote_get('https://apexhomesdxb.com/generate-bayut-xml.php', ['timeout' => 120, 'blocking' => true, 'sslverify' => true]);
    $url = 'https://apexhomesdxb.com/BayutProperties.xml';
    $file_path = apex_get_xml_path();
    $fp = fopen($file_path, 'w+');
    $ch = curl_init(str_replace(" ","%20",$url));
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_FILE, $fp); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    $xml = simplexml_load_file($file_path, 'SimpleXMLElement', LIBXML_NOCDATA);
    if ($xml) {
        $queue = [];
        foreach ($xml->Property as $p) { $queue[] = sanitize_text_field((string)$p->Property_Ref_No); }
        update_option('apex_sync_queue', $queue); 
        update_option('apex_prop_pointer', 0);   
        update_option('apex_img_pointer', -1);
        update_option('apex_active_refs', $queue);
        $current_log = ['start_time' => time(), 'details' => [], 'stats' => ['added' => 0, 'updated' => 0, 'deleted' => 0, 'unchanged' => 0]];
        update_option('apex_current_run_log', $current_log);
        unset($xml); gc_collect_cycles();
        wp_schedule_single_event(time() + 10, 'apex_process_background_batch');
    } else {
        delete_transient('apex_sync_lock');
    }
});

// CRON BATCH PROCESSOR (Loop until done)
add_action('apex_process_background_batch', function() {
    $start_time = time();
    $max_time = 45; // Safety limit per batch
    
    while (time() - $start_time < $max_time) {
        // Direct call
        $res = apex_perform_sync_step();
        
        if (isset($res['data']['status']) && $res['data']['status'] === 'done_all') {
            apex_finalize_sync_log(); 
            return;
        }
    }
    
    // Reschedule self to continue
    wp_schedule_single_event(time() + 60, 'apex_process_background_batch');
});