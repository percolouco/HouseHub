<?php
// modules/budget/includes/DynamicBudgetCalculator.php

class DynamicBudgetCalculator {
    private \PDO $pdo;
    private int $year;
    private string $month;
    private array $settings = [];
    private array $foyerSettings = [];
    
    private array $kids = [];
    private string $helperName = 'Intervenant'; // Valeur par défaut
    private array $calendarEvents = [];

    public function __construct(\PDO $pdo, int|string $year, int|string $month) {
        $this->pdo = $pdo;
        $this->year = (int)$year;
        $this->month = str_pad((string)$month, 2, '0', STR_PAD_LEFT);
        
        $this->loadSettings();
        $this->loadPeople();
        $this->loadEvents();
    }

    private function loadSettings(): void {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM pf_settings WHERE module = 'budget'");
        $this->settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->foyerSettings = $this->pdo->query("SELECT * FROM pf_foyer_settings LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    }

    private function getSetting(string $key, float|int $default = 0): float {
        return isset($this->settings[$key]) ? (float)$this->settings[$key] : (float)$default;
    }

    private function getSettingString(string $key, string $default = ''): string {
        return isset($this->settings[$key]) ? (string)$this->settings[$key] : $default;
    }

    private function loadPeople(): void {
        // 1. Chargement des enfants sans doublons
        $stmt = $this->pdo->query("SELECT id, name, care_modes FROM pf_people WHERE role IN ('enfant', 'child') AND is_active = 1");
        $rawKids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->kids = [];
        $seenNames = [];
        
        foreach ($rawKids as $k) {
            $nameKey = mb_strtolower(trim($k['name']));
            if (!isset($seenNames[$nameKey])) {
                $this->kids[] = $k;
                $seenNames[$nameKey] = true;
            }
        }

        // 2. Chargement dynamique de l'intervenant (ex: Carole)
        $stmtHelper = $this->pdo->query("SELECT name FROM pf_people WHERE role IN ('helper', 'nounou') AND is_active = 1 LIMIT 1");
        $helper = $stmtHelper->fetchColumn();
        if ($helper) {
            $this->helperName = mb_strtoupper($helper);
        }
    }

    private function loadEvents(): void {
        $startDate = "{$this->year}-{$this->month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        $stmt = $this->pdo->prepare("SELECT event_date, event_type, person_id FROM pf_events WHERE event_date BETWEEN ? AND ?");
        $stmt->execute([$startDate, $endDate]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($events as $e) {
            $this->calendarEvents[$e['event_date']][] = $e;
        }
    }

    private function getOffDays(): array {
        $cacheKey = "offdays_{$this->year}";
        $stmt = $this->pdo->prepare("SELECT content FROM pf_notes WHERE note_type = 'system_cache' AND reference_id = ?");
        $stmt->execute([$cacheKey]);
        $cached = $stmt->fetchColumn();
        
        if ($cached) return json_decode($cached, true);

        $offDays = ['feries' => [], 'vacances' => []];

        try {
            $jsonFeries = @file_get_contents("https://calendrier.api.gouv.fr/jours-feries/metropole/{$this->year}.json");
            if ($jsonFeries) $offDays['feries'] = array_keys(json_decode($jsonFeries, true));
        } catch (\Throwable $e) {}

        $zone = $this->foyerSettings['zone_scolaire'] ?? 'C';
        try {
            // 🔥 FIX STATE OF THE ART : On cible uniquement la population "Élèves"
            $prevYear = $this->year - 1;
            $nextYear = $this->year + 1;
            $where = "(annee_scolaire='{$prevYear}-{$this->year}' OR annee_scolaire='{$this->year}-{$nextYear}') AND zones LIKE '%Zone {$zone}%' AND population = 'Élèves'";
            
            $url = "https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records?where=" . urlencode($where) . "&limit=100";
            
            $jsonVacances = @file_get_contents($url);
            if ($jsonVacances) {
                $data = json_decode($jsonVacances, true);
                if (!empty($data['results'])) {
                    foreach ($data['results'] as $r) {
                        $start = new DateTime(substr($r['start_date'], 0, 10));
                        $end = new DateTime(substr($r['end_date'], 0, 10));
                        // Sécurité : si la vacance commence le vendredi soir, on la compte à partir du samedi
                        if ($start->format('N') == 5) $start->modify('+1 day'); 
                        
                        while ($start < $end) {
                            $offDays['vacances'][] = $start->format('Y-m-d');
                            $start->modify('+1 day');
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        $this->pdo->prepare("INSERT INTO pf_notes (note_type, reference_id, content) VALUES ('system_cache', ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content)")
                  ->execute([$cacheKey, json_encode($offDays)]);

        return $offDays;
    }

    public function getEstimate(string $dynamicCode): array {
        if ($dynamicCode === 'SCHOOL_ESTIMATE') {
            return $this->calculateSchoolEstimate();
        }
        return ['amount' => 0.0, 'details' => tr('error_occured')];
    }

    private function calculateSchoolEstimate(): array {
        $nannyFixed = $this->getSetting('budget_nanny_fixed', 700);
        $nannyDaily = $this->getSetting('budget_nanny_daily', 4);
        $nannyAid   = $this->getSetting('budget_nanny_aid', 120);
        
        $cesuAvg = $this->getSetting('budget_nanny_cesu_avg', 170);
        $overridesJson = $this->getSettingString('budget_cesu_overrides', '{}');
        $cesuOverrides = json_decode($overridesJson, true) ?: [];
        
        $monthKey = sprintf("%04d-%02d", $this->year, $this->month);
        $isCesuOverride = isset($cesuOverrides[$monthKey]);
        $cesuApplied = $isCesuOverride ? (float)$cesuOverrides[$monthKey] : $cesuAvg;
        $cesuLabelText = $isCesuOverride ? tr('bud_audit_cesu_adj') : tr('bud_audit_cesu_avg');
        // ------------------------------------

        $schoolMeal      = $this->getSetting('budget_school_meal', 5.62);
        $schoolAftercare = $this->getSetting('budget_school_aftercare', 1.97);
        $schoolFullday   = $this->getSetting('budget_school_fullday', 21.89);

        $offDays = $this->getOffDays();
        
        $totalCost = 0;
        $details = sprintf(tr('bud_audit_month') . "\n\n", $this->month, $this->year);

        $hasNanny = false;
        $nannyOffDays = 0; 
        $nannyGlobalCost = $nannyFixed - $nannyAid - $cesuAvg;

        $kidsStats = [];

        // 1. Initialisation stricte
        foreach ($this->kids as $k) {
            $modes = json_decode($k['care_modes'] ?? '[]', true) ?: [];
            $isNanny = count(array_intersect(array_map('strtolower', $modes), ['nounou', 'kangur'])) > 0;
            if ($isNanny) $hasNanny = true;
            
            $kidsStats[$k['id']] = [
                'name' => $k['name'], 'isNanny' => $isNanny,
                'nannyDays' => 0, 'sick' => 0,
                'schoolMeal' => 0, 'schoolAftercare' => 0, 'center' => 0
            ];
        }

        $daysInMonth = (int)date('t', strtotime(sprintf("%04d-%02d-01", $this->year, $this->month)));

        // 2. Itération SANS pointeurs de référence
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf("%04d-%02d-%02d", $this->year, $this->month, $d);
            $dayOfWeek = date('N', strtotime($dateStr)); 

            if ($dayOfWeek >= 6) continue;
            if (in_array($dateStr, $offDays['feries'])) continue;

            $isVacances = in_array($dateStr, $offDays['vacances']);
            

            $events = $this->calendarEvents[$dateStr] ?? [];

            $nannyOffToday = false;
            foreach ($events as $e) {
                if (in_array($e['event_type'], ['HELPER_OFF', 'HELPER_EXTRA'])) $nannyOffToday = true;
            }
            if ($nannyOffToday) $nannyOffDays++;

            foreach ($this->kids as $kid) {
                $kidId = $kid['id'];
                
                $isSick = false;
                $hasCenterEvent = false;
                foreach ($events as $e) {
                    if ($e['event_type'] === 'CHILD_SICK' && $e['person_id'] == $kidId) $isSick = true;
                    if (strtoupper($e['event_type']) === 'CENTRE' && $e['person_id'] == $kidId) $hasCenterEvent = true;
                }

                if ($isSick) {
                    $kidsStats[$kidId]['sick']++;
                    continue; 
                }

                if ($kidsStats[$kidId]['isNanny']) {
                    if (!$nannyOffToday) $kidsStats[$kidId]['nannyDays']++;
                } else {
                    if ($isVacances) {
                        if ($hasCenterEvent) $kidsStats[$kidId]['center']++;
                    } else {
                        if ($dayOfWeek == 3) {
                            $kidsStats[$kidId]['center']++;
                        } else {
                            $kidsStats[$kidId]['schoolMeal']++;
                            if (in_array($dayOfWeek, [2, 4])) $kidsStats[$kidId]['schoolAftercare']++;
                        }
                    }
                }
            }
        }

        $totalNannyDays = 0;
        foreach ($kidsStats as $stat) {
            if ($stat['isNanny']) {
                $totalNannyDays += $stat['nannyDays'];
            }
        }
        
        $totalNannyFees = $totalNannyDays * $nannyDaily;
        // On soustrait $cesuApplied au lieu de $cesuAvg
        $nannyGlobalCost = $nannyFixed + $totalNannyFees - $nannyAid - $cesuApplied;

        // 3. Rédaction du Bilan
        if ($hasNanny) {
            $totalCost += max(0, $nannyGlobalCost);
            // On passe $cesuLabelText au sprintf
            $details .= sprintf(tr('bud_audit_nanny_full') . "\n", 
                        $this->helperName, $nannyFixed, $totalNannyDays, $nannyDaily, $totalNannyFees, $nannyAid, $cesuLabelText, $cesuApplied, max(0, $nannyGlobalCost));
            
            if ($nannyOffDays > 0) {
                $details .= sprintf(tr('bud_audit_nanny_abs') . "\n", $this->helperName, $nannyOffDays);
            }
            $details .= "\n";
        }

        foreach ($kidsStats as $finalStat) {
            if ($finalStat['isNanny']) {
                // L'enfant est chez la nounou : on ne l'affiche QUE s'il a été malade
                if ($finalStat['sick'] > 0) {
                    $details .= sprintf(tr('bud_audit_child') . "\n", mb_strtoupper($finalStat['name']));
                    $details .= sprintf(tr('bud_audit_sick') . "\n", $finalStat['sick']) . "\n\n";
                }
            } else {
                // L'enfant va à l'école / centre
                $details .= sprintf(tr('bud_audit_child') . "\n", mb_strtoupper($finalStat['name']));
                
                $costM = $finalStat['schoolMeal'] * $schoolMeal;
                $costA = $finalStat['schoolAftercare'] * $schoolAftercare;
                $costC = $finalStat['center'] * $schoolFullday;
                $kidTotal = $costM + $costA + $costC;
                
                $totalCost += $kidTotal;
                
                $details .= sprintf(tr('bud_audit_school_fee') . "\n", 
                            $finalStat['schoolMeal'], $finalStat['schoolAftercare'], $finalStat['center'], $kidTotal);
                
                if ($finalStat['sick'] > 0) {
                    $details .= sprintf(tr('bud_audit_sick') . "\n", $finalStat['sick']) . "\n";
                }
                $details .= "\n";
            }
        }

        return [
            'amount' => round($totalCost, 2),
            'details' => trim($details)
        ];
    }

}