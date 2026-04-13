<?php
include '../comfig/database.php';

header('Content-Type: application/json');

// Handle JSON input (from SheetJS / AJAX)
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

ob_start();

// Custom error handler to log fatals to our debug log
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    file_put_contents('../upload_last_debug.log', "PHP ERROR: [$errno] $errstr in $errfile on line $errline\n", FILE_APPEND);
    return false;
});

if (isset($input['records']) && is_array($input['records'])) {
    $records = $input['records'];
    $filename = $input['filename'] ?? 'Unknown File';
    $upload_status = $input['upload_status'] ?? 'Active';
    $upload_level = $input['upload_level'] ?? 'ES';
    
    // Helper to map shortcuts back to full names
    $mapShortcutToFull = function($reason) {
        if (empty($reason)) return '';
        $mapping = [
            'SWOP' => 'Sick leave without pay',
            'SLWP' => 'Sick leave with pay',
            'VWOP' => 'Vacation leave without pay',
            'VWP' => 'Vacation leave with pay',
            'ML' => 'Maternity leave',
            'STUDY' => 'Study leave',
            'WL' => 'Wellness leave',
            'SPL' => 'Special privilege leave',
            'FL' => 'Forced leave'
        ];
        $reason_upper = strtoupper(trim($reason));
        return $mapping[$reason_upper] ?? $reason;
    };
    
    // Helper to format Excel dates or strings
    $formatDate = function($val) {
        $val = trim(strval($val));
        if (empty($val) || $val == 'x' || $val == 'X') return null;
        if (is_numeric($val)) {
            return date('Y-m-d', intval(($val - 25569) * 86400)); 
        }
        // Handle DD/MM/YYYY or DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $val, $matches)) {
            return "{$matches[3]}-".str_pad($matches[2], 2, '0', STR_PAD_LEFT)."-".str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        }
        $ts = strtotime($val);
        return $ts ? date('Y-m-d', $ts) : null;
    };

    // Helper to extract name (Enhanced to detect Middle Initial)
    $parseName = function($full_name) {
        $full_name = trim(preg_replace('/\s+/', ' ', strval($full_name))); 
        $surname = ""; $first_name = ""; $mi = "";
        
        if (strpos($full_name, ',') !== false) {
            $parts = explode(',', $full_name);
            $surname = strtoupper(trim($parts[0]));
            if (count($parts) >= 3) { // Force handle "SURNAME, FN, MI"
                $first_name = strtoupper(trim($parts[1]));
                $mi = strtoupper(trim($parts[2], '. '));
            } else {
                $fn_full = trim($parts[1] ?? '');
                $fn_parts = explode(' ', $fn_full);
                if (count($fn_parts) > 1) {
                    $last = end($fn_parts);
                    if (strlen($last) <= 2) { 
                        $mi = strtoupper(trim($last, '.'));
                        array_pop($fn_parts);
                        $first_name = strtoupper(trim(implode(' ', $fn_parts)));
                    } else {
                        $first_name = strtoupper($fn_full);
                    }
                } else {
                    $first_name = strtoupper($fn_full);
                }
            }
        } else {
            $parts = explode(' ', $full_name);
            if (count($parts) >= 2) {
                $surname = strtoupper($parts[0]);
                $last_part = end($parts);
                if (strlen($last_part) <= 2) {
                    $mi = strtoupper(trim($last_part, '.'));
                    array_pop($parts);
                } else {
                    $mi = "";
                }
                $first_name = strtoupper(implode(' ', array_slice($parts, 1)));
            } else {
                $surname = strtoupper($full_name);
                $first_name = "";
                $mi = "";
            }
        }
        return [$surname, $first_name, $mi];
    };
    
    // Diagnostic logging to local file
    $all_rows_snippet = "";
    if (strpos($filename, 'ALIVIO') !== false) { 
        $all_rows_snippet = " | AllRows: " . json_encode($records);
    }
    file_put_contents('../upload_last_debug.log', date('Y-m-d H:i:s') . " | File: $filename | Total Rows: " . count($records) . $all_rows_snippet . "\n", FILE_APPEND);

    $success_count = 0;
    $error_count = 0;
    $error_details = [];
    $skipped_count = 0;

    // --- PRE-FATAL: ACCEPT METADATA FROM CLIENT ---
    $meta = $input['metadata'] ?? [];
    $current_employee_no = $meta['employee_no'] ?? null;
    $ext_surname = strtoupper(trim($meta['surname'] ?? ''));
    $ext_first = strtoupper(trim($meta['first_name'] ?? ''));
    $ext_mi = strtoupper(trim($meta['mi'] ?? ''));
    $ext_dob_pob = $meta['dob_pob'] ?? '';

    $current_employee_id = null;
    $current_employee_name = $ext_surname ? "$ext_surname, $ext_first" : ""; 
    $current_employee_dob = null;
    $current_employee_pob = null;

    if (!empty($ext_dob_pob) && strpos($ext_dob_pob, ' / ') !== false) {
        list($dStr, $pStr) = explode(' / ', $ext_dob_pob);
        if ($dStr !== 'N/A') {
            $ts = strtotime($dStr);
            if ($ts) $current_employee_dob = date('Y-m-d', $ts);
        }
        if ($pStr !== 'N/A') $current_employee_pob = $pStr;
    }
    
    try {
    // --- 0. PRE-IDENTIFY VIA CLIENT METADATA ---
    if (!empty($ext_surname) && !empty($ext_first) && $ext_surname !== 'N/A') {
        $check_stmt = $conn->prepare("SELECT id, first_name, middle_initial FROM employees WHERE surname = ? AND (first_name LIKE ? OR first_name = ?)");
        $fn_search = "$ext_first%";
        $check_stmt->bind_param("sss", $ext_surname, $fn_search, $ext_first);
        $check_stmt->execute();
        $res = $check_stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $current_employee_id = $row['id'];
            // Sync current data
            $upd_sql = "UPDATE employees SET employee_no = COALESCE(?, employee_no), date_of_birth = COALESCE(?, date_of_birth), place_of_birth = COALESCE(?, place_of_birth), status = ?, school_level = ?, is_deleted = 0 WHERE id = ?";
            $upd = $conn->prepare($upd_sql);
            $upd->bind_param("sssssi", $current_employee_no, $current_employee_dob, $current_employee_pob, $upload_status, $upload_level, $current_employee_id);
            $upd->execute();
            $upd->close();
        } else {
            $emp_stmt = $conn->prepare("INSERT INTO employees (surname, first_name, middle_initial, employee_no, date_of_birth, place_of_birth, status, school_level, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
            $emp_stmt->bind_param("ssssssss", $ext_surname, $ext_first, $ext_mi, $current_employee_no, $current_employee_dob, $current_employee_pob, $upload_status, $upload_level);
            if ($emp_stmt->execute()) {
                $current_employee_id = $conn->insert_id;
            }
            $emp_stmt->close();
        }
        $check_stmt->close();
    }
    
    // --- 0b. PRE-IDENTIFY VIA FILENAME (Last Resort Baseline) ---
    if (!$current_employee_id && strpos($filename, ',') !== false) {
        $raw_name_fn = pathinfo($filename, PATHINFO_FILENAME);
        // Clean filename (remove extra dots or dates often found in exports)
        $raw_name_fn = preg_replace('/\s*\d{4}.*$/', '', $raw_name_fn); // Remove year+
        
        // Use parseName but keep it simple
        $parts = explode(',', $raw_name_fn);
        $sn_fn = strtoupper(trim($parts[0]));
        $fn_fn = strtoupper(trim(explode(' ', trim($parts[1] ?? ''))[0])); // First word of first name
        
        $check_stmt = $conn->prepare("SELECT id, first_name, middle_initial FROM employees WHERE surname = ? AND (first_name LIKE ? OR first_name = ?)");
        $fn_search = "$fn_fn%";
        $check_stmt->bind_param("sss", $sn_fn, $fn_search, $fn_fn);
        $check_stmt->execute();
        $res = $check_stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $current_employee_id = $row['id'];
            // Sync name if filename has more info (Full First Name/MI)
            $full_fn_from_filename = trim($parts[1] ?? '');
            list($sn_tmp, $fn_tmp, $mi_tmp) = $parseName("$sn_fn, $full_fn_from_filename");
            if ((empty($row['middle_initial']) && !empty($mi_tmp)) || (strlen($fn_tmp) > strlen($row['first_name']))) {
                $upd = $conn->prepare("UPDATE employees SET first_name = ?, middle_initial = ?, status = ?, school_level = ?, is_deleted = 0 WHERE id = ?");
                $upd->bind_param("ssssi", $fn_tmp, $mi_tmp, $upload_status, $upload_level, $current_employee_id);
                $upd->execute();
                $upd->close();
            }
        } else {
            // Create if definitely looks like a valid name
            if (strlen($sn_fn) > 2 && strlen($fn_fn) > 1) {
                $full_fn = trim($parts[1] ?? '');
                list($sn_new, $fn_new, $mi_new) = $parseName("$sn_fn, $full_fn");
                $emp_stmt = $conn->prepare("INSERT INTO employees (surname, first_name, middle_initial, status, school_level, is_deleted) VALUES (?, ?, ?, ?, ?, 0)");
                $emp_stmt->bind_param("sssss", $sn_new, $fn_new, $mi_new, $upload_status, $upload_level);
                if ($emp_stmt->execute()) {
                    $current_employee_id = $conn->insert_id;
                }
                $emp_stmt->close();
            }
        }
        $check_stmt->close();
    }
    

    foreach ($records as $index => $data) {
        $col_count = count($data);
        if ($col_count < 1) { $skipped_count++; continue; }
        
        // Log state at start of row
        file_put_contents('../upload_last_debug.log', "   Row " . ($index+1) . ": i=$index | currentEmp=" . ($current_employee_name ?: 'NONE') . " | currentID=" . ($current_employee_id ?: 'NULL') . "\n", FILE_APPEND);

        $found_employee_this_row = false;
        $period_from = null;
        $period_to = null;
        
        // Initialize column indices if not set
        if (!isset($reason_col)) {
            $reason_col = null;
            $station_col = null;
            $pay_status_col = null;
            $total_days_col = null;
            $remarks_col = null;
        }
        
        // --- 1. EMPLOYEE IDENTIFICATION ---
        // We only look for employee details in the first 15 rows of the sheet
        // and we stop once we have a confident match.
        if ($index < 15) {
            foreach ($data as $idx => $cell) {
                $cell_txt = strtoupper(trim(strval($cell)));
                if (empty($cell_txt)) continue;

                // Detect header columns for later use
                if (stripos($cell, 'REASON') !== false || stripos($cell, 'PARTICULAR') !== false) { $reason_col = $idx; }
                if (stripos($cell, 'STATION') !== false) $station_col = $idx;
                if (stripos($cell, 'PAY') !== false && stripos($cell, 'STATUS') !== false) $pay_status_col = $idx;
                if (stripos($cell, 'DAYS') !== false) $total_days_col = $idx;
                if (stripos($cell, 'REMARKS') !== false) {
                    $remarks_col = $idx;
                    if ($reason_col === null) $reason_col = $idx; // Fallback
                }
                
                // Name Keywords - HARDENED MATCHING
                $name_keywords = ['NAME:', 'NAME', 'FULL NAME:', 'EMPLOYEE NAME:', 'STAFF:', 'SURNAME:', 'EMPLOYEE'];
                foreach ($name_keywords as $kw) {
                    // If the keyword is just "NAME", ensure it's a whole word or followed by colon
                    $match = false;
                    if ($kw === 'NAME' || $kw === 'SURNAME' || $kw === 'EMPLOYEE') {
                        if ($cell_txt === $kw || strpos($cell_txt, $kw.':') !== false) $match = true;
                    } else {
                        if (strpos($cell_txt, $kw) !== false) $match = true;
                    }
                    
                    if ($match) {
                        $raw_name = "";
                        
                        // --- MULTI-COLUMN TEMPLATE DETECTION ---
                        $s = trim(strval($data[$idx + 1] ?? ''));
                        $f = trim(strval($data[$idx + 3] ?? ''));
                        $m = trim(strval($data[$idx + 5] ?? ''));
                        
                        if (!empty($s) && !empty($f) && strpos($s, '(') === false && strpos($f, '(') === false && strlen($s) > 2 ) {
                            $raw_name = "$s, $f, $m";
                        } else {
                            $raw_name = trim(str_replace($kw, '', $cell_txt));
                            if (empty($raw_name)) {
                                for ($off = 1; $off <= 10; $off++) {
                                    if (isset($data[$idx + $off]) && !empty(trim(strval($data[$idx + $off])))) {
                                        $val = trim(strval($data[$idx + $off]));
                                        if (strpos($val, '(') === false && strpos($val, 'SURNAME') === false && strpos($val, 'FIRST') === false) {
                                            $raw_name = $val;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        
                        // FINAL INSTRUCTION GUARD - ADDED MAIDEN/MARRIED
                        $is_instruction = preg_match('/SURNAME|FIRST NAME|NAME|M\.N\.|M\.I\.|BIRTH|REASON|STATION|PERIOD|MAIDEN|MARRIED|EMPLOYEE NO|ID NO|DATE/i', $raw_name) || strpos($raw_name, '(') !== false || strpos($raw_name, '___') !== false;
                        
                        if (!empty($raw_name) && strlen($raw_name) > 2 && !$is_instruction && $raw_name != 'BIRTH:' && $raw_name != 'REASON') {
                            list($surname, $first_name, $mi) = $parseName($raw_name);
                            
                            $check_stmt = $conn->prepare("SELECT id, first_name, middle_initial FROM employees WHERE surname = ? AND (first_name LIKE ? OR first_name = ?)");
                            $fn_search = "$first_name%";
                            $check_stmt->bind_param("sss", $surname, $fn_search, $first_name);
                            $check_stmt->execute();
                            $res = $check_stmt->get_result();
                            if ($row = $res->fetch_assoc()) {
                                $current_employee_id = $row['id'];
                                
                                // Update employee_no if we have one
                                if ($current_employee_no) {
                                    $upd_no = $conn->prepare("UPDATE employees SET employee_no = ?, status = ?, school_level = ?, is_deleted = 0 WHERE id = ?");
                                    $upd_no->bind_param("sssi", $current_employee_no, $upload_status, $upload_level, $current_employee_id);
                                    $upd_no->execute();
                                    $upd_no->close();
                                }
                                // Update name if we have more info now
                                if ((empty($row['middle_initial']) && !empty($mi)) || (strlen($first_name) > strlen($row['first_name']))) {
                                    $upd = $conn->prepare("UPDATE employees SET first_name = ?, middle_initial = ?, status = ?, school_level = ?, is_deleted = 0 WHERE id = ?");
                                    $upd->bind_param("ssssi", $first_name, $mi, $upload_status, $upload_level, $current_employee_id);
                                    $upd->execute();
                                    $upd->close();
                                }
                            } else {
                                $emp_stmt = $conn->prepare("INSERT INTO employees (surname, first_name, middle_initial, employee_no, status, school_level, is_deleted) VALUES (?, ?, ?, ?, ?, ?, 0)");
                                $emp_stmt->bind_param("ssssss", $surname, $first_name, $mi, $current_employee_no, $upload_status, $upload_level);
                                if ($emp_stmt->execute()) {
                                    $current_employee_id = $conn->insert_id;
                                }
                                $emp_stmt->close();
                            }
                            $check_stmt->close();
                            
                            if ($current_employee_id) {
                                $current_employee_name = $raw_name;
                                $found_employee_this_row = true;
                                
                                // If we didn't have birth info, try to update it if we found it earlier
                                // (Optimization: can be added later if needed)
                                
                                break 2; // Stop looking for names in this row AND stop looking in this file if we are confident? 
                                // Actually, just break 2 for this row.
                            }
                        }
                    }
                }

                // If we found a confident name via "NAME" keyword, we should strongly prefer it 
                // throughout the file.
                
                // --- BRUTE FORCE NAME DETECTION (If no keywords found in first 15 rows) ---
                if (!$found_employee_this_row && $index < 12) {
                    if (!$current_employee_id) { // Only brute force if we haven't found anyone yet
                        foreach ($data as $cell) {
                            $cell_val = trim(strval($cell));
                            $cell_val_lc = strtolower($cell_val);
                            $exclusions = ['employee', 'birth', 'inclusive', 'date', 'station', 'reason', 'place', 'assignment', 'remarks', 'pay', 'service', 'issued', 'compliance', 'certified', 'correct', 'division', 'office', 'monitoring', 'accomplished', 'employer', 'purpose', 'also', 'maiden'];
                            $is_excluded = false;
                            foreach($exclusions as $ex) if (strpos($cell_val_lc, $ex) !== false) $is_excluded = true;
                            
                            // Names should NOT start with a parenthesis or containing structural characters like '(' or ')'
                            if (strpos($cell_val, '(') !== false || strpos($cell_val, ')') !== false) $is_excluded = true;

                            // Check if it looks like a name: Must have a comma and at least 2 words, no numbers
                            $is_name_candidate = (strlen($cell_val) > 5 && !$is_excluded && strpos($cell_val, ',') !== false && count(explode(' ', $cell_val)) >= 2 && !preg_match('/[0-9]/', $cell_val));
                            
                            if ($is_name_candidate) {
                                $cell_val = str_ireplace('(Inclusive Date)', '', $cell_val);
                                list($surname, $first_name, $mi) = $parseName($cell_val);
                            if (strlen($surname) > 2 && strlen($first_name) > 1) {
                                // Try to match or create
                                $check_stmt = $conn->prepare("SELECT id, first_name, middle_initial FROM employees WHERE surname = ? AND (first_name LIKE ? OR first_name = ?)");
                                $fn_search = "$first_name%";
                                $check_stmt->bind_param("sss", $surname, $fn_search, $first_name);
                                $check_stmt->execute();
                                $res = $check_stmt->get_result();
                                if ($row = $res->fetch_assoc()) {
                                    $current_employee_id = $row['id'];
                                    // Sync name
                                    if ((empty($row['middle_initial']) && !empty($mi)) || (strlen($first_name) > strlen($row['first_name']))) {
                                        $upd = $conn->prepare("UPDATE employees SET first_name = ?, middle_initial = ?, status = ? WHERE id = ?");
                                        $upd->bind_param("sssi", $first_name, $mi, $upload_status, $current_employee_id);
                                        $upd->execute();
                                        $upd->close();
                                    }
                                    $found_employee_this_row = true;
                                    break 2;
                                } else {
                                    // Create
                                    $emp_stmt = $conn->prepare("INSERT INTO employees (surname, first_name, middle_initial, employee_no, status) VALUES (?, ?, ?, ?, ?)");
                                    $emp_stmt->bind_param("sssss", $surname, $first_name, $mi, $current_employee_no, $upload_status);
                                    if ($emp_stmt->execute()) {
                                        $current_employee_id = $conn->insert_id;
                                        $found_employee_this_row = true;
                                        break 2;
                                    }
                                    $emp_stmt->close();
                                }
                                $check_stmt->close();
                            }
                        }
                    }
                }

                // --- EMPLOYEE NO & BIRTH INFO DETECTION ---
                $emp_keywords = ['EMPLOYEE NO', 'EMP NO', 'ID NO'];
                foreach($emp_keywords as $ekw) {
                    if (strpos($cell_txt, $ekw) !== false) {
                        for ($off = 1; $off <= 8; $off++) {
                            $eval = trim(strval($data[$idx + $off] ?? ''));
                            if (!empty($eval) && strlen($eval) >= 2 && !preg_match('/EMPLOYEE|NAME|REASON|STATION/i', $eval)) {
                                $current_employee_no = $eval;
                                if ($current_employee_id) {
                                    $upd = $conn->prepare("UPDATE employees SET employee_no = ? WHERE id = ?");
                                    $upd->bind_param("si", $current_employee_no, $current_employee_id);
                                    $upd->execute();
                                    $upd->close();
                                }
                                break;
                            }
                        }
                    }
                }

                $b_keywords = ['BIRTH', 'DATE OF BIRTH', 'BORN:', 'PLACE OF BIRTH'];
                foreach($b_keywords as $bkw) {
                    if (strpos($cell_txt, $bkw) !== false) {
                        for ($off = 1; $off <= 11; $off++) {
                            $bval = trim(strval($data[$idx + $off] ?? ''));
                            if (empty($bval) || strlen($bval) < 2 || strpos($bval, '(') !== false || preg_match('/SURNAME|FIRST|NAME|CHECKED|FROM|VERIFIED|BIRTH/i', $bval)) continue;
                            
                            $bd = $formatDate($bval);
                            if ($bd && !$current_employee_dob) {
                                $current_employee_dob = $bd;
                                if ($current_employee_id) {
                                    $upd = $conn->prepare("UPDATE employees SET date_of_birth = ? WHERE id = ?");
                                    $upd->bind_param("si", $current_employee_dob, $current_employee_id);
                                    $upd->execute();
                                    $upd->close();
                                }
                            } else if (strlen($bval) > 2 && !$current_employee_pob && !strpos($bval, '___')) {
                                // Redundant check for Place of Birth to ensure no notes leak in
                                if (!preg_match('/CHECKED|FROM|VERIFIED|BIRTH|BAP-|BORN|DATE/i', $bval)) {
                                    $current_employee_pob = $bval;
                                    if ($current_employee_id) {
                                        $upd = $conn->prepare("UPDATE employees SET place_of_birth = ? WHERE id = ?");
                                        $upd->bind_param("si", $current_employee_pob, $current_employee_id);
                                        $upd->execute();
                                        $upd->close();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($found_employee_this_row) continue;

        // --- 2. LEAVE DATA DETECTION (Flexible Scanning) ---
        $period_from = null;
        $period_to = null;
        $found_dates = [];
        
        for ($i = 0; $i < $col_count; $i++) {
            $val = trim(strval($data[$i] ?? ''));
            if (empty($val) || $val == 'x' || $val == 'X' || strlen($val) < 2) continue;
            
            $d = $formatDate($val);
            if ($d) {
                $found_dates[] = ['date' => $d, 'idx' => $i];
            }
        }

        if (count($found_dates) >= 1) {
            // If only one date, period_to is NULL (User wants N/A in display)
            $period_from = $found_dates[0]['date'];
            $period_to = (count($found_dates) >= 2) ? $found_dates[1]['date'] : null;
            $base = $found_dates[0]['idx'];
            
            // Log exactly what we found for debugging
            file_put_contents('../upload_last_debug.log', "   Row " . ($index+1) . ": Found Date=$period_from | data=" . json_encode($data) . " | EmpID=" . ($current_employee_id ?? 'NULL') . "\n", FILE_APPEND);
        }

        if ($period_from) {
            // Found a data row candidate
            // Look for reason in subsequent cells (could be at base+1 or base+2)
            $reasonRaw = trim(strval($data[$base+1] ?? ''));
            if (empty($reasonRaw) || is_numeric($reasonRaw)) $reasonRaw = trim(strval($data[$base+2] ?? ''));
            
            $reason = $mapShortcutToFull($reasonRaw);
            
            // Look for station (could be at base+2 or base+3)
            $station = trim(strval($data[$base+3] ?? ''));
            if (empty($station) || $station == $reasonRaw) $station = trim(strval($data[$base+4] ?? ''));
            
            // FILTER: Skip row if it looks like a header or is completely empty
            if (empty($reason) || $reason == 'REASON' || strpos($reason, '***') !== false || $reason == 'x' || $reason == 'X') {
                // If it's short but common, it might be a valid reason code (like VWOP)
                if (strlen($reason) < 2 && !in_array(strtoupper($reason), ['FL', 'ML', 'WL', 'SL', 'VL'])) {
                    $skipped_count++;
                    continue;
                }
            }
            // Relax station requirement: if reason is valid but station is empty, we still import
            if (strlen($station) <= 1 && ($station == 'STATION' || strpos($station, '***') !== false)) {
                $station = ''; // Just clear it instead of skipping
            }

            $without_pay = trim($data[$base+4] ?? '');
            $with_pay = trim($data[$base+5] ?? '');
            
            // Logic: Default to 'N/A'. Only update if 'Without Pay' or 'With Pay' columns have valid values.
            $pay_status = 'N/A';
            if (!empty($without_pay) && $without_pay != '0' && !in_array(strtolower($without_pay), ['x', 'n/a', 'none'])) {
                $pay_status = 'Without Pay';
            } elseif (!empty($with_pay) && $with_pay != '0' && !in_array(strtolower($with_pay), ['x', 'n/a', 'none'])) {
                $pay_status = 'With Pay';
            }
            
            // Calculate total days from dates (User request: base on period covered)
            $total_days = 0;
            if ($period_from && $period_to) {
                try {
                    $d1 = new DateTime($period_from);
                    $d2 = new DateTime($period_to);
                    $total_days = $d2->diff($d1)->days + 1;
                } catch (Exception $e) {
                    $total_days = 0;
                }
            } else if ($period_from) {
                $total_days = 1;
            }
            
            $remarks = trim($data[$base+6] ?? '');
            
            if ($current_employee_id) {
                $sql = "INSERT INTO leaves (employee_id, period_from, period_to, reason, station, pay_status, total_days, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    $error_count++;
                    $error_details[] = "SQL Prepare Error: " . $conn->error;
                    continue;
                }
                $stmt->bind_param("isssssis", $current_employee_id, $period_from, $period_to, $reason, $station, $pay_status, $total_days, $remarks);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                    $error_details[] = "Row " . ($index + 1) . ": " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error_count++;
                $error_details[] = "Row " . ($index + 1) . ": No employee identified yet.";
            }
        } else {
            $skipped_count++;
        }
    }

    $msg = "Processed sheet.";
    if ($success_count > 0) $msg = "Successfully imported $success_count records.";
    if ($error_count > 0) $msg .= " File: [ $filename ] | $error_count errors. " . implode(" | ", array_slice($error_details, 0, 1));
    if ($success_count == 0 && $error_count == 0) {
        $msg = "No records found in: $filename";
        if ($skipped_count > 0) $msg .= " (Skipped $skipped_count rows that lacked dates or reason).";
    }
    }
    } catch (Throwable $e) {
        file_put_contents('../upload_last_debug.log', date('Y-m-d H:i:s') . " | FATAL EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n", FILE_APPEND);
        $final_json = json_encode(['status' => 'error', 'message' => 'Internal Server Error: ' . $e->getMessage()]);
        ob_clean();
        echo $final_json;
        exit;
    }

    ob_clean();
    // Return success if no hard errors happened, even if 0 records found (shows Check in UI)
    $is_success = ($success_count > 0 || ($error_count == 0 && $skipped_count > 0));
    $final_json = json_encode(['status' => ($is_success ? 'success' : 'error'), 'message' => $msg]);
    file_put_contents('../upload_last_debug.log', date('Y-m-d H:i:s') . " | $filename | Response: $final_json\n", FILE_APPEND);
    echo $final_json;
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request or empty records.']);
?>
