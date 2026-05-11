<?php

class ExportService
{
    public function exportToCSV(string $filename, array $headers, array $rows): void
    {
        // Prevent any buffered output from corrupting the file stream
        if (ob_get_level()) ob_end_clean();

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');

        // UTF-8 BOM so Excel opens the file with correct encoding
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, $headers);

        foreach ($rows as $row) {
            fputcsv($out, $row);
        }

        fclose($out);
        exit;
    }

    public function formatFilename(string $prefix): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $prefix);
        return $safe . '_' . date('Y-m-d_His') . '.csv';
    }

    public function exportOrderRows(array $rows): array
    {
        return array_map(fn($r) => [
            $r['id'],
            $r['order_date'],
            $r['customer_name'],
            ucfirst($r['payment_method']),
            $r['reference_number'] ?? '',
            ucfirst($r['delivery_type']),
            number_format((float)$r['total_amount'] - (float)$r['delivery_fee'], 2, '.', ''),
            number_format((float)$r['delivery_fee'],  2, '.', ''),
            number_format((float)$r['total_amount'],  2, '.', ''),
            number_format((float)$r['total_cost'],    2, '.', ''),
            number_format((float)$r['total_amount']   - (float)$r['total_cost'], 2, '.', ''),
            ucfirst($r['status']),
        ], $rows);
    }
}
