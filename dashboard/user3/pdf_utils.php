<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/db.php';

use Smalot\PdfParser\Parser;

function parseRemittancePDF($filePath, $moduleType)
{
    global $conn;

    $parser = new Parser();
    try {
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        // Debug: Log raw text to analyze structure
        file_put_contents(__DIR__ . '/last_pdf_text.txt', $text);

        $metadata = [];

        // PhilHealth Number (needed for stripping prefix from POR)
        if (preg_match('/PhilHealth Number\s*:\s*(\d+)/i', $text, $matches))
            $metadata['philhealth_no'] = $matches[1];

        // POR No / Control No
        $rawPOR = '';
        // Prioritize "POR No." which is often at the bottom
        if (preg_match('/POR No\.\s*:\s*(\S+)/i', $text, $matches)) {
            $rawPOR = $matches[1];
        } elseif (preg_match('/Document Control Number\s*:\s*(\S+)/i', $text, $matches)) {
            $rawPOR = $matches[1];
        }

        if ($rawPOR) {
            $ctrl = $rawPOR;
            $phNo = $metadata['philhealth_no'] ?? '';

            // If it starts with the 12-digit PhilHealth No, strip it
            if ($phNo && strlen($phNo) >= 10 && strpos($ctrl, $phNo) === 0) {
                $ctrl = substr($ctrl, strlen($phNo));
            }

            // Fallback: if it still has letters later but starts with numbers not matching PH No,
            // we might still want to strip leading numbers if that's the pattern.
            // But stripping the explicit PH No prefix is safer for all-digit cases.
            // Let's also keep the "start from first letter" logic as a secondary cleanup for alphanumeric ones.
            if (preg_match('/[a-zA-Z]/', $ctrl, $m, PREG_OFFSET_CAPTURE)) {
                // Only strip if there are leading digits and they aren't part of some other ID
                // For now, if we already stripped PH No, we're likely good.
                // But let's re-apply the "start from letter" if it's alphanumeric to be consistent with previous fixes.
                $ctrl = substr($ctrl, $m[0][1]);
            }

            $metadata['control_no'] = $ctrl;
        }

        // Period
        if (preg_match('/Applicable Period\s*:\s*(\d{2}-\d{4})/i', $text, $matches))
            $metadata['period'] = $matches[1];

        // Date Generated
        if (preg_match('/Date\/Time Generated\s*:\s*([\d\/:\s]+)/i', $text, $matches))
            $metadata['date_generated'] = trim($matches[1]);

        // Date Received
        if (preg_match('/Date Received\s*:\s*([\d\/:\s]+)/i', $text, $matches))
            $metadata['date_received'] = trim($matches[1]);

        // Employer Details
        if (preg_match('/Employer Name\s*:\s*([^\n\r]+)/i', $text, $matches))
            $metadata['employer_name'] = trim($matches[1]);
        if (preg_match('/Employer TIN\s*:\s*([\d-]+)/i', $text, $matches))
            $metadata['employer_tin'] = $matches[1];
        if (preg_match('/Employer Type\s*:\s*([^\n\r]+)/i', $text, $matches))
            $metadata['employer_type'] = trim($matches[1]);
        if (preg_match('/Group Name\s*:\s*([^\n\r]+)/i', $text, $matches))
            $metadata['group_name'] = trim($matches[1]);
        if (preg_match('/Employer Address\s*:\s*([^\n\r]+)/i', $text, $matches))
            $metadata['employer_address'] = trim($matches[1]);

        // Members Extraction
        $rows = explode("\n", $text);
        $members = [];

        foreach ($rows as $row) {
            $row = trim($row);
            if (empty($row))
                continue;

            // Skip header if it shows up in rows
            if (stripos($row, "PhilHealth No.") !== false)
                continue;

            // More flexible pattern for member rows
            // 1 020002329035 ABA 	MICHAEL	ASOY     1,499.15     1,499.15A
            $cleaned = preg_replace('/\t+/', ' ', $row);
            if (preg_match('/^(\d+)\s+(\d{10,12})\s+(.+?)\s+([\d,.]+)\s+([\d,.]+)([A-Z]{1,2})\s*$/', $cleaned, $m)) {
                $rawNames = trim($m[3]);
                $nameParts = preg_split('/\s+/', $rawNames);

                $surname = array_shift($nameParts);
                $middle = (count($nameParts) > 0) ? array_pop($nameParts) : '';
                $given = implode(' ', $nameParts);

                $members[] = [
                    'no' => $m[2],
                    'surname' => $surname,
                    'given' => $given,
                    'middle' => $middle,
                    'ps' => (float) str_replace(',', '', $m[4]),
                    'es' => (float) str_replace(',', '', $m[5]),
                    'status' => $m[6]
                ];
            }
        }

        return ['metadata' => $metadata, 'members' => $members];

    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}
?>