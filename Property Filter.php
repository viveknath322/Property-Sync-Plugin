/* ==========================================================================
   APEX PROPERTY SEARCH SYSTEM v3.7 (Mobile UX Final Polish)
   - Height: 70dvh
   - Sticky Header & Footer inside Modal
   - Accordion Icons & Clean Closed State
   - Clear Button with Refresh Logic
   ========================================================================== */

// --- 1. REUSABLE FILTER LOGIC ---
if (!function_exists('apex_get_search_args')) {
    function apex_get_search_args() {
        $args = [
            'post_type'      => 'property',
            'post_status'    => 'publish',
            'tax_query'      => ['relation' => 'AND'],
            'meta_query'     => ['relation' => 'AND'],
            'fields'         => 'ids',
        ];

        if (!empty($_GET['f_purpose'])) {
            $args['tax_query'][] = ['taxonomy' => 'property_purpose', 'field' => 'slug', 'terms' => sanitize_text_field($_GET['f_purpose'])];
        }
        if (!empty($_GET['f_loc'])) {
            $loc_ids = array_map('absint', explode(',', $_GET['f_loc']));
            $args['tax_query'][] = ['taxonomy' => 'property_location', 'field' => 'term_id', 'terms' => $loc_ids, 'operator' => 'IN'];
        }
        if (!empty($_GET['f_type'])) {
            $args['tax_query'][] = ['taxonomy' => 'property_type', 'field' => 'slug', 'terms' => sanitize_text_field($_GET['f_type'])];
        }
        if (!empty($_GET['f_feat'])) {
            $feats = is_array($_GET['f_feat']) ? $_GET['f_feat'] : explode(',', $_GET['f_feat']);
            $feats = array_map('sanitize_text_field', $feats);
            $args['tax_query'][] = ['taxonomy' => 'property_feature', 'field' => 'slug', 'terms' => $feats, 'operator' => 'IN'];
        }
        if (!empty($_GET['f_bed'])) $args['meta_query'][] = ['key' => 'property_bedrooms', 'value' => sanitize_text_field($_GET['f_bed']), 'compare' => '='];
        if (!empty($_GET['f_bath'])) $args['meta_query'][] = ['key' => 'property_bathrooms', 'value' => sanitize_text_field($_GET['f_bath']), 'compare' => '>='];
        if (!empty($_GET['f_min_price'])) $args['meta_query'][] = ['key' => 'property_price', 'value' => absint($_GET['f_min_price']), 'compare' => '>=', 'type' => 'NUMERIC'];
        if (!empty($_GET['f_max_price'])) $args['meta_query'][] = ['key' => 'property_price', 'value' => absint($_GET['f_max_price']), 'compare' => '<=', 'type' => 'NUMERIC'];
        if (!empty($_GET['f_min_area'])) $args['meta_query'][] = ['key' => 'property_size', 'value' => absint($_GET['f_min_area']), 'compare' => '>=', 'type' => 'NUMERIC'];
        if (!empty($_GET['f_max_area'])) $args['meta_query'][] = ['key' => 'property_size', 'value' => absint($_GET['f_max_area']), 'compare' => '<=', 'type' => 'NUMERIC'];
        if (!empty($_GET['f_furn'])) $args['meta_query'][] = ['key' => 'property_furnished', 'value' => sanitize_text_field($_GET['f_furn']), 'compare' => 'LIKE'];

        return $args;
    }
}

// --- 2. MAIN SEARCH BAR SHORTCODE [apex_search_bar] ---
if (!function_exists('apex_render_search_system')) {
    add_shortcode('apex_search_bar', 'apex_render_search_system');

    function apex_render_search_system() {
        $conf_beds = ['1','2','3','4','5','6','7+'];
        $conf_baths = ['1','2','3','4','5','6','7+'];

        // Caching
        $purposes = get_transient('apex_purposes_cache');
        if (false === $purposes) {
            $purposes = get_terms(['taxonomy' => 'property_purpose', 'hide_empty' => true]);
            set_transient('apex_purposes_cache', $purposes, 12 * HOUR_IN_SECONDS);
        }
        $types = get_transient('apex_types_cache');
        if (false === $types) {
            $types = get_terms(['taxonomy' => 'property_type', 'hide_empty' => true]);
            set_transient('apex_types_cache', $types, 12 * HOUR_IN_SECONDS);
        }
        $features = get_transient('apex_features_cache');
        if (false === $features) {
            $features = get_terms(['taxonomy' => 'property_feature', 'hide_empty' => true]);
            set_transient('apex_features_cache', $features, 12 * HOUR_IN_SECONDS);
        }
        
        $s_loc_ids = isset($_GET['f_loc']) ? explode(',', sanitize_text_field($_GET['f_loc'])) : [];
        $loc_objects = [];
        if(!empty($s_loc_ids)) {
            $terms = get_terms(['taxonomy'=>'property_location', 'include'=>$s_loc_ids, 'hide_empty'=>false]);
            if(!is_wp_error($terms)) { foreach($terms as $t) $loc_objects[] = ['id' => $t->term_id, 'name' => $t->name]; }
        }

        $p = [
            'purpose' => isset($_GET['f_purpose']) ? sanitize_text_field($_GET['f_purpose']) : '',
            'type' => isset($_GET['f_type']) ? sanitize_text_field($_GET['f_type']) : '',
            'bed'  => isset($_GET['f_bed']) ? sanitize_text_field($_GET['f_bed']) : '',
            'bath' => isset($_GET['f_bath']) ? sanitize_text_field($_GET['f_bath']) : '',
            'min'  => isset($_GET['f_min_price']) ? absint($_GET['f_min_price']) : '',
            'max'  => isset($_GET['f_max_price']) ? absint($_GET['f_max_price']) : '',
            'furn' => isset($_GET['f_furn']) ? sanitize_text_field($_GET['f_furn']) : '',
            'min_a'=> isset($_GET['f_min_area']) ? absint($_GET['f_min_area']) : '',
            'max_a'=> isset($_GET['f_max_area']) ? absint($_GET['f_max_area']) : '',
            'sort' => isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : ''
        ];

        ob_start();
        ?>
        <style>
            :root { --apx-gold: #f5c45e; --apx-dark: #1f2937; --apx-gray: #f9fafb; --apx-border: #e5e7eb; }
            .apex-wrap { position: relative; z-index: 800; font-family: "Satoshi", sans-serif; max-width: auto; margin: 0 auto; color: var(--apx-dark); }
            
            /* DESKTOP BASE */
            .apex-form-container {
                display: block; background: #fff; border: 1px solid #e5e7eb; 
                border-radius: 100px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); 
                padding: 0 10px; position: relative; transition: all 0.3s ease;
            }
            .apex-bar { display: flex; align-items: center; height: 72px; width: 100%; }
            .apex-item { 
                flex: 1; padding: 0 24px; position: relative; cursor: pointer;
                height: 100%; display: flex; flex-direction: column; justify-content: center;
                border-radius: 40px; transition: 0.2s; min-width: 0;
            }
            .apex-item:hover { background: #f9fafb; }
            .apex-item::after { content: ''; position: absolute; right: 0; top: 20%; height: 60%; width: 1px; background: #e5e7eb; }
            .apex-item:last-child::after, .apex-item:nth-last-child(2)::after { display: none; }

            /* Purpose Smaller */
            #item-purpose { flex: 0.6; min-width: 110px; }

            .apex-lbl { font-size: 11px; font-weight: 800; color: #6b7280; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px; font-family: "Satoshi", sans-serif; }
            .apex-val { font-size: 14px; font-weight: 600; color: var(--apx-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2; font-family: "Satoshi", sans-serif; }

            .apex-btn-wrap { padding-left: 10px; flex: 0 0 auto; display: flex; align-items: center; gap: 8px; }
            .apex-btn {
                background: var(--apx-gold); color: #000; border: none; 
                padding: 0 36px; border-radius: 100px; font-weight: 600; font-size: 16px; 
                cursor: pointer; height: 52px; transition: 0.2s; display: flex; align-items: center; justify-content: center;
                font-family: "Satoshi", sans-serif;
            }
            .apex-btn:hover { background: #eab308; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(234, 179, 8, 0.3); }

            /* CLEAR BUTTON (Desktop) */
            .apex-btn-clear {
                background: #f9fafb; color: #6b7280; border: 1px solid #e5e7eb;
                width: 52px; height: 52px; border-radius: 50%; font-weight: 600;
                cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center;
                position: relative;
            }
            .apex-btn-clear:hover { background: #fee2e2; color: #ef4444; border-color: #fca5a5; }
            /* Tooltip */
            .apex-btn-clear:hover::after {
                content: "Clear all filters"; position: absolute; bottom: 110%; left: 50%; transform: translateX(-50%);
                background: #1f2937; color: #fff; padding: 6px 10px; font-size: 12px; border-radius: 6px; 
                white-space: nowrap; pointer-events: none; opacity: 0; animation: fadeIn 0.2s forwards;
            }
            .apex-btn-clear svg { width: 20px; height: 20px; stroke-width: 2; }

            /* DROPDOWNS */
            .apex-drop {
                position: absolute; top: 85px; left: 0; background: #fff; 
                padding: 24px; border-radius: 20px; width: 380px;
                box-shadow: 0 20px 50px rgba(0,0,0,0.15); border: 1px solid #f3f4f6;
                opacity: 0; visibility: hidden; transform: translateY(10px); transition: 0.2s; z-index: 1000; pointer-events: none;
            }
            .apex-drop.show { opacity: 1; visibility: visible; transform: translateY(0); pointer-events: auto; }
            .apex-drop.wide { width: 750px; left: auto; right: 0; }

            /* UI COMPONENTS */
            .loc-wrapper { display: flex; align-items: center; gap: 4px; height: 32px; width: 100%; overflow: hidden; position: relative; }
            .loc-pill { 
                background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; 
                font-size: 12px; padding: 1px 8px; border-radius: 4px; flex-shrink: 0; 
                white-space: nowrap; display: flex; align-items: center; gap: 5px; font-weight: 600; height: 24px;
                font-family: "Satoshi", sans-serif;
            }
            .loc-pill span { cursor: pointer; opacity: 0.6; display: flex; align-items: center; }
            .loc-pill span:hover { opacity: 1; }
            .loc-more-pill {
                background: var(--apx-gray); border: 1px solid #d1d5db; color: #374151;
                font-size: 11px; padding: 1px 8px; border-radius: 4px; flex-shrink: 0; height: 24px;
                cursor: pointer; font-weight: 700; display: flex; align-items: center;
                font-family: "Satoshi", sans-serif;
            }
            .loc-direct-inp {
                border: none; outline: none; font-size: 14px; font-weight: 600; 
                color: #1f2937; background: transparent; padding: 0; margin: 0; 
                min-width: 60px; flex-grow: 1; height: 100%; font-family: "Satoshi", sans-serif;
            }
            .loc-direct-inp::placeholder { color: #9ca3af; font-weight: 400; }

            .loc-results-pop {
                position: absolute; top: 100%; left: 0; width: 100%; background: #fff;
                border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                max-height: 250px; overflow-y: auto; display: none; z-index: 1001; margin-top: 10px;
            }
            .loc-item { padding: 12px 15px; font-size: 14px; cursor: pointer; border-bottom: 1px solid #f9fafb; color: #374151; font-family: "Satoshi", sans-serif; }
            .loc-item:hover { background: #fffbeb; color: #b45309; }
            .loc-overflow-pop {
                position: absolute; top: 100%; left: 0; background: #fff;
                border: 1px solid #e5e7eb; border-radius: 12px; padding: 15px; width: 320px; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: none; z-index: 1002; margin-top: 10px;
            }
            .loc-overflow-pop.show { display: block; animation: fadeIn 0.2s ease; }
            .loc-overflow-grid { display: flex; flex-wrap: wrap; gap: 8px; }

            .apx-title { font-size: 13px; font-weight: 700; margin-bottom: 12px; display: block; color: #374151; font-family: "Satoshi", sans-serif; }
            .apx-pills { display: flex; flex-wrap: wrap; gap: 8px; }
            .apx-pill {
                border: 1px solid #e5e7eb; padding: 8px 20px; border-radius: 30px; 
                font-size: 13px; font-weight: 500; cursor: pointer; transition: 0.2s; background: #fff; color: #4b5563;
                font-family: "Satoshi", sans-serif;
            }
            .apx-pill:hover { border-color: #d1d5db; background: #f9fafb; }
            .apx-pill.active { background: var(--apx-gold); color: #000; border-color: var(--apx-gold); font-weight: 700; }
            .apx-inp { 
                width: 100%; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; 
                font-size: 14px; outline: none; transition: 0.2s; background: #fff; color: #1f2937;
                font-family: "Satoshi", sans-serif;
            }
            .apx-inp:focus { border-color: var(--apx-gold); box-shadow: 0 0 0 3px rgba(245, 196, 94, 0.2); }
            .apx-grid { display: grid; grid-template-columns: 280px 1fr; gap: 40px; }
            .apx-checks { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; max-height: 300px; overflow-y: auto; padding-right: 10px; }
            .apx-check { display: flex; align-items: center; gap: 10px; font-size: 14px; color: #4b5563; cursor: pointer; font-family: "Satoshi", sans-serif; }
            .apx-check input { accent-color: var(--apx-gold); width: 18px; height: 18px; }

            .mob-trigger-btn, .mob-close-head { display: none; }

            /* ================= MOBILE OPTIMIZATION ================= */
            @media (max-width: 900px) {
                
                /* FIX 1: NO ZOOM on inputs (16px rule) */
                input, select, textarea { font-size: 16px !important; }

                .mob-trigger-btn {
                    display: flex; align-items: center; justify-content: center; gap: 10px;
                    background: #fff; border: 1px solid #e5e7eb; border-radius: 12px;
                    padding: 14px; width: 100%; box-shadow: 0 4px 15px rgba(0,0,0,0.06);
                    font-weight: 700; font-size: 15px; color: var(--apx-dark); cursor: pointer;
                    font-family: "Satoshi", sans-serif;
                }

                .apex-form-container {
                    display: none; 
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(0,0,0,0.5); z-index: 9999; 
                    align-items: flex-end; /* Sheet Alignment */
                    border-radius: 0; padding: 0; border: none;
                }
                .apex-form-container.open { display: flex; }

                /* FIX 2: 70dvh Height & Scrolling Container */
                .apex-bar { 
                    flex-direction: column; 
                    height: 70dvh; /* User Request */
                    width: 100%; background: #fff; 
                    border-radius: 20px 20px 0 0; padding: 0; 
                    display: flex; 
                    overflow-y: auto; 
                    -webkit-overflow-scrolling: touch;
                    animation: slideUp 0.3s ease;
                    position: relative;
                }

                /* FIX 3: Sticky Header */
                .mob-close-head {
                    display: flex; justify-content: space-between; align-items: center;
                    padding: 12px; border-bottom: 1px solid #e5e7eb; background: #fff;
                    position: sticky; top: 0; z-index: 100;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.02);
                    width: 100%;

                }

                /* Accordion Styling */
                .apex-item { 
                    padding: 18px 20px; border-bottom: 1px solid #f5c45e4a; border-radius: 0; height: auto;
                    display: block; position: relative; width: 100%;
                }
                .apex-item::after { display: none; }

                /* FIX 4: Accordion Icon (Chevron) */
                .apex-item::before {
                    content: ''; position: absolute; right: 20px; top: 22px;
                    width: 8px; height: 8px;
                    border-right: 2px solid #9ca3af; border-bottom: 2px solid #9ca3af;
                    transform: rotate(45deg); transition: 0.2s; pointer-events: none;
                }
                /* Rotate when active */
                .apex-item.active::before { transform: rotate(-135deg); top: 26px; border-color: var(--apx-gold); }

                #item-purpose { flex: auto; width: 100%; } 

                .apex-drop {
                    position: static; opacity: 1; visibility: visible; transform: none;
                    width: 100% !important; box-shadow: none; border: none; padding: 15px 0 0 0; display: none; 
                }
                .apex-drop.show { display: block; }
                .apex-drop.wide, .apex-drop.loc-drop { width: 100%; }

                .apx-grid, .apx-checks { grid-template-columns: 1fr; gap: 20px; }
                
                /* FIX 5: Sticky Footer */
                .apex-btn-wrap { 
                    padding: 12px; border-top: 1px solid #eee; margin-top: auto; 
                    position: sticky; bottom: 0; background: #fff; z-index: 100;
                    display: grid; grid-template-columns: 50px 1fr; /* Space for clear button */
                    box-shadow: 0 -4px 10px rgba(0,0,0,0.03);
                    width: 100%;
                }
                .apex-btn { width: 100%; }
                
                /* Mobile Clear Button */
                .apex-btn-clear { 
                    display: flex; width: 45px; height: 52px; 
                    background: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb;
                }
                
                .loc-results-pop { position: static; box-shadow: none; border: 1px solid #eee; margin-top: 10px; max-height: none; }
                .loc-overflow-pop { display: none !important; }
            }

            @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        </style>

        <div class="apex-wrap">
            <div class="mob-trigger-btn" onclick="openMobileSearch()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                Search Properties
            </div>

            <div class="apex-form-container" id="apex-main-cont">
                <div class="apex-bar">
                    <div class="mob-close-head">
                        <div style="font-weight:800; font-size:18px; font-family:'Satoshi', sans-serif;">Filters</div>
                        <button onclick="closeMobileSearch()" style="background:none;border:none;font-size:24px;">&times;</button>
                    </div>

                    <form id="apex-form" onsubmit="return false;" style="display:contents;">
                        
                        <div class="apex-item" id="item-purpose" onclick="handleItemClick('purpose', this)">
                            <div class="apex-lbl">Purpose</div>
                            <div class="apex-val" id="disp-purpose"><?php echo $p['purpose'] ? ucwords($p['purpose']) : 'Any'; ?></div>
                            <div class="apex-drop" id="drop-purpose">
                                <div class="apx-title">Property Purpose</div>
                                <div class="apx-pills">
                                    <div class="apx-pill <?php echo !$p['purpose'] ? 'active' : ''; ?>" onclick="setVal('purpose', '', 'Any', event)">Any</div>
                                    <?php if(!empty($purposes) && !is_wp_error($purposes)): foreach($purposes as $pur): ?>
                                        <div class="apx-pill <?php echo $p['purpose'] == $pur->slug ? 'active' : ''; ?>" 
                                             onclick="setVal('purpose', '<?php echo $pur->slug; ?>', '<?php echo $pur->name; ?>', event)">
                                             <?php echo $pur->name; ?>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="apex-item" id="item-loc" style="flex: 2.2; cursor: text;" onclick="handleItemClick('loc', this)">
                            <div class="apex-lbl">Location</div>
                            <div class="loc-wrapper" id="loc-display">
                                <input type="text" id="loc-input" class="loc-direct-inp" placeholder="City, community..." autocomplete="off">
                            </div>
                            <div class="loc-results-pop" id="loc-results"></div>
                            <div class="loc-overflow-pop" id="loc-overflow-win">
                                <div class="apx-title" style="margin-bottom:8px;">Selected Locations</div>
                                <div class="loc-overflow-grid" id="loc-overflow-content"></div>
                            </div>
                        </div>

                        <div class="apex-item" id="item-type" onclick="handleItemClick('type', this)">
                            <div class="apex-lbl">Property Type</div>
                            <div class="apex-val" id="disp-type"><?php echo $p['type'] ? ucwords(str_replace('-', ' ', $p['type'])) : 'All Types'; ?></div>
                            <div class="apex-drop" id="drop-type">
                                <div class="apx-title">Property Type</div>
                                <div class="apx-pills">
                                    <div class="apx-pill <?php echo !$p['type'] ? 'active' : ''; ?>" onclick="setVal('type', '', 'All Types', event)">All</div>
                                    <?php if(!empty($types) && !is_wp_error($types)): foreach($types as $t): ?>
                                        <div class="apx-pill <?php echo $p['type'] == $t->slug ? 'active' : ''; ?>" 
                                             onclick="setVal('type', '<?php echo $t->slug; ?>', '<?php echo $t->name; ?>', event)">
                                             <?php echo $t->name; ?>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="apex-item" id="item-bb" onclick="handleItemClick('bb', this)">
                            <div class="apex-lbl">Beds & Baths</div>
                            <div class="apex-val" id="disp-bb">
                                <?php echo $p['bed'] ? $p['bed'] . ' Bed' : 'Any Bed'; ?>, <?php echo $p['bath'] ? $p['bath'] . ' Bath' : 'Any Bath'; ?>
                            </div>
                            <div class="apex-drop" id="drop-bb">
                                <div class="apx-title">Bedrooms</div>
                                <div class="apx-pills" id="pills-bed">
                                    <div class="apx-pill <?php echo $p['bed'] == 'Studio' ? 'active' : ''; ?>" onclick="setPill(this, 'bed', 'Studio', event)">Studio</div>
                                    <?php foreach($conf_beds as $b) echo "<div class='apx-pill ".($p['bed']==$b?'active':'')."' onclick=\"setPill(this,'bed','$b', event)\">$b</div>"; ?>
                                </div>
                                <hr style="margin:20px 0; border:0; border-top:1px solid #f3f4f6;">
                                <div class="apx-title">Bathrooms</div>
                                <div class="apx-pills" id="pills-bath">
                                    <?php foreach($conf_baths as $b) echo "<div class='apx-pill ".($p['bath']==$b?'active':'')."' onclick=\"setPill(this,'bath','$b', event)\">$b</div>"; ?>
                                </div>
                            </div>
                        </div>

                        <div class="apex-item" id="item-more" onclick="handleItemClick('more', this)">
                            <div class="apex-lbl">Filters</div>
                            <div class="apex-val">Price, Area, Amenities...</div>
                            <div class="apex-drop wide" id="drop-more">
                                <div class="apx-grid">
                                    <div>
                                        <div class="apx-title">Price Range (AED)</div>
                                        <div style="display:flex; gap:10px; margin-bottom:20px;">
                                            <input type="number" id="inp-min" class="apx-inp" placeholder="Min" value="<?php echo $p['min'] ?: ''; ?>" onclick="event.stopPropagation()">
                                            <input type="number" id="inp-max" class="apx-inp" placeholder="Max" value="<?php echo $p['max'] ?: ''; ?>" onclick="event.stopPropagation()">
                                        </div>
                                        <div class="apx-title">Furnishing</div>
                                        <div class="apx-pills" id="pills-furn" style="margin-bottom:20px;">
                                            <div class="apx-pill <?php echo $p['furn'] == 'Yes' ? 'active' : ''; ?>" onclick="setPill(this, 'furn', 'Yes', event)">Furnished</div>
                                            <div class="apx-pill <?php echo $p['furn'] == 'No' ? 'active' : ''; ?>" onclick="setPill(this, 'furn', 'No', event)">Unfurnished</div>
                                        </div>
                                        <div class="apx-title">Property Size (Sqft)</div>
                                        <div style="display:flex; gap:10px;">
                                            <input type="number" id="inp-min-area" class="apx-inp" placeholder="Min" value="<?php echo $p['min_a'] ?: ''; ?>" onclick="event.stopPropagation()">
                                            <input type="number" id="inp-max-area" class="apx-inp" placeholder="Max" value="<?php echo $p['max_a'] ?: ''; ?>" onclick="event.stopPropagation()">
                                        </div>
                                    </div>
                                    <div style="border-left:1px solid #f3f4f6; padding-left:30px;" onclick="event.stopPropagation()">
                                        <div class="apx-title">Amenities</div>
                                        <div class="apx-checks">
                                            <?php foreach($features as $f): ?>
                                                <label class="apx-check"><input type="checkbox" name="feat" value="<?php echo $f->slug; ?>"> <?php echo $f->name; ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="apex-btn-wrap">
                            <button class="apex-btn-clear" onclick="clearSearch(event)" title="Clear all filters">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                            <button class="apex-btn" onclick="runSearch()">Show Results</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        (function() {
            // STATE
            const state = {
                locs: <?php echo json_encode($loc_objects); ?>, 
                purpose: '<?php echo $p['purpose']; ?>',
                type: '<?php echo $p['type']; ?>',
                bed: '<?php echo $p['bed']; ?>',
                bath: '<?php echo $p['bath']; ?>',
                furn: '<?php echo $p['furn']; ?>',
                sort: '<?php echo $p['sort']; ?>'
            };
            const ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
            const nonce = '<?php echo wp_create_nonce("apex_search_nonce"); ?>';
            let isMobile = window.innerWidth <= 900;

            window.openMobileSearch = function() { document.getElementById('apex-main-cont').classList.add('open'); document.body.style.overflow = 'hidden'; };
            window.closeMobileSearch = function() { document.getElementById('apex-main-cont').classList.remove('open'); document.body.style.overflow = 'auto'; };

            // HANDLE CLICK (with Icon Rotation)
            window.handleItemClick = function(id, el) {
                if(id === 'loc') { document.getElementById('loc-input').focus(); if(isMobile) return; }
                const drop = document.getElementById('drop-' + id);
                if(!drop) return;
                
                if(isMobile) { 
                    drop.classList.toggle('show'); 
                    if(el) el.classList.toggle('active'); // Rotate chevron
                } 
                else {
                    const isVis = drop.classList.contains('show');
                    document.querySelectorAll('.apex-drop').forEach(d => d.classList.remove('show'));
                    if(!isVis) drop.classList.add('show');
                    document.getElementById('loc-overflow-win').classList.remove('show');
                }
            };

            document.addEventListener('click', e => {
                if(window.innerWidth > 900) {
                    if(!e.target.closest('.apex-bar') && !e.target.closest('.loc-overflow-pop')) {
                        document.querySelectorAll('.apex-drop').forEach(d => d.classList.remove('show'));
                        document.getElementById('loc-results').style.display = 'none';
                        document.getElementById('loc-overflow-win').classList.remove('show');
                    }
                }
            });

            // Location Logic
            const locWrapper = document.getElementById('loc-display');
            const locInp = document.getElementById('loc-input');
            const locRes = document.getElementById('loc-results');
            const locOverWin = document.getElementById('loc-overflow-win');
            const locOverContent = document.getElementById('loc-overflow-content');

            function renderLocs() {
                locWrapper.querySelectorAll('.loc-pill, .loc-more-pill').forEach(e => e.remove());
                locInp.style.display = 'block';
                if(state.locs.length === 0) { locInp.placeholder = "City, community or building..."; return; }
                locInp.placeholder = "";

                if(window.innerWidth <= 900) {
                    state.locs.forEach(obj => {
                        const pill = createPill(obj); locWrapper.insertBefore(pill, locInp);
                    });
                    locWrapper.style.height = 'auto'; locWrapper.style.flexWrap = 'wrap';
                } else {
                    locWrapper.style.height = '32px'; locWrapper.style.flexWrap = 'nowrap';
                    const pills = state.locs.map(createPill);
                    let visibleCount = 0;
                    for(let i=0; i<pills.length; i++) {
                        locWrapper.insertBefore(pills[i], locInp);
                        if(locWrapper.scrollWidth > locWrapper.clientWidth) { pills[i].remove(); break; }
                        visibleCount++;
                    }
                    if(visibleCount < state.locs.length) {
                        const morePill = document.createElement('div');
                        morePill.className = 'loc-more-pill';
                        morePill.innerHTML = `& ${state.locs.length - visibleCount} more`;
                        morePill.onclick = toggleOverflow;
                        locWrapper.insertBefore(morePill, locInp);
                    }
                    const hiddenLocs = state.locs.slice(visibleCount);
                    if(hiddenLocs.length > 0) {
                        locOverContent.innerHTML = hiddenLocs.map(obj => `<div class="loc-pill">${obj.name} <span onclick="remLoc('${obj.id}')">&times;</span></div>`).join('');
                    } else {
                        locOverWin.classList.remove('show');
                    }
                }
            }

            function createPill(obj) {
                const pill = document.createElement('div'); pill.className = 'loc-pill';
                pill.innerHTML = `${obj.name} <span onclick="remLoc('${obj.id}'); event.stopPropagation();">&times;</span>`;
                return pill;
            }

            window.addEventListener('resize', () => { isMobile = window.innerWidth <= 900; renderLocs(); });
            setTimeout(renderLocs, 100);

            locInp.addEventListener('input', e => {
                const term = e.target.value;
                if(term.length < 2) { locRes.style.display='none'; return; }
                let fd = new FormData(); fd.append('action','apex_loc_search'); fd.append('term',term); fd.append('nonce', nonce);
                fetch(ajaxUrl, {method:'POST', body:fd}).then(r=>r.json()).then(r => {
                    if(r.success) {
                        if (r.data && r.data.length > 0) {
                            locRes.innerHTML = r.data.map(l => `<div class="loc-item" onclick="addLoc('${l.id}','${l.name}', event)">${l.name}</div>`).join('');
                        } else {
                            locRes.innerHTML = `<div class="loc-item" style="cursor:default; color:#9ca3af; pointer-events:none; font-family:'Satoshi',sans-serif;">No location found</div>`;
                        }
                        locRes.style.display = 'block';
                    }
                });
            });

            locInp.addEventListener('click', e => e.stopPropagation());

            window.addLoc = function(id, name, e) {
                if(e) e.stopPropagation();
                if(state.locs.length >= 10) return alert('Max 10 locations');
                if(!state.locs.some(l => l.id == id)) { state.locs.push({id: id, name: name}); renderLocs(); }
                locInp.value = ''; locRes.style.display = 'none'; locInp.focus();
            };

            window.remLoc = function(id) { state.locs = state.locs.filter(l => l.id != id); renderLocs(); };

            window.toggleOverflow = function(e) {
                e.stopPropagation(); if(window.innerWidth <= 900) return; 
                locOverWin.classList.toggle('show'); document.querySelectorAll('.apex-drop').forEach(d => d.classList.remove('show'));
            };

            window.setVal = function(key, val, name, e) {
                if(e) e.stopPropagation(); state[key] = val;
                
                if(key === 'purpose' || key === 'type') {
                    const dispId = (key === 'purpose') ? 'disp-purpose' : 'disp-type';
                    const dropId = (key === 'purpose') ? 'drop-purpose' : 'drop-type';
                    
                    document.getElementById(dispId).innerText = name;
                    document.querySelectorAll('#' + dropId + ' .apx-pill').forEach(p => p.classList.remove('active'));
                    e.target.classList.add('active');
                    if(!isMobile) document.getElementById(dropId).classList.remove('show');
                }
            };

            window.setPill = function(el, key, val, e) {
                if(e) e.stopPropagation();
                Array.from(el.parentElement.children).forEach(c => c.classList.remove('active'));
                if(state[key] === val) state[key] = ''; 
                else { state[key] = val; el.classList.add('active'); }
                document.getElementById('disp-bb').innerText = `${state.bed ? state.bed+' Bed' : 'Any Bed'}, ${state.bath ? state.bath+' Bath' : 'Any Bath'}`;
            };

            window.clearSearch = function(e) {
                if(e) e.preventDefault();
                const baseUrl = window.location.href.split('?')[0];
                window.location.href = baseUrl;
            };

            window.runSearch = function() {
                const params = new URLSearchParams();
                if(state.purpose) params.append('f_purpose', state.purpose);
                if(state.locs.length) params.append('f_loc', state.locs.map(l => l.id).join(','));
                if(state.type) params.append('f_type', state.type);
                if(state.bed) params.append('f_bed', state.bed);
                if(state.bath) params.append('f_bath', state.bath);
                if(state.furn) params.append('f_furn', state.furn);
                if(state.sort) params.append('sort', state.sort);

                const min = document.getElementById('inp-min').value;
                const max = document.getElementById('inp-max').value;
                const minA = document.getElementById('inp-min-area').value;
                const maxA = document.getElementById('inp-max-area').value;
                
                if(min) params.append('f_min_price', min);
                if(max) params.append('f_max_price', max);
                if(minA) params.append('f_min_area', minA);
                if(maxA) params.append('f_max_area', maxA);
                
                const feats = Array.from(document.querySelectorAll('input[name="feat"]:checked')).map(c=>c.value);
                if(feats.length) feats.forEach(f => params.append('f_feat[]', f));

                window.location.href = "<?php echo get_post_type_archive_link('property'); ?>?" + params.toString();
            };
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

// --- 3. AJAX LOC HANDLER (Cached & Secured) ---
if (!function_exists('apex_loc_search_fn')) {
    add_action('wp_ajax_apex_loc_search', 'apex_loc_search_fn');
    add_action('wp_ajax_nopriv_apex_loc_search', 'apex_loc_search_fn');
    function apex_loc_search_fn() {
        check_ajax_referer('apex_search_nonce', 'nonce');
        
        $term = sanitize_text_field($_POST['term']);
        $cache_key = 'apex_loc_' . md5($term);
        $out = get_transient($cache_key);

        if (false === $out) {
            $terms = get_terms(['taxonomy'=>'property_location', 'name__like'=>$term, 'number'=>8, 'hide_empty'=>false]);
            $out = []; 
            if (!is_wp_error($terms)) {
                foreach($terms as $t) $out[] = ['id'=>$t->term_id, 'name'=>$t->name];
            }
            set_transient($cache_key, $out, HOUR_IN_SECONDS);
        }
        
        wp_send_json_success($out);
    }
}

// --- 4. RESULTS HEADER SHORTCODE (Optimized Count) ---
add_shortcode('apex_results_header', 'apex_render_results_header');

function apex_render_results_header() {
    $args = apex_get_search_args();
    $args['posts_per_page'] = 1; 
    $args['fields'] = 'ids';
    
    $query = new WP_Query($args);
    $count = $query->found_posts;

    $loc_text = "";
    if (!empty($_GET['f_loc'])) {
        $loc_ids = array_map('absint', explode(',', $_GET['f_loc']));
        $names = [];
        $terms = get_terms(['taxonomy'=>'property_location', 'include'=>$loc_ids, 'hide_empty'=>false]);
        if(!is_wp_error($terms)) {
            foreach($terms as $t) $names[] = $t->name;
        }
        if (!empty($names)) {
            $loc_text = " in <span class='highlight'>" . implode(', ', $names) . "</span>";
        }
    }

    $current_sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'newest';

    ob_start();
    ?>
    <style>
        .apex-header-wrap {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; flex-wrap: wrap; gap: 15px;
            font-family: "Satoshi", sans-serif;
        }
        .apex-count { font-size: 18px; color: #374151; font-weight: 500; font-family: "Satoshi", sans-serif; }
        .apex-count .highlight { color: #b45309; font-weight: 700; }
        .apex-sort-wrap { display: flex; align-items: center; gap: 12px; }
        .apex-sort-label { font-size: 13px; font-weight: 600; color: #6b7280; font-family: "Satoshi", sans-serif; white-space: nowrap; }
        .apex-sort-sel {
            padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 8px;
            font-size: 14px; color: #374151; outline: none; cursor: pointer; background: #fff;
            font-family: "Satoshi", sans-serif;
        }
        .apex-sort-sel:focus { border-color: #f5c45e; }
        @media (max-width: 600px) {
            .apex-header-wrap { flex-direction: column; align-items: flex-start; }
            .apex-sort-wrap { width: 100%; justify-content: space-between; }
            .apex-sort-sel { flex-grow: 1; margin-left: 10px; }
        }
    </style>

    <div class="apex-header-wrap">
        <div class="apex-count"><?php echo number_format($count); ?> Properties Found<?php echo $loc_text; ?></div>
        <div class="apex-sort-wrap">
            <label class="apex-sort-label">Sort By:</label>
            <select class="apex-sort-sel" onchange="apexSort(this.value)">
                <option value="newest" <?php selected($current_sort, 'newest'); ?>>Newest to Oldest</option>
                <option value="oldest" <?php selected($current_sort, 'oldest'); ?>>Oldest to Newest</option>
                <option value="price_high" <?php selected($current_sort, 'price_high'); ?>>Price: High to Low</option>
                <option value="price_low" <?php selected($current_sort, 'price_low'); ?>>Price: Low to High</option>
            </select>
        </div>
    </div>
    <script>
    function apexSort(val) {
        const url = new URL(window.location.href); url.searchParams.set('sort', val); window.location.href = url.toString();
    }
    </script>
    <?php
    return ob_get_clean();
}

// --- 5. BRICKS QUERY FILTER (Uses Reusable Logic) ---
add_filter( 'bricks/posts/query_vars', function( $query_vars, $settings, $element_id ) {
    if ( is_admin() ) return $query_vars;

    if ( isset($_GET['f_loc']) || isset($_GET['f_type']) || isset($_GET['f_bed']) || isset($_GET['f_min_price']) || isset($_GET['f_purpose']) ) {
        $filter_args = apex_get_search_args();
        $query_vars['tax_query'] = $filter_args['tax_query'];
        $query_vars['meta_query'] = $filter_args['meta_query'];
    }

    if (isset($_GET['sort'])) {
        $sort = sanitize_text_field($_GET['sort']);
        switch ($sort) {
            case 'price_high':
                $query_vars['meta_key'] = 'property_price'; $query_vars['orderby'] = 'meta_value_num'; $query_vars['order'] = 'DESC'; break;
            case 'price_low':
                $query_vars['meta_key'] = 'property_price'; $query_vars['orderby'] = 'meta_value_num'; $query_vars['order'] = 'ASC'; break;
            case 'oldest':
                $query_vars['orderby'] = 'date'; $query_vars['order'] = 'ASC'; break;
            case 'newest': default:
                $query_vars['orderby'] = 'date'; $query_vars['order'] = 'DESC'; break;
        }
    }
    return $query_vars;
}, 10, 3 );