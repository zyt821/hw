<?php
class ExcelComparisonTool {
    private $dceData = [];
    private $argosData = [];
    private $snData = [];
    private $comparisonResults = [];
    private $sessionId = '';

    // set unique session id
    public function setSessionId($sessionId) {
        $this->sessionId = $sessionId;
        return $this;
    }

    // set comparison results
    public function setComparisonResults(array $results) {
        $this->comparisonResults = $results;
        return $this;
    }

    // import DCE file
    public function importDceFile($sessionId = null) {
        $sessionId = $sessionId ?: $this->sessionId;
        if (!$sessionId) {
            throw new Exception("Session ID is required");
        }

        $this->dceData = loadCsvDataFromDatabase('dce_data', $sessionId);
        return $this->dceData;
    }


    public function importArgosFile($sessionId = null) {
        $sessionId = $sessionId ?: $this->sessionId;
        if (!$sessionId) {
            throw new Exception("Session ID is required");
        }

        $this->argosData = loadCsvDataFromDatabase('argos_data', $sessionId);
        return $this->argosData;
    }

    // 修改importSnFile方法 - 从数据库加载数据
    public function importSnFile($sessionId = null) {
        $sessionId = $sessionId ?: $this->sessionId;
        if (!$sessionId) {
            throw new Exception("Session ID is required");
        }

        $this->snData = loadCsvDataFromDatabase('sn_data', $sessionId);
        return $this->snData;
    }

    // data merge and comparison using serial number as key
    // *test11 - Batch Processing for Large Datasets
    /**
     * Compare data in batches to optimize memory usage
     * @param int $batchSize Number of records to process in each batch
     * @return array Complete comparison results
     */
    public function compareData(int $batchSize = 1000): array {
        $comparisonResults = [];
        // $totalDce = count($this->dceData);

        // Step 1: Create indexed lookup arrays for faster searching
        $argosMap = [];
        foreach ($this->argosData as $argosRow) {
            $serialKey = strtolower($argosRow['serial#']);
            if (!isset($argosMap[$serialKey])) {
                $argosMap[$serialKey] = [];
            }
            $argosMap[$serialKey][] = $argosRow;
        }

        $snMap = [];
        foreach ($this->snData as $snRow) {
            $serialKey = strtolower($snRow['Serial number']);
            if (!isset($snMap[$serialKey])) {
                $snMap[$serialKey] = [];
            }
            $snMap[$serialKey][] = $snRow;
        }

        // Step 2: Process DCE data in batches
        $totalDce = count($this->dceData);
        for ($i = 0; $i < $totalDce; $i += $batchSize) {
            $batch = array_slice($this->dceData, $i, $batchSize);

            foreach ($batch as $dceRow) {
                $dceSerial = strtolower($dceRow['serial'] ?? '');

                // Find matching ARGOS entries using the lookup map
                $matchedArgosRows = $argosMap[$dceSerial] ?? [];

                // If no ARGOS match found, create non-compliant entry
                if (empty($matchedArgosRows)) {
                    $comparisonResults[] = [
                        'hostname' => $dceRow['hostname'] ?? 'N/A',
                        'dce_useruid' => $dceRow['useruid'] ?? 'N/A',
                        'dce_sn' => $dceRow['serial'] ?? 'N/A',
                        'argos_owner' => 'N/A',
                        'argos_serial' => 'N/A',
                        'sn_serial' => 'N/A',
                        'dce_livedate' => $dceRow['livedate'] ?? 'N/A',
                        'dce_userwhen' => $dceRow['userwhen'] ?? 'N/A',
                        'argos_state' => 'N/A',
                        'sn_state' => 'N/A',
                        'sn_pending_collection' => 'N/A',
                        'dce_status' => 'N/A',
                        'compliance_status' => 'non-compliant',
                        'compliance_reason' => 'No matching ARGOS entry'
                    ];
                    continue;
                }

                // For each matched ARGOS row, find matching SN entry
                foreach ($matchedArgosRows as $argosRow) {
                    $argosSerial = strtolower($argosRow['serial#'] ?? '');

                    // Find matching SN entries using the lookup map
                    $matchedSnRows = $snMap[$argosSerial] ?? [];

                    // If no SN match found, create non-compliant entry
                    if (empty($matchedSnRows)) {
                        $comparisonResults[] = [
                            'hostname' => $dceRow['hostname'] ?? 'N/A',
                            'dce_useruid' => $dceRow['useruid'] ?? 'N/A',
                            'dce_sn' => $dceRow['serial'] ?? 'N/A',
                            'argos_owner' => $argosRow['owner'] ?? 'N/A',
                            'argos_serial' => $argosRow['serial#'] ?? 'N/A',
                            'sn_serial' => 'N/A',
                            'dce_livedate' => $dceRow['livedate'] ?? 'N/A',
                            'dce_userwhen' => $dceRow['userwhen'] ?? 'N/A',
                            'argos_state' => $argosRow['state'] ?? 'N/A',
                            'sn_state' => 'N/A',
                            'sn_pending_collection' => 'N/A',
                            'dce_status' => $this->checkDceStatus($dceRow, $argosRow),
                            'compliance_status' => 'non-compliant',
                            'compliance_reason' => 'No matching SN entry'
                        ];
                        continue;
                    }

                    // Process each matching SN row
                    foreach ($matchedSnRows as $snRow) {
                        // Check DCE status first (disabled or not)
                        $dceStatus = $this->checkDceStatus($dceRow, $argosRow);

                        // Check compliance based on the rules
                        $complianceResult = $this->checkCompliance($dceStatus, $argosRow, $snRow);

                        $comparisonResults[] = [
                            'hostname' => $dceRow['hostname'] ?? 'N/A',
                            'dce_useruid' => $dceRow['useruid'] ?? 'N/A',
                            'dce_sn' => $dceRow['serial'] ?? 'N/A',
                            'argos_owner' => $argosRow['owner'] ?? 'N/A',
                            'argos_serial' => $argosRow['serial#'] ?? 'N/A',
                            'sn_serial' => $snRow['Serial number'] ?? 'N/A',
                            'dce_livedate' => $dceRow['livedate'] ?? 'N/A',
                            'dce_userwhen' => $dceRow['userwhen'] ?? 'N/A',
                            'argos_state' => $argosRow['state'] ?? 'N/A',
                            'sn_state' => $snRow['State'] ?? 'N/A',
                            'sn_pending_collection' => $snRow['Pending collection [Hardware]'] ?? 'N/A',
                            'dce_status' => $dceStatus,
                            'compliance_status' => $complianceResult['status'],
                            'compliance_reason' => $complianceResult['reason']
                        ];
                    }
                }
            }

            // Free memory after each batch
            unset($batch);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        // Step 3: Process ARGOS records not in DCE (in batches)
        $totalArgos = count($this->argosData);
        for ($i = 0; $i < $totalArgos; $i += $batchSize) {
            $batch = array_slice($this->argosData, $i, $batchSize);

            foreach ($batch as $argosRow) {
                $argosSerial = strtolower($argosRow['serial#'] ?? '');

                // Check if this ARGOS record was already processed with DCE
                $found = false;
                foreach ($this->dceData as $dceRow) {
                    if (strcasecmp($dceRow['serial'] ?? '', $argosRow['serial#'] ?? '') === 0) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    // Find matching SN entries
                    $matchedSnRows = $snMap[$argosSerial] ?? [];

                    if (empty($matchedSnRows)) {
                        $comparisonResults[] = [
                            'hostname' => $argosRow['hostname'] ?? 'N/A',
                            'dce_useruid' => 'N/A',
                            'dce_sn' => 'N/A',
                            'argos_owner' => $argosRow['owner'] ?? 'N/A',
                            'argos_serial' => $argosRow['serial#'] ?? 'N/A',
                            'sn_serial' => 'N/A',
                            'dce_livedate' => 'N/A',
                            'dce_userwhen' => 'N/A',
                            'argos_state' => $argosRow['state'] ?? 'N/A',
                            'sn_state' => 'N/A',
                            'sn_pending_collection' => 'N/A',
                            'dce_status' => 'N/A',
                            'compliance_status' => 'non-compliant',
                            'compliance_reason' => 'In ARGOS but not in DCE or SN'
                        ];
                    } else {
                        foreach ($matchedSnRows as $snRow) {
                            // Process as if DCE is disabled
                            $complianceResult = $this->checkCompliance('dce_disabled', $argosRow, $snRow);

                            $comparisonResults[] = [
                                'hostname' => $argosRow['hostname'] ?? 'N/A',
                                'dce_useruid' => 'N/A',
                                'dce_sn' => 'N/A',
                                'argos_owner' => $argosRow['owner'] ?? 'N/A',
                                'argos_serial' => $argosRow['serial#'] ?? 'N/A',
                                'sn_serial' => $snRow['Serial number'] ?? 'N/A',
                                'dce_livedate' => 'N/A',
                                'dce_userwhen' => 'N/A',
                                'argos_state' => $argosRow['state'] ?? 'N/A',
                                'sn_state' => $snRow['State'] ?? 'N/A',
                                'sn_pending_collection' => $snRow['Pending collection [Hardware]'] ?? 'N/A',
                                'dce_status' => 'dce_disabled',
                                'compliance_status' => $complianceResult['status'],
                                'compliance_reason' => $complianceResult['reason']
                            ];
                        }
                    }
                }
            }

            // Free memory after each batch
            unset($batch);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        // Step 4: Process SN records not in DCE or ARGOS (in batches)
        $totalSn = count($this->snData);
        for ($i = 0; $i < $totalSn; $i += $batchSize) {
            $batch = array_slice($this->snData, $i, $batchSize);

            foreach ($batch as $snRow) {
                $snSerial = strtolower($snRow['Serial number'] ?? '');

                // Check if this SN record was already processed
                $foundInDce = false;
                foreach ($this->dceData as $dceRow) {
                    if (strcasecmp($dceRow['serial'] ?? '', $snRow['Serial number'] ?? '') === 0) {
                        $foundInDce = true;
                        break;
                    }
                }

                $foundInArgos = false;
                if (!$foundInDce) {
                    foreach ($this->argosData as $argosRow) {
                        if (strcasecmp($argosRow['serial#'] ?? '', $snRow['Serial number'] ?? '') === 0) {
                            $foundInArgos = true;
                            break;
                        }
                    }
                }

                if (!$foundInDce && !$foundInArgos) {
                    $comparisonResults[] = [
                        'hostname' => 'N/A',
                        'dce_useruid' => 'N/A',
                        'dce_sn' => 'N/A',
                        'argos_owner' => 'N/A',
                        'argos_serial' => 'N/A',
                        'sn_serial' => $snRow['Serial number'] ?? 'N/A',
                        'dce_livedate' => 'N/A',
                        'dce_userwhen' => 'N/A',
                        'argos_state' => 'N/A',
                        'sn_state' => $snRow['State'] ?? 'N/A',
                        'sn_pending_collection' => $snRow['Pending collection [Hardware]'] ?? 'N/A',
                        'dce_status' => 'N/A',
                        'compliance_status' => 'non-compliant',
                        'compliance_reason' => 'In SN but not in DCE or ARGOS'
                    ];
                }
            }

            // Free memory after each batch
            unset($batch);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $this->comparisonResults = $comparisonResults;
        return $comparisonResults;
    }

    // Check DCE status (disabled or not)
    private function checkDceStatus(array $dceRow, array $argosRow): string {
        // Check if useruid (from DCE) matches owner (from ARGOS)
        $ownerMatch = strcasecmp($dceRow['useruid'], $argosRow['owner']) === 0;
        return ($ownerMatch) ? 'dce_active' : 'dce_disabled';
    }

    // Check compliance based on the new rules
    private function checkCompliance(string $dceStatus, array $argosRow, array $snRow): array {
        $argosState = strtolower($argosRow['state']);
        $snState = strtolower($snRow['State']);
        $snPendingCollection = strtolower($snRow['Pending collection [Hardware]']);

        // Default status is non-compliant until proven otherwise
        $status = 'non-compliant';
        $reason = '';

        // For DCE disabled
        if ($dceStatus === 'dce_disabled') {
            switch ($argosState) {
                case 'state_unassigned':
                    if ($snState === 'in stock') {
                        $status = 'compliant';
                        $reason = 'DCE disabled, Argos = state_unassigned, SN in stock';
                    } else {
                        $reason = "DCE disabled, Argos = state_unassigned, but SN state is not 'in stock'";
                    }
                    break;

                case 'state_pending_signoff':
                case 'state_signed_off':
                    $reason = "DCE disabled with ARGOS = $argosState is non-compliant";
                    break;

                case 'state_pending_collection':
                    if ($snPendingCollection === 'true') {
                        $status = 'compliant';
                        $reason = 'DCE disabled, Argos = state_pending_collection, SN pending collection is true';
                    } else {
                        $reason = 'DCE disabled, Argos = state_pending_collection, but SN pending collection is not true';
                    }
                    break;

                case 'state_retired':
                    if ($snState === 'retired' || $snState === 'missing') {
                        $status = 'compliant';
                        $reason = "DCE disabled, Argos = state_retired, SN state is $snState";
                    } else {
                        $reason = "DCE disabled, Argos = state_retired, but SN state is not 'retired' or 'missing'";
                    }
                    break;

                default:
                    $reason = "Unknown ARGOS state: $argosState";
                    break;
            }
        }
        // For DCE not disabled
        else if ($dceStatus === 'dce_active') {
            switch ($argosState) {
                case 'state_unassigned':
                    if ($snState === 'in stock') {
                        $status = 'compliant';
                        $reason = 'DCE not disabled, Argos = state_unassigned, SN in stock';
                    } else {
                        $reason = "DCE not disabled, Argos = state_unassigned, but SN state is not 'in stock'";
                    }
                    break;

                case 'state_pending_signoff':
                case 'state_signed_off':
                    if ($snState === 'in use') {
                        $status = 'compliant';
                        $reason = "DCE not disabled, Argos = $argosState, SN in use";
                    } else {
                        $reason = "DCE not disabled, Argos = $argosState, but SN state is not 'in use'";
                    }
                    break;

                case 'state_pending_collection':
                    if ($snPendingCollection === 'true') {
                        $status = 'compliant';
                        $reason = 'DCE not disabled, Argos = state_pending_collection, SN pending collection is true';
                    } else {
                        $reason = 'DCE not disabled, Argos = state_pending_collection, but SN pending collection is not true';
                    }
                    break;

                case 'state_retired':
                    $reason = 'DCE not disabled but Argos = state_retired';
                    break;

                default:
                    $reason = "Unknown ARGOS state: $argosState";
                    break;
            }
        } else {
            $reason = "Unknown DCE status: $dceStatus";
        }

        return [
            'status' => $status,
            'reason' => $reason
        ];
    }

    // Exporting non-compliant results
    public function exportNonCompliantResults() {
        // Filter out non-compliant results
        $nonCompliantResults = array_filter($this->comparisonResults, function($result) {
            return $result['compliance_status'] === 'non-compliant';
        });

        // Define headers
        $headers = [
            'Hostname',
            'DCE UserUID',
            'ARGOS Owner',
            'DCE SN#',
            'ARGOS Serial#',
            'SN Serial Number',
            'DCE Livedate',
            'DCE Userwhen',
            'ARGOS State',
            'SN State',
            'SN Pending Collection',
            'DCE Status',
            'Compliance Status',
            'Compliance Reason'
        ];

        // Create export directory
        $exportDir = __DIR__ . '/exports/';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        // Generate unique file name
        $filename = $exportDir . 'non_compliant_results_' . date('YmdHis') . '.csv';

        // Open file for writing
        $file = fopen($filename, 'w');

        // Write headers
        fputcsv($file, $headers);

        // Write data rows
        foreach ($nonCompliantResults as $result) {
            $row = [];
            foreach ($headers as $header) {
                $key = $this->getKeyFromHeader($header);
                $row[] = $result[$key] ?? 'N/A';
            }
            fputcsv($file, $row);
        }

        // Close file
        fclose($file);

        return $filename;
    }

    // Helper function to convert header name to result key
    private function getKeyFromHeader($header) {
        $mapping = [
            'Hostname' => 'hostname',
            'DCE UserUID' => 'dce_useruid',
            'ARGOS Owner' => 'argos_owner',
            'DCE SN#' => 'dce_sn',
            'ARGOS Serial#' => 'argos_serial',
            'SN Serial Number'=> 'sn_serial',
            'DCE Livedate' => 'dce_livedate',
            'DCE Userwhen' => 'dce_userwhen',
            'ARGOS State' => 'argos_state',
            'SN State' => 'sn_state',
            'SN Pending Collection' => 'sn_pending_collection',
            'DCE Status' => 'dce_status',
            'Compliance Status' => 'compliance_status',
            'Compliance Reason' => 'compliance_reason'
        ];
        return $mapping[$header] ?? strtolower(str_replace(' ', '_', $header));
    }

    // Method to clean DCE data
    public function cleanDceData()
    {
        if (empty($this->dceData)) {
            return;
        }

        // Remove rows where all values in a specific column are empty or null
        $this->dceData = array_filter($this->dceData, function ($row) {
            // Check for empty serial which is a critical field
            if (empty($row['serial']) || $row['serial'] === 'N/A' || $row['serial'] === '') {
                return false;
            }

            // Check if all values in the row are empty (completely null row)
            $nonEmptyValues = array_filter($row, function ($value) {
                return !empty($value) && $value !== 'N/A' && $value !== '';
            });

            return count($nonEmptyValues) > 0;
        });

        // Re-index array after filtering
        $this->dceData = array_values($this->dceData);
        return $this->dceData;
    }

// Method to clean ARGOS data
    public function cleanArgosData()
    {
        if (empty($this->argosData)) {
            return;
        }

        // Remove rows where serial# is empty or all values are empty
        $this->argosData = array_filter($this->argosData, function ($row) {
            // Check for empty serial# which is a critical field
            if (empty($row['serial#']) || $row['serial#'] === 'N/A' || $row['serial#'] === '') {
                return false;
            }

            // Check if all values in the row are empty
            $nonEmptyValues = array_filter($row, function ($value) {
                return !empty($value) && $value !== 'N/A' && $value !== '';
            });

            return count($nonEmptyValues) > 0;
        });

        // Re-index array after filtering
        $this->argosData = array_values($this->argosData);
        return $this->argosData;
    }

// Method to clean SN data
    public function cleanSnData()
    {
        if (empty($this->snData)) {
            return;
        }

        // Remove rows where serial number is empty or all values are empty
        $this->snData = array_filter($this->snData, function ($row) {
            // Check for empty serial number which is a critical field
            if (empty($row['Serial number']) || $row['Serial number'] === 'N/A' || $row['Serial number'] === '') {
                return false;
            }

            // Check if all values in the row are empty
            $nonEmptyValues = array_filter($row, function ($value) {
                return !empty($value) && $value !== 'N/A' && $value !== '';
            });

            return count($nonEmptyValues) > 0;
        });

        // Re-index array after filtering
        $this->snData = array_values($this->snData);
        return $this->snData;
    }

// Single method to clean all data sources
    public function cleanAllData()
    {
        $this->cleanDceData();
        $this->cleanArgosData();
        $this->cleanSnData();
        return $this;
    }
}

?>